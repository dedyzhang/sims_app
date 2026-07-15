<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\SppPembayaran;
use App\Services\Keuangan\SppService;
use App\Support\KeuanganBank;
use App\Support\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Modul Keuangan untuk Bendahara (juga admin/superadmin).
 *
 * - Grid pembayaran SPP per kelas (siswa × 12 bulan tahun ajaran Juli–Juni).
 * - Verifikasi bukti pembayaran yang diunggah ortu/siswa.
 * - Pengaturan bank/metode pembayaran.
 */
class KeuanganController extends Controller
{
    public function __construct(private SppService $spp) {}

    /** Daftar kelas + ringkasan untuk dipilih bendahara. */
    public function index(Request $request)
    {
        $ta = $this->resolveTahunAjaran($request);

        $kelas = Kelas::withCount('siswa')->orderBy('tingkat')->orderBy('kelas')->get();

        // Ringkasan lunas per kelas pada tahun ajaran ini.
        $lunasPerKelas = SppPembayaran::where('tahun_ajaran', $ta)
            ->where('spp_pembayaran.status', SppPembayaran::STATUS_LUNAS)
            ->join('siswa', 'siswa.uuid', '=', 'spp_pembayaran.id_siswa')
            ->selectRaw('siswa.id_kelas, COUNT(*) as lunas, SUM(spp_pembayaran.nominal) as nominal')
            ->groupBy('siswa.id_kelas')
            ->get()
            ->keyBy('id_kelas');

        $menungguTotal = SppPembayaran::where('tahun_ajaran', $ta)
            ->whereIn('status', [SppPembayaran::STATUS_MENUNGGU, SppPembayaran::STATUS_TERVERIFIKASI])
            ->count();

        return view('keuangan.index', [
            'kelasList'     => $kelas,
            'lunasPerKelas' => $lunasPerKelas,
            'menungguTotal' => $menungguTotal,
            'ta'            => $ta,
            'taOptions'     => TahunAjaran::options(),
        ]);
    }

    /** Grid pembayaran satu kelas. */
    public function kelas(Request $request, Kelas $kelas)
    {
        $ta   = $this->resolveTahunAjaran($request);
        $rows = $this->spp->gridForKelas($kelas, $ta);

        return view('keuangan.kelas', [
            'kelas'      => $kelas,
            'rows'       => $rows,
            'bulanList'  => TahunAjaran::bulanList($ta),
            'ta'         => $ta,
            'taOptions'  => TahunAjaran::options(),
        ]);
    }

    /** Form input VA & nominal SPP/bulan per siswa dalam satu kelas. */
    public function pengaturanKelas(Request $request, Kelas $kelas)
    {
        $ta = $this->resolveTahunAjaran($request);

        return view('keuangan.pengaturan', [
            'kelas'     => $kelas,
            'siswaList' => $kelas->siswa()->get(),
            'ta'        => $ta,
        ]);
    }

    /** Simpan VA & nominal SPP per siswa; opsional terapkan ke bulan yang belum dibayar. */
    public function simpanPengaturanKelas(Request $request, Kelas $kelas)
    {
        $data = $request->validate([
            'va'       => 'nullable|array',
            'va.*'     => 'nullable|string|max:60',
            'spp'      => 'nullable|array',
            'spp.*'    => 'nullable|integer|min:0',
            'terapkan' => 'nullable',
        ]);

        $ta       = $this->resolveTahunAjaran($request);
        $terapkan = $request->boolean('terapkan');

        foreach ($kelas->siswa()->get() as $s) {
            $va  = $data['va'][$s->uuid]  ?? null;
            $spp = $data['spp'][$s->uuid] ?? null;

            $s->va  = $va !== null && $va !== '' ? $va : null;
            $s->spp = $spp !== null ? (string) $spp : $s->spp;
            $s->save();

            // Terapkan nominal baru ke bulan yang BELUM dibayar (belum/ditolak),
            // tanpa mengganggu yang sedang diproses atau sudah lunas.
            if ($terapkan && $spp !== null) {
                SppPembayaran::where('id_siswa', $s->uuid)
                    ->where('tahun_ajaran', $ta)
                    ->whereIn('status', [SppPembayaran::STATUS_BELUM, SppPembayaran::STATUS_DITOLAK])
                    ->update(['nominal' => (int) $spp]);
            }
        }

        return redirect()
            ->route('keuangan.kelas', ['kelas' => $kelas->uuid, 'ta' => $ta])
            ->with('success', 'VA & nominal SPP tersimpan.');
    }

    /** Update satu atau beberapa sel pembayaran sekaligus (status/nominal/tanggal/jatuh tempo). */
    public function cell(Request $request, SppPembayaran $pembayaran)
    {
        $data = $request->validate([
            'status'           => 'nullable|in:belum,menunggu,terverifikasi,lunas,ditolak',
            'nominal'          => 'nullable|integer|min:0',
            'tanggal_bayar'    => 'required_unless:status,belum|nullable|date',
            'jatuh_tempo'      => 'nullable|date',
            'catatan'          => 'nullable|string|max:500',
            'selected_bulans'  => 'nullable|array',
            'selected_bulans.*'=> 'integer|between:1,12',
        ]);

        $selectedBulans = $data['selected_bulans'] ?? [$pembayaran->bulan];

        $payments = SppPembayaran::where('id_siswa', $pembayaran->id_siswa)
            ->where('tahun_ajaran', $pembayaran->tahun_ajaran)
            ->whereIn('bulan', $selectedBulans)
            ->get();

        foreach ($payments as $p) {
            if (array_key_exists('nominal', $data) && $data['nominal'] !== null) {
                $p->nominal = $data['nominal'];
            }
            if (array_key_exists('jatuh_tempo', $data)) {
                $p->jatuh_tempo = $data['jatuh_tempo'];
            }
            if (array_key_exists('catatan', $data)) {
                $p->catatan = $data['catatan'];
            }

            if (!empty($data['status'])) {
                $this->applyStatus($p, $data['status'], $data['tanggal_bayar'] ?? null);
            } elseif (array_key_exists('tanggal_bayar', $data)) {
                $p->tanggal_bayar = $data['tanggal_bayar'];
            }

            $p->save();
        }

        if ($request->wantsJson()) {
            return response()->json([
                'ok'         => true,
                'pembayaran' => $this->serialize($pembayaran),
            ]);
        }
        return back()->with('success', 'Pembayaran diperbarui.');
    }

    /**
     * Dua antrian verifikasi, masing-masing dikelompokkan per upload (batch):
     * 1) menunggu        → perlu dicek buktinya (→ terverifikasi)
     * 2) terverifikasi   → perlu divalidasi via rekening koran bank (→ lunas)
     */
    public function verifikasi(Request $request)
    {
        $ta = $this->resolveTahunAjaran($request);
        $q  = trim((string) $request->query('q', ''));

        $rows = SppPembayaran::with('siswa.kelas')
            ->where('tahun_ajaran', $ta)
            ->whereIn('status', [SppPembayaran::STATUS_MENUNGGU, SppPembayaran::STATUS_TERVERIFIKASI])
            ->when($q !== '', function ($query) use ($q) {
                $query->whereHas('siswa', function ($s) use ($q) {
                    $s->where('nama', 'like', "%{$q}%")
                      ->orWhere('nis', 'like', "%{$q}%")
                      ->orWhereHas('kelas', function ($k) use ($q) {
                          $k->where('tingkat', 'like', "%{$q}%")->orWhere('kelas', 'like', "%{$q}%");
                      });
                });
            })
            ->orderBy('bulan')
            ->get();

        $byStatus = fn (string $status) => $rows
            ->where('status', $status)
            ->groupBy(fn ($p) => $p->batch_id ?? $p->uuid)
            ->sortByDesc(fn ($g) => $g->first()->updated_at)
            ->values();

        return view('keuangan.verifikasi', [
            'menungguGroups'      => $byStatus(SppPembayaran::STATUS_MENUNGGU),
            'terverifikasiGroups' => $byStatus(SppPembayaran::STATUS_TERVERIFIKASI),
            'menungguCount'       => $rows->where('status', SppPembayaran::STATUS_MENUNGGU)->count(),
            'terverifikasiCount'  => $rows->where('status', SppPembayaran::STATUS_TERVERIFIKASI)->count(),
            'q'                   => $q,
            'ta'                  => $ta,
            'taOptions'           => TahunAjaran::options(),
        ]);
    }

    /** Revisi data pembayaran (mis. perbaiki nominal/tanggal/bank) tanpa ubah status. */
    public function reviseBatch(Request $request)
    {
        $data = $request->validate([
            'nominal'       => 'required|array',
            'nominal.*'     => 'nullable|integer|min:0',
            'tanggal_bayar' => 'nullable|date',
            'bank'          => 'nullable|string|max:60',
        ]);

        $rows = SppPembayaran::whereIn('uuid', array_keys($data['nominal']))
            ->whereIn('status', [SppPembayaran::STATUS_MENUNGGU, SppPembayaran::STATUS_TERVERIFIKASI])
            ->get();

        foreach ($rows as $p) {
            if (($data['nominal'][$p->uuid] ?? null) !== null) {
                $p->nominal = (int) $data['nominal'][$p->uuid];
            }
            if ($request->filled('tanggal_bayar')) {
                $p->tanggal_bayar = $request->date('tanggal_bayar');
            }
            if ($request->filled('bank')) {
                $p->bank = (string) $request->string('bank');
            }
            $p->save();
        }

        $n = $rows->count();
        return back()->with('success', $n > 1 ? "Revisi tersimpan untuk {$n} bulan." : 'Revisi pembayaran tersimpan.');
    }

    /** Tahap 1: verifikasi bukti (menunggu → terverifikasi). */
    public function verifyBatch(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);

        $rows = SppPembayaran::whereIn('uuid', $data['ids'])
            ->where('status', SppPembayaran::STATUS_MENUNGGU)
            ->get();

        foreach ($rows as $p) {
            $p->status = SppPembayaran::STATUS_TERVERIFIKASI;
            $p->diverifikasi_oleh = auth()->id();
            $p->diverifikasi_pada = now();
            $p->catatan = null;
            $p->save();
        }

        $n = $rows->count();
        return back()->with('success', $n > 1
            ? "{$n} bulan terverifikasi. Lanjut validasi via rekening koran bank."
            : 'Bukti terverifikasi. Lanjut validasi via rekening koran bank.');
    }

    /** Tahap 2: validasi via rekening koran (terverifikasi → lunas). */
    public function validateBatch(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);

        $rows = SppPembayaran::whereIn('uuid', $data['ids'])
            ->where('status', SppPembayaran::STATUS_TERVERIFIKASI)
            ->get();

        foreach ($rows as $p) {
            $this->applyStatus($p, SppPembayaran::STATUS_LUNAS, $p->tanggal_bayar?->toDateString());
            $p->catatan = null;
            $p->save();
        }

        $n = $rows->count();
        return back()->with('success', $n > 1
            ? "{$n} bulan divalidasi & LUNAS."
            : 'Pembayaran divalidasi & LUNAS.');
    }

    /** Tolak beberapa bulan sekaligus (dari tahap menunggu maupun terverifikasi). */
    public function rejectBatch(Request $request)
    {
        $data = $request->validate([
            'ids'     => 'required|array',
            'ids.*'   => 'string',
            'catatan' => 'required|string|max:500',
        ]);

        $rows = SppPembayaran::whereIn('uuid', $data['ids'])
            ->whereIn('status', [SppPembayaran::STATUS_MENUNGGU, SppPembayaran::STATUS_TERVERIFIKASI])
            ->get();

        foreach ($rows as $p) {
            $p->status = SppPembayaran::STATUS_DITOLAK;
            $p->catatan = $data['catatan'];
            $p->diverifikasi_oleh = auth()->id();
            $p->diverifikasi_pada = now();
            $p->save();
        }

        $n = $rows->count();
        return back()->with('success', "{$n} bulan ditolak. Ortu/siswa dapat mengunggah ulang.");
    }

    /** Halaman pengaturan bank/metode pembayaran. */
    public function bank()
    {
        return view('keuangan.bank', [
            'banks' => KeuanganBank::all(),
        ]);
    }

    /** Simpan pengaturan bank. */
    public function bankUpdate(Request $request)
    {
        $data = $request->validate([
            'banks'             => 'nullable|array',
            'banks.*.nama'      => 'required|string|max:60',
            'banks.*.atas_nama' => 'nullable|string|max:120',
            'banks.*.nomor'     => 'nullable|string|max:60',
            'banks.*.warna'     => 'nullable|string|max:9',
            'banks.*.langkah'   => 'nullable|string',
            'banks.*.aktif'     => 'nullable',
        ]);

        $banks = collect($data['banks'] ?? [])->map(fn ($b) => [
            'nama'      => $b['nama'],
            'atas_nama' => $b['atas_nama'] ?? '',
            'nomor'     => $b['nomor'] ?? '',
            'warna'     => $b['warna'] ?? '#64748b',
            'langkah'   => $b['langkah'] ?? '',
            'aktif'     => !empty($b['aktif']),
        ])->all();

        KeuanganBank::save($banks);

        return back()->with('success', 'Pengaturan bank pembayaran disimpan.');
    }

    // ─────────────────────────── helper ───────────────────────────

    /** Terapkan transisi status + atur kolom verifikasi/tanggal. */
    private function applyStatus(SppPembayaran $p, string $status, ?string $tanggalBayar): void
    {
        $p->status = $status;

        if ($status === SppPembayaran::STATUS_LUNAS) {
            $p->tanggal_bayar = $tanggalBayar ? Carbon::parse($tanggalBayar) : ($p->tanggal_bayar ?? now());
            $p->diverifikasi_oleh = auth()->id();
            $p->diverifikasi_pada = now();
        } elseif ($status === SppPembayaran::STATUS_TERVERIFIKASI) {
            if ($tanggalBayar !== null) {
                $p->tanggal_bayar = Carbon::parse($tanggalBayar);
            }
            $p->diverifikasi_oleh = auth()->id();
            $p->diverifikasi_pada = now();
        } elseif ($status === SppPembayaran::STATUS_BELUM) {
            $p->tanggal_bayar = null;
            $p->bank = null;
            $p->diverifikasi_oleh = null;
            $p->diverifikasi_pada = null;
            $p->catatan = null;
        } else {
            if ($tanggalBayar !== null) {
                $p->tanggal_bayar = Carbon::parse($tanggalBayar);
            }
        }
    }

    private function resolveTahunAjaran(Request $request): string
    {
        $ta = (string) $request->query('ta', '');
        return in_array($ta, TahunAjaran::options(), true) ? $ta : TahunAjaran::current();
    }

    private function serialize(SppPembayaran $p): array
    {
        return [
            'uuid'          => $p->uuid,
            'bulan'         => $p->bulan,
            'status'        => $p->status,
            'nominal'       => $p->nominal,
            'tanggal_bayar' => $p->tanggal_bayar?->toDateString(),
            'jatuh_tempo'   => $p->jatuh_tempo?->toDateString(),
            'catatan'       => $p->catatan,
        ];
    }
}
