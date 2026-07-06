<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Orangtua;
use App\Models\P3Kategori;
use App\Models\P3Poin;
use App\Models\P3Temp;
use App\Models\Sekretaris;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Sistem P3 (Pelanggaran, Prestasi, Partisipasi): tiga kategori independen,
 * akumulatif per semester (bukan pengurangan dari basis). Alur pengajuan
 * (guru/walikelas/sekretaris) → approval (admin/kesiswaan) sama seperti Poin.
 */
class P3Controller extends Controller
{
    // ─────────────── Akses (identik pola dengan PoinController) ───────────────

    private function bisaKelola(): bool
    {
        return auth()->user()?->canAccess('manage_disiplin') ?? false;
    }

    private function guardKelola(): void
    {
        abort_unless($this->bisaKelola(), 403, 'Hanya admin/kesiswaan yang dapat mengelola P3.');
    }

    /** Bisa melihat ringkasan/riwayat P3: admin/kesiswaan (semua siswa) atau wali kelas (kelasnya saja). */
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
        abort_unless($this->bisaAjukan(), 403, 'Hanya guru atau sekretaris kelas yang dapat mengajukan P3.');
    }

    private function pengajuInfo(): array
    {
        $u = auth()->user();
        if ($u->guru) return ['yang_mengajukan' => 'guru', 'id_pengajuan' => $u->guru->uuid];
        return ['yang_mengajukan' => 'sekretaris', 'id_pengajuan' => $u->siswa->uuid];
    }

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

    // ─────────────── Master Kategori P3 (admin/kesiswaan) ───────────────

    public function index(Request $request)
    {
        $this->guardKelola();
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['jenis', 'deskripsi', 'poin'], 'jenis');
        $kategoris = P3Kategori::orderBy($sort, $dir)->orderBy('deskripsi')->paginate(15)->withQueryString();
        return view('p3.index', compact('kategoris'));
    }

    public function create()
    {
        $this->guardKelola();
        return view('p3.create');
    }

    public function store(Request $request)
    {
        $this->guardKelola();
        $data = $request->validate([
            'jenis'     => 'required|in:prestasi,partisipasi,pelanggaran',
            'deskripsi' => 'required|string|max:255',
            'poin'      => 'required|integer|min:0',
        ]);
        P3Kategori::create($data);
        return redirect()->route('p3.index')->with('success', 'Kategori P3 ditambahkan.');
    }

    public function edit(P3Kategori $kategori)
    {
        $this->guardKelola();
        return view('p3.edit', compact('kategori'));
    }

    public function update(Request $request, P3Kategori $kategori)
    {
        $this->guardKelola();
        $data = $request->validate([
            'jenis'     => 'required|in:prestasi,partisipasi,pelanggaran',
            'deskripsi' => 'required|string|max:255',
            'poin'      => 'required|integer|min:0',
        ]);
        $kategori->update($data);
        return redirect()->route('p3.index')->with('success', 'Kategori P3 diperbarui.');
    }

    public function destroy(P3Kategori $kategori)
    {
        $this->guardKelola();
        $kategori->delete();
        return redirect()->route('p3.index')->with('success', 'Kategori P3 dihapus.');
    }

    /** AJAX: kategori sesuai jenis, dipakai utk auto-isi deskripsi+poin di form. */
    public function kategoriGet(Request $request)
    {
        $kategoris = P3Kategori::when($request->jenis, fn ($q) => $q->where('jenis', $request->jenis))
            ->orderBy('deskripsi')->get();
        return response()->json(['kategoris' => $kategoris]);
    }

    // ─────────────── P3 Siswa (admin/kesiswaan: langsung tercatat) ───────────────

    public function siswaIndex(Request $request)
    {
        $this->guardLihatSiswa();
        [$sort, $dir] = \App\Support\TableSort::resolve(
            $request, ['nama', 'nis', 'kelas', 'prestasi', 'partisipasi', 'pelanggaran'], 'nama'
        );

        $siswas = ($this->bisaKelola() ? Siswa::with('kelas') : $this->siswaScope()->with('kelas'))
            ->when($request->search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('nama', 'like', '%' . $request->search . '%')
                ->orWhere('nis', 'like', '%' . $request->search . '%')))
            ->get();
        $countMap = [];
        foreach (P3Poin::selectRaw('id_siswa, jenis, count(*) as c')->groupBy('id_siswa', 'jenis')->get() as $r) {
            $countMap[$r->id_siswa][$r->jenis] = $r->c;
        }

        $sorted = $siswas->sortBy(function ($s) use ($sort, $countMap) {
            return match ($sort) {
                'prestasi', 'partisipasi', 'pelanggaran' => $countMap[$s->uuid][$sort] ?? 0,
                'nis'   => $s->nis,
                'kelas' => $s->kelas ? $s->kelas->tingkat . $s->kelas->kelas : '',
                default => $s->nama,
            };
        }, SORT_REGULAR, $dir === 'desc')->values();

        $siswas = \App\Support\TableSort::paginateCollection($sorted, 20);
        return view('p3.siswa.index', compact('siswas', 'countMap'));
    }

    public function siswaShow(Request $request, Siswa $siswa)
    {
        $this->guardLihatSiswa();
        if (!$this->bisaKelola()) {
            $u = auth()->user();
            abort_unless($u->guru?->walikelas && $siswa->id_kelas === $u->guru->walikelas->id_kelas, 403, 'Siswa ini bukan siswa kelas Anda.');
        }
        $totals = $this->totalsFor($siswa->uuid);
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['tanggal', 'jenis', 'deskripsi', 'poin'], 'tanggal', 'desc');
        $rows = P3Poin::where('id_siswa', $siswa->uuid)->orderBy($sort, $dir)->paginate(20)->withQueryString();
        return view('p3.siswa.show', compact('siswa', 'rows', 'totals'));
    }

    /** Total poin per kategori (Prestasi/Partisipasi/Pelanggaran) untuk satu siswa. */
    public static function totalsFor(string $siswaUuid): array
    {
        $sums = P3Poin::where('id_siswa', $siswaUuid)
            ->selectRaw('jenis, sum(poin) as total')->groupBy('jenis')->pluck('total', 'jenis');
        return [
            'prestasi'    => (int) ($sums['prestasi'] ?? 0),
            'partisipasi' => (int) ($sums['partisipasi'] ?? 0),
            'pelanggaran' => (int) ($sums['pelanggaran'] ?? 0),
        ];
    }

    public function createPoin(Siswa $siswa)
    {
        $this->guardKelola();
        return view('p3.siswa.create', compact('siswa'));
    }

    public function storePoin(Request $request, Siswa $siswa)
    {
        $this->guardKelola();
        $data = $request->validate([
            'tanggal'   => 'required|date',
            'jenis'     => 'required|in:prestasi,partisipasi,pelanggaran',
            'deskripsi' => 'required|string',
            'poin'      => 'required|integer|min:0',
        ]);
        P3Poin::create($data + ['id_siswa' => $siswa->uuid, 'id_semester' => Semester::aktif()?->id]);
        return redirect()->route('p3.siswa.show', $siswa)->with('success', 'P3 ditambahkan.');
    }

    public function editPoin(P3Poin $poin)
    {
        $this->guardKelola();
        return view('p3.siswa.edit', compact('poin'));
    }

    public function updatePoin(Request $request, P3Poin $poin)
    {
        $this->guardKelola();
        $data = $request->validate([
            'tanggal'   => 'required|date',
            'jenis'     => 'required|in:prestasi,partisipasi,pelanggaran',
            'deskripsi' => 'required|string',
            'poin'      => 'required|integer|min:0',
        ]);
        $poin->update($data);
        return redirect()->route('p3.siswa.show', $poin->id_siswa)->with('success', 'P3 diperbarui.');
    }

    public function deletePoin(P3Poin $poin)
    {
        $this->guardKelola();
        $siswaUuid = $poin->id_siswa;
        $poin->delete();
        return redirect()->route('p3.siswa.show', $siswaUuid)->with('success', 'P3 dihapus.');
    }

    /** Cetak laporan P3 semester berjalan: A. Prestasi, B. Partisipasi, C. Pelanggaran. */
    public function printPoin(Siswa $siswa)
    {
        $this->guardKelola();
        $sem = Semester::aktif() ?? Semester::first();
        $rows = P3Poin::where('id_siswa', $siswa->uuid)
            ->when($sem, fn ($q) => $q->where('id_semester', $sem->id))
            ->orderBy('tanggal')->get();

        $grup = [
            'prestasi'    => $rows->where('jenis', 'prestasi')->values(),
            'partisipasi' => $rows->where('jenis', 'partisipasi')->values(),
            'pelanggaran' => $rows->where('jenis', 'pelanggaran')->values(),
        ];

        $kepalaGuru = Guru::whereHas('user', fn ($q) => $q->where('access', 'kepala'))->first();
        $walikelasGuru = $siswa->kelas?->walikelas?->guru;

        return view('p3.siswa.print', [
            'siswa'     => $siswa->load('kelas'),
            'sem'       => $sem,
            'grup'      => $grup,
            'sekolah'   => [
                'nama'     => Setting::get('nama_sekolah', 'Sekolah'),
                'alamat'   => Setting::get('alamat_sekolah', ''),
                'kota'     => Setting::get('kota', ''),
                'provinsi' => Setting::get('provinsi', ''),
                'telp'     => Setting::get('telp_sekolah', ''),
                'npsn'     => Setting::get('npsn', ''),
            ],
            'walikelas'    => $walikelasGuru,
            'kepala'       => $kepalaGuru?->nama ?: Setting::get('kepala_sekolah', ''),
            'tanggal'      => Carbon::now()->locale('id')->translatedFormat('d F Y'),
            'kopLogoKiri'  => $this->kopImg('kop_logo_kiri', 'img/tutwuri.png'),
            'kopLogoKanan' => $this->kopImg('kop_logo_kanan', 'img/maitreyawira_square.png'),
            'kopTeks'      => Setting::get('kop_teks'),
        ]);
    }

    private function kopImg(string $key, string $default): ?string
    {
        $v = Setting::get($key);
        if ($v && file_exists(storage_path('app/public/' . $v))) return asset('storage/' . $v);
        if (file_exists(public_path($default))) return asset($default);
        return null;
    }

    // ─────────────── Temp: pengajuan → approval admin/kesiswaan ───────────────

    public function tempIndex(Request $request)
    {
        $this->guardKelola();
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['tanggal', 'jenis', 'poin', 'created_at'], 'created_at', 'desc');
        $pendings = P3Temp::with('siswa.kelas')->where('status', 'belum')
            ->orderBy($sort, $dir)->paginate(20)->withQueryString();
        return view('p3.temp.index', compact('pendings'));
    }

    public function tempHistory(Request $request)
    {
        $this->guardKelola();
        $status = $request->status === 'disapprove' ? 'disapprove' : 'approve';
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['tanggal', 'jenis', 'poin', 'updated_at'], 'updated_at', 'desc');
        $items = P3Temp::with('siswa.kelas')->where('status', $status)
            ->orderBy($sort, $dir)->paginate(20)->withQueryString();
        return view('p3.temp.history', compact('items', 'status'));
    }

    public function tempApprove(P3Temp $temp)
    {
        $this->guardKelola();
        $temp->update(['status' => 'approve']);
        P3Poin::create([
            'tanggal' => $temp->tanggal, 'id_siswa' => $temp->id_siswa, 'jenis' => $temp->jenis,
            'deskripsi' => $temp->deskripsi, 'poin' => $temp->poin, 'id_semester' => $temp->id_semester,
        ]);
        return back()->with('success', 'Pengajuan disetujui.');
    }

    public function tempDisapprove(P3Temp $temp)
    {
        $this->guardKelola();
        $temp->update(['status' => 'disapprove']);
        return back()->with('success', 'Pengajuan ditolak.');
    }

    // ─────────────── Pengajuan oleh guru/walikelas/sekretaris ───────────────

    public function guruIndex(Request $request)
    {
        $this->guardAjukan();
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['nama', 'nis'], 'nama');
        $siswas = $this->siswaScope()->with('kelas')
            ->when($request->search, fn ($q) => $q->where('nama', 'like', '%' . $request->search . '%'))
            ->orderBy($sort, $dir)->paginate(24)->withQueryString();
        return view('p3.guru.index', compact('siswas'));
    }

    public function guruCreate(Siswa $siswa)
    {
        $this->guardAjukan();
        return view('p3.guru.create', compact('siswa'));
    }

    public function guruStore(Request $request, Siswa $siswa)
    {
        $this->guardAjukan();
        $data = $request->validate([
            'tanggal'   => 'required|date',
            'jenis'     => 'required|in:prestasi,partisipasi,pelanggaran',
            'deskripsi' => 'required|string',
            'poin'      => 'required|integer|min:0',
        ]);
        $info = $this->pengajuInfo();
        P3Temp::create($data + [
            'id_siswa' => $siswa->uuid, 'status' => 'belum', 'id_semester' => Semester::aktif()?->id,
            'yang_mengajukan' => $info['yang_mengajukan'], 'id_pengajuan' => $info['id_pengajuan'],
        ]);
        return redirect()->route('p3.guru.index')->with('success', 'Pengajuan P3 dikirim, menunggu persetujuan kesiswaan.');
    }

    public function guruRiwayat(Request $request)
    {
        $this->guardAjukan();
        $info = $this->pengajuInfo();
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['tanggal', 'jenis', 'poin', 'status', 'created_at'], 'created_at', 'desc');
        $items = P3Temp::with('siswa')
            ->where('yang_mengajukan', $info['yang_mengajukan'])->where('id_pengajuan', $info['id_pengajuan'])
            ->orderBy($sort, $dir)->paginate(20)->withQueryString();
        return view('p3.guru.riwayat', compact('items'));
    }

    // ─────────────── Self-view: siswa & orangtua ───────────────

    public function selfShow(Request $request)
    {
        $u = auth()->user();
        $siswa = $u->siswa ?: Orangtua::where('id_login', $u->uuid)->first()?->siswa;
        abort_unless($siswa, 404);
        $totals = $this->totalsFor($siswa->uuid);
        [$sort, $dir] = \App\Support\TableSort::resolve($request, ['tanggal', 'jenis', 'deskripsi', 'poin'], 'tanggal', 'desc');
        $rows = P3Poin::where('id_siswa', $siswa->uuid)->orderBy($sort, $dir)->paginate(20)->withQueryString();
        return view('p3.self', compact('siswa', 'rows', 'totals'));
    }
}
