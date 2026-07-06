<?php

namespace App\Http\Controllers;

use App\Exports\AturanExport;
use App\Imports\AturanImport;
use App\Models\Aturan;
use App\Models\Orangtua;
use App\Models\Poin;
use App\Models\PoinTemp;
use App\Models\Sekretaris;
use App\Models\Siswa;
use App\Support\ExcelWatermark;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Sistem Poin/Aturan (lama): ledger deduksi poin siswa dari basis 100.
 * Guru/walikelas/sekretaris hanya bisa MENGAJUKAN (poin_temp); admin/kesiswaan
 * yang menyetujui/menolak, atau menambahkan poin langsung tanpa pengajuan.
 */
class PoinController extends Controller
{
    // ─────────────── Akses ───────────────

    private function bisaKelola(): bool
    {
        return auth()->user()?->canAccess('manage_disiplin') ?? false;
    }

    private function guardKelola(): void
    {
        abort_unless($this->bisaKelola(), 403, 'Hanya admin/kesiswaan yang dapat mengelola poin.');
    }

    /** Bisa melihat ringkasan/ledger poin: admin/kesiswaan (semua siswa) atau wali kelas (kelasnya saja). */
    private function bisaLihatSiswa(): bool
    {
        return $this->bisaKelola() || (bool) auth()->user()->guru?->walikelas;
    }

    private function guardLihatSiswa(): void
    {
        abort_unless($this->bisaLihatSiswa(), 403, 'Hanya admin/kesiswaan/wali kelas yang dapat melihat data ini.');
    }

    private function bisaAjukan(): bool
    {
        $u = auth()->user();
        if ($u->guru) return true;
        if ($u->siswa && Sekretaris::where('id_siswa', $u->siswa->uuid)->exists()) return true;
        return false;
    }

    private function guardAjukan(): void
    {
        abort_unless($this->bisaAjukan(), 403, 'Hanya guru atau sekretaris kelas yang dapat mengajukan poin.');
    }

    private function pengajuInfo(): array
    {
        $u = auth()->user();
        if ($u->guru) return ['penginput' => 'guru', 'id_input' => $u->guru->uuid];
        return ['penginput' => 'sekretaris', 'id_input' => $u->siswa->uuid];
    }

    /** Daftar siswa yang boleh diajukan poinnya: guru biasa=semua, walikelas/sekretaris=kelasnya saja. */
    private function siswaScope()
    {
        $u = auth()->user();
        if ($u->guru?->walikelas) {
            return Siswa::where('id_kelas', $u->guru->walikelas->id_kelas);
        }
        if ($u->siswa) {
            $sek = Sekretaris::where('id_siswa', $u->siswa->uuid)->first();
            if ($sek) return Siswa::where('id_kelas', $sek->id_kelas);
        }
        return Siswa::query();
    }

    // ─────────────── Perhitungan (basis 100, dikurangi/ditambah per Poin) ───────────────

    public static function hitung(string $siswaUuid): array
    {
        $rows = Poin::with('aturan')->where('id_siswa', $siswaUuid)
            ->orderBy('tanggal')->orderBy('created_at')->get();
        $sisa = 100;
        $ledger = [];
        $totalTambah = 0;
        foreach ($rows as $r) {
            $delta = $r->aturan?->jenis === 'kurang' ? -($r->aturan->poin ?? 0) : ($r->aturan->poin ?? 0);
            $sisa += $delta;
            if ($delta > 0) {
                $totalTambah += $delta;
            }
            $ledger[] = ['row' => $r, 'delta' => $delta, 'sisa' => $sisa];
        }
        return [
            'sisa' => $sisa,
            'ledger' => $ledger,
            'peringatan' => self::peringatan($sisa),
            'totalTambah' => $totalTambah,
            'adaAktivitas' => $rows->isNotEmpty(),
        ];
    }

    public static function peringatan(int $sisa): string
    {
        if ($sisa < 25) return 'Peringatan 3';
        if ($sisa < 50) return 'Peringatan 2';
        if ($sisa < 75) return 'Peringatan 1';
        return '-';
    }

    /**
     * Peringkat 3 siswa dengan sisa poin tertinggi se-sekolah (untuk widget dashboard siswa).
     * Siswa tanpa rekam jejak poin sama sekali (masih 100 default) disaring keluar — supaya
     * podium tidak dipenuhi siswa yang belum tersentuh data, bukan siswa yang benar berprestasi.
     * Jika sisa poin seri, dipisahkan lewat total poin "tambah" (poin prestasi) yang diperoleh.
     */
    public static function top3Sekolah(): \Illuminate\Support\Collection
    {
        return self::rankingAktif(Siswa::with('kelas')->get())->take(3)->values();
    }

    /** Urutkan siswa yang punya rekam jejak poin: sisa poin desc, lalu total tambah desc, lalu nama. */
    private static function rankingAktif(\Illuminate\Support\Collection $siswas): \Illuminate\Support\Collection
    {
        return $siswas
            ->map(function ($s) {
                $h = self::hitung($s->uuid);
                return ['siswa' => $s, 'sisa' => $h['sisa'], 'totalTambah' => $h['totalTambah'], 'adaAktivitas' => $h['adaAktivitas']];
            })
            ->filter(fn ($r) => $r['adaAktivitas'])
            ->sortBy([
                fn ($a, $b) => $b['sisa'] <=> $a['sisa'],
                fn ($a, $b) => $b['totalTambah'] <=> $a['totalTambah'],
                fn ($a, $b) => strcmp($a['siswa']->nama, $b['siswa']->nama),
            ])->values();
    }

    /**
     * Auto-deduksi poin saat siswa terlambat absen (khusus sistem Poin/Aturan lama).
     * Diaktifkan lewat Setting `poin_terlambat_aturan` (uuid Aturan). Idempoten per
     * siswa+tanggal+aturan agar tak dobel jika dipanggil berulang di hari yang sama.
     */
    public static function autoTerlambat(string $siswaUuid, string $tanggal): void
    {
        if (\App\Models\Setting::get('jenis_aturan', 'p3') !== 'poin') return;

        $aturanUuid = \App\Models\Setting::get('poin_terlambat_aturan');
        if (!$aturanUuid) return;

        $sudah = Poin::where('id_siswa', $siswaUuid)->where('id_aturan', $aturanUuid)
            ->whereDate('tanggal', $tanggal)->exists();
        if ($sudah) return;

        Poin::create(['tanggal' => $tanggal, 'id_siswa' => $siswaUuid, 'id_aturan' => $aturanUuid]);
    }

    // ─────────────── Master Aturan (admin/kesiswaan) ───────────────

    public function index(Request $request)
    {
        $this->guardKelola();
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['kode', 'jenis', 'poin'], 'kode');
        $aturans = Aturan::orderBy($sort, $dir)->paginate(15)->withQueryString();
        return view('poin.index', compact('aturans'));
    }

    public function create()
    {
        $this->guardKelola();
        return view('poin.create');
    }

    public function store(Request $request)
    {
        $this->guardKelola();
        $data = $request->validate([
            'kode'   => 'required|string|max:50|unique:aturan,kode',
            'jenis'  => 'required|in:tambah,kurang',
            'aturan' => 'required|string',
            'poin'   => 'required|integer|min:0',
        ]);
        Aturan::create($data);
        return redirect()->route('poin.index')->with('success', 'Aturan ditambahkan.');
    }

    public function edit(Aturan $aturan)
    {
        $this->guardKelola();
        return view('poin.edit', compact('aturan'));
    }

    public function update(Request $request, Aturan $aturan)
    {
        $this->guardKelola();
        $data = $request->validate([
            'kode'   => 'required|string|max:50|unique:aturan,kode,' . $aturan->uuid . ',uuid',
            'jenis'  => 'required|in:tambah,kurang',
            'aturan' => 'required|string',
            'poin'   => 'required|integer|min:0',
        ]);
        $aturan->update($data);
        return redirect()->route('poin.index')->with('success', 'Aturan diperbarui.');
    }

    public function destroy(Aturan $aturan)
    {
        $this->guardKelola();
        $aturan->delete();
        return redirect()->route('poin.index')->with('success', 'Aturan dihapus.');
    }

    // ─────────────── Export & Import Master Aturan (Excel) ───────────────

    public function exportAturan()
    {
        $this->guardKelola();
        return Excel::download(new AturanExport, 'master_aturan_poin_' . now()->format('Ymd_His') . '.xlsx');
    }

    public function importForm()
    {
        $this->guardKelola();
        return view('poin.import');
    }

    /**
     * Import hanya menerima file hasil exportAturan() sendiri: file harus membawa
     * watermark tersembunyi (HMAC di custom document property) yang cocok, dan
     * struktur kolomnya (nama & urutan header) harus persis sama seperti template.
     * Nilai di dalam sel data BOLEH diubah admin — itu memang tujuan importnya.
     */
    public function importAturan(Request $request)
    {
        $this->guardKelola();
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120'], [
            'file.mimes' => 'File harus berformat Excel (.xlsx atau .xls).',
        ]);

        try {
            $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('error', 'File Excel tidak valid atau rusak.');
        }

        $props = $spreadsheet->getProperties();
        $tag = $props->getCustomPropertyValue('smpv6_wm_tag');
        $sig = $props->getCustomPropertyValue('smpv6_wm_sig');
        if ($tag !== AturanExport::WATERMARK_TAG || !ExcelWatermark::verify(AturanExport::WATERMARK_TAG, $sig)) {
            return back()->with('error', 'File ini bukan hasil export dari sistem ini. Silakan klik "Export Excel" untuk mendapatkan file resmi, edit datanya, lalu import kembali file tersebut.');
        }

        $headerRow = 4;
        $sheet = $spreadsheet->getActiveSheet();
        $headers = array_map(
            fn ($col) => trim((string) $sheet->getCell($col . $headerRow)->getValue()),
            ['A', 'B', 'C', 'D', 'E']
        );
        if ($headers !== AturanExport::HEADINGS) {
            return back()->with('error', 'Struktur kolom pada file telah diubah. Silakan export ulang dan jangan mengubah nama atau urutan kolom.');
        }

        try {
            $import = new AturanImport;
            Excel::import($import, $request->file('file'));

            $msg = "Import selesai: {$import->created} aturan baru ditambahkan, {$import->updated} diperbarui.";
            $status = 'success';
            if (!empty($import->errors)) {
                $status = ($import->created + $import->updated) > 0 ? 'success' : 'error';
                $shown = array_slice($import->errors, 0, 5);
                $msg .= ' Baris bermasalah: ' . implode(' ', $shown);
                if (count($import->errors) > 5) {
                    $msg .= ' (dan ' . (count($import->errors) - 5) . ' baris lainnya)';
                }
            }

            return redirect()->route('poin.index')->with($status, $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }

    // ─────────────── Poin Siswa (admin/kesiswaan: langsung tercatat) ───────────────

    public function poinIndex(Request $request)
    {
        $this->guardLihatSiswa();
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['nama', 'nis', 'kelas', 'sisa'], 'nama');

        $siswas = ($this->bisaKelola() ? Siswa::with('kelas') : $this->siswaScope()->with('kelas'))
            ->when($request->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('nama', 'like', '%' . $request->search . '%')
                ->orWhere('nis', 'like', '%' . $request->search . '%')))
            ->get();
        $sisaMap = [];
        foreach ($siswas as $s) {
            $sisaMap[$s->uuid] = self::hitung($s->uuid)['sisa'];
        }

        $sorted = $siswas->sortBy(function ($s) use ($sort, $sisaMap) {
            return match ($sort) {
                'sisa'  => $sisaMap[$s->uuid] ?? 100,
                'nis'   => $s->nis,
                'kelas' => $s->kelas ? $s->kelas->tingkat . $s->kelas->kelas : '',
                default => $s->nama,
            };
        }, SORT_REGULAR, $dir === 'desc')->values();

        $siswas = \App\Support\TableSort::paginateCollection($sorted, 20);
        return view('poin.siswa.index', compact('siswas', 'sisaMap'));
    }

    public function poinShow(Request $request, Siswa $siswa)
    {
        $this->guardLihatSiswa();
        if (!$this->bisaKelola()) {
            $u = auth()->user();
            abort_unless($u->guru?->walikelas && $siswa->id_kelas === $u->guru->walikelas->id_kelas, 403, 'Siswa ini bukan siswa kelas Anda.');
        }
        $h = self::hitung($siswa->uuid);
        return view('poin.siswa.show', [
            'siswa' => $siswa, 'ledger' => $this->ledgerSorted($h['ledger'], $request),
            'sisa' => $h['sisa'], 'peringatan' => $h['peringatan'],
        ]);
    }

    /**
     * Urutkan & paginasi ledger (array ['row','delta','sisa']) hasil self::hitung().
     * Nilai "sisa" tiap baris SUDAH final (dihitung kronologis di hitung()) — di sini
     * hanya mengubah URUTAN TAMPILAN, bukan menghitung ulang.
     */
    private function ledgerSorted(array $ledger, Request $request)
    {
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['tanggal', 'jenis', 'poin', 'sisa'], 'tanggal');
        $desc = $dir === 'desc';

        $sorted = collect($ledger)->sortBy(function ($l) use ($sort) {
            return match ($sort) {
                'jenis' => $l['row']->aturan?->jenis,
                'poin'  => $l['delta'],
                'sisa'  => $l['sisa'],
                default => $l['row']->tanggal,
            };
        }, SORT_REGULAR, $desc)->values();

        return \App\Support\TableSort::paginateCollection($sorted, 20);
    }

    public function poinCreate(Siswa $siswa)
    {
        $this->guardKelola();
        return view('poin.siswa.create', compact('siswa'));
    }

    /** AJAX: daftar Aturan sesuai jenis (tambah/kurang). */
    public function poinGetAturan(Request $request)
    {
        $aturans = Aturan::when($request->jenis, fn ($q) => $q->where('jenis', $request->jenis))
            ->orderBy('kode')->get();
        return response()->json(['aturans' => $aturans]);
    }

    public function poinStore(Request $request, Siswa $siswa)
    {
        $this->guardKelola();
        $data = $request->validate([
            'tanggal' => 'required|date',
            'aturan'  => 'required|exists:aturan,uuid',
        ]);
        Poin::create(['tanggal' => $data['tanggal'], 'id_siswa' => $siswa->uuid, 'id_aturan' => $data['aturan']]);
        return redirect()->route('poin.siswa.show', $siswa)->with('success', 'Poin ditambahkan.');
    }

    public function poinDelete(Poin $poin)
    {
        $this->guardKelola();
        $siswaUuid = $poin->id_siswa;
        $poin->delete();
        return redirect()->route('poin.siswa.show', $siswaUuid)->with('success', 'Poin dihapus.');
    }

    // ─────────────── Temp: pengajuan → approval admin/kesiswaan ───────────────

    public function tempIndex(Request $request)
    {
        $this->guardKelola();
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['tanggal', 'created_at'], 'created_at', 'desc');
        $pendings = PoinTemp::with(['aturan', 'siswa.kelas'])->where('status', 'belum')
            ->orderBy($sort, $dir)->paginate(20)->withQueryString();
        return view('poin.temp.index', compact('pendings'));
    }

    public function tempHistory(Request $request)
    {
        $this->guardKelola();
        $status = $request->status === 'disapprove' ? 'disapprove' : 'approve';
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['tanggal', 'updated_at'], 'updated_at', 'desc');
        $items = PoinTemp::with(['aturan', 'siswa.kelas'])->where('status', $status)
            ->orderBy($sort, $dir)->paginate(20)->withQueryString();
        return view('poin.temp.history', compact('items', 'status'));
    }

    public function tempUpdate(Request $request, PoinTemp $temp)
    {
        $this->guardKelola();
        $data = $request->validate(['status' => 'required|in:approve,disapprove']);
        $temp->status = $data['status'];
        $temp->save();

        if ($data['status'] === 'approve') {
            Poin::create(['tanggal' => $temp->tanggal, 'id_siswa' => $temp->id_siswa, 'id_aturan' => $temp->id_aturan]);
        }

        return back()->with('success', $data['status'] === 'approve' ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.');
    }

    /** Setujui/tolak semua pengajuan yang masih menunggu (status 'belum') sekaligus. */
    public function tempBulkUpdate(Request $request)
    {
        $this->guardKelola();
        $data = $request->validate(['status' => 'required|in:approve,disapprove']);

        $pendings = PoinTemp::where('status', 'belum')->get();
        \Illuminate\Support\Facades\DB::transaction(function () use ($pendings, $data) {
            foreach ($pendings as $temp) {
                $temp->status = $data['status'];
                $temp->save();
                if ($data['status'] === 'approve') {
                    Poin::create(['tanggal' => $temp->tanggal, 'id_siswa' => $temp->id_siswa, 'id_aturan' => $temp->id_aturan]);
                }
            }
        });

        $count = $pendings->count();
        $msg = $data['status'] === 'approve'
            ? "{$count} pengajuan poin disetujui."
            : "{$count} pengajuan poin ditolak.";

        return redirect()->route('poin.temp.index')->with('success', $msg);
    }

    // ─────────────── Dashboard kedisiplinan (khusus sistem Poin/Aturan) ───────────────

    /**
     * Peringkat 10 siswa dengan sisa poin terbesar (paling sedikit pelanggaran),
     * dipilah per kelas / per tingkat / seluruh sekolah.
     */
    public function dashboard(Request $request)
    {
        $this->guardKelola();

        $scope = in_array($request->scope, ['kelas', 'tingkat', 'sekolah'], true) ? $request->scope : 'sekolah';
        $kelasList = \App\Models\Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $tingkatList = $kelasList->pluck('tingkat')->unique()->sort()->values();

        $selKelas = $scope === 'kelas' ? ($request->kelas ?: optional($kelasList->first())->uuid) : null;
        $selTingkat = $scope === 'tingkat' ? ($request->filled('tingkat') ? (int) $request->tingkat : $tingkatList->first()) : null;

        $query = Siswa::with('kelas');
        if ($scope === 'kelas' && $selKelas) {
            $query->where('id_kelas', $selKelas);
        } elseif ($scope === 'tingkat' && $selTingkat !== null) {
            $kelasIds = $kelasList->where('tingkat', $selTingkat)->pluck('uuid');
            $query->whereIn('id_kelas', $kelasIds);
        }
        $siswas = $query->get();
        $ranked = self::rankingAktif($siswas);

        return view('poin.dashboard', [
            'top10'      => $ranked->take(10)->values(),
            'totalSiswa' => $siswas->count(),
            'scope'      => $scope,
            'kelasList'  => $kelasList,
            'tingkatList' => $tingkatList,
            'selKelas'   => $selKelas,
            'selTingkat' => $selTingkat,
        ]);
    }

    // ─────────────── Pengajuan oleh guru/walikelas/sekretaris ───────────────

    public function guruIndex(Request $request)
    {
        $this->guardAjukan();
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['nama', 'nis'], 'nama');
        $siswas = $this->siswaScope()->with('kelas')
            ->when($request->search, fn ($q) => $q->where('nama', 'like', '%' . $request->search . '%'))
            ->orderBy($sort, $dir)->paginate(24)->withQueryString();
        return view('poin.guru.index', compact('siswas'));
    }

    public function guruCreate(Siswa $siswa)
    {
        $this->guardAjukan();
        return view('poin.guru.create', compact('siswa'));
    }

    public function guruStore(Request $request, Siswa $siswa)
    {
        $this->guardAjukan();
        $data = $request->validate([
            'tanggal' => 'required|date',
            'aturan'  => 'required|exists:aturan,uuid',
        ]);
        $info = $this->pengajuInfo();
        PoinTemp::create([
            'tanggal'   => $data['tanggal'], 'id_aturan' => $data['aturan'], 'id_siswa' => $siswa->uuid,
            'penginput' => $info['penginput'], 'id_input' => $info['id_input'], 'status' => 'belum',
        ]);
        return redirect()->route('poin.guru.index')->with('success', 'Pengajuan poin dikirim, menunggu persetujuan kesiswaan.');
    }

    public function guruRiwayat(Request $request)
    {
        $this->guardAjukan();
        $info = $this->pengajuInfo();
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['tanggal', 'status', 'created_at'], 'created_at', 'desc');
        $items = PoinTemp::with(['aturan', 'siswa'])
            ->where('penginput', $info['penginput'])->where('id_input', $info['id_input'])
            ->orderBy($sort, $dir)->paginate(20)->withQueryString();
        return view('poin.guru.riwayat', compact('items'));
    }

    // ─────────────── Self-view: siswa & orangtua ───────────────

    public function selfShow(Request $request)
    {
        $u = auth()->user();
        $siswa = $u->siswa ?: Orangtua::where('id_login', $u->uuid)->first()?->siswa;
        abort_unless($siswa, 404);
        $h = self::hitung($siswa->uuid);
        return view('poin.self', [
            'siswa' => $siswa, 'ledger' => $this->ledgerSorted($h['ledger'], $request),
            'sisa' => $h['sisa'], 'peringatan' => $h['peringatan'],
        ]);
    }
}
