<?php

namespace App\Http\Controllers\Keuangan;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\SppPembayaran;
use App\Services\Keuangan\BendaharaAntrianDigest;
use App\Services\Keuangan\SppAnomalyDetector;
use App\Services\Keuangan\SppMonthlyDashboard;
use App\Services\Keuangan\SppMutasiMatchingService;
use App\Services\Keuangan\SppService;
use App\Services\Keuangan\SppActivityLogger;
use App\Services\Keuangan\SppNotifier;
use App\Services\Keuangan\SppOcrAssistService;
use App\Services\Keuangan\SppVerificationQueue;
use Spatie\Activitylog\Models\Activity;
use App\Support\KeuanganBank;
use App\Support\RekeningKoran\RekeningKoranParserResolver;
use App\Support\TahunAjaran;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
    public function index(
        Request $request,
        SppMonthlyDashboard $dashboard,
        BendaharaAntrianDigest $digest,
    ) {
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

        $ringkasanAntrian = $digest->ringkasan($ta);
        $menungguTotal = $ringkasanAntrian['menunggu'] + $ringkasanAntrian['terverifikasi'];

        $tahun = (int) $request->query('tahun', now()->year);
        $bulan = (int) $request->query('bulan', now()->month);

        return view('keuangan.index', [
            'kelasList'        => $kelas,
            'lunasPerKelas'    => $lunasPerKelas,
            'menungguTotal'    => $menungguTotal,
            'ringkasanAntrian' => $ringkasanAntrian,
            'ringkasan'        => $dashboard->ringkasanTahun($ta),
            'bulanIni'         => $dashboard->ringkasanBulanKalender($tahun, $bulan),
            'ta'               => $ta,
            'taOptions'        => TahunAjaran::options(),
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
            'catatan_bendahara'=> 'nullable|string|max:500',
            'selected_bulans'  => 'nullable|array',
            'selected_bulans.*'=> 'integer|between:1,12',
        ]);

        $selectedBulans = $data['selected_bulans'] ?? [$pembayaran->bulan];

        $payments = SppPembayaran::where('id_siswa', $pembayaran->id_siswa)
            ->where('tahun_ajaran', $pembayaran->tahun_ajaran)
            ->whereIn('bulan', $selectedBulans)
            ->get();

        $statusBerubah = collect();

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
            // catatan_bendahara SENGAJA tak ikut ditimpa oleh applyStatus()/status manapun di
            // bawah — beda dari `catatan` (alasan tolak, dihapus otomatis tiap transisi status).
            // Ini catatan bebas per bulan utk orang tua, harus bertahan lintas status.
            if (array_key_exists('catatan_bendahara', $data)) {
                $p->catatan_bendahara = $data['catatan_bendahara'];
            }

            if (!empty($data['status'])) {
                $sebelum = $p->status;
                $this->applyStatus($p, $data['status'], $data['tanggal_bayar'] ?? null);
                if ($sebelum !== $p->status) {
                    SppActivityLogger::logStatusChange($p, 'spp_status_diubah', $sebelum, $p->status, auth()->id());
                    if (in_array($p->status, [
                        SppPembayaran::STATUS_TERVERIFIKASI,
                        SppPembayaran::STATUS_LUNAS,
                        SppPembayaran::STATUS_DITOLAK,
                    ], true)) {
                        $statusBerubah->push($p);
                    }
                }
            } elseif (array_key_exists('tanggal_bayar', $data)) {
                $p->tanggal_bayar = $data['tanggal_bayar'];
            }

            $p->save();
        }

        foreach ($statusBerubah->groupBy('status') as $status => $group) {
            $event = match ($status) {
                SppPembayaran::STATUS_TERVERIFIKASI => 'terverifikasi',
                SppPembayaran::STATUS_LUNAS => 'lunas',
                SppPembayaran::STATUS_DITOLAK => 'ditolak',
                default => null,
            };
            if ($event) {
                SppNotifier::statusDiperbarui($group->values(), $event);
            }
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
    public function verifikasi(
        Request $request,
        SppVerificationQueue $queue,
        SppAnomalyDetector $anomaly,
        SppMutasiMatchingService $matching,
    ) {
        $ta = $this->resolveTahunAjaran($request);
        $q  = trim((string) $request->query('q', ''));
        $prioritas = $request->boolean('prioritas');
        $filterAnomali = $request->query('filter') === 'anomali';
        $anomaliMap = $anomaly->scan($ta)->keyBy(fn ($row) => $row['pembayaran']->uuid);

        if ($prioritas) {
            $groups = $queue->prioritizedGroups($ta, $q !== '' ? $q : null);
            if ($filterAnomali) {
                $groups = $groups->filter(function ($scoredGroup) use ($anomaliMap) {
                    return $scoredGroup->contains(fn ($item) => $anomaliMap->has($item['pembayaran']->uuid));
                })->values();
            }

            $menungguGroups = $groups
                ->filter(fn ($g) => $g->first()['pembayaran']->status === SppPembayaran::STATUS_MENUNGGU)
                ->map(fn ($scoredGroup) => [
                    'group'          => $scoredGroup->pluck('pembayaran'),
                    'priorityScore'  => $scoredGroup->max('skor'),
                    'priorityAlasan' => $scoredGroup->first()['alasan'] ?? [],
                ])
                ->values();

            $terverifikasiGroups = $groups
                ->filter(fn ($g) => $g->first()['pembayaran']->status === SppPembayaran::STATUS_TERVERIFIKASI)
                ->map(fn ($scoredGroup) => [
                    'group'          => $scoredGroup->pluck('pembayaran'),
                    'priorityScore'  => $scoredGroup->max('skor'),
                    'priorityAlasan' => $scoredGroup->first()['alasan'] ?? [],
                ])
                ->values();

            $menungguCount = $menungguGroups->sum(fn ($g) => $g['group']->count());
            $terverifikasiCount = $terverifikasiGroups->sum(fn ($g) => $g['group']->count());
        } else {
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
                ->when($filterAnomali, function ($query) use ($anomaliMap) {
                    $query->whereIn('uuid', $anomaliMap->keys());
                })
                ->orderBy('bulan')
                ->get();

            $byStatus = fn (string $status) => $rows
                ->where('status', $status)
                ->groupBy(fn ($p) => $p->batch_id ?? $p->uuid)
                ->sortByDesc(fn ($g) => $g->first()->updated_at)
                ->map(fn ($group) => [
                    'group'          => $group,
                    'priorityScore'  => null,
                    'priorityAlasan' => [],
                ])
                ->values();

            $menungguGroups = $byStatus(SppPembayaran::STATUS_MENUNGGU);
            $terverifikasiGroups = $byStatus(SppPembayaran::STATUS_TERVERIFIKASI);
            $menungguCount = $rows->where('status', SppPembayaran::STATUS_MENUNGGU)->count();
            $terverifikasiCount = $rows->where('status', SppPembayaran::STATUS_TERVERIFIKASI)->count();
        }

        $rekonsiliasiAntrian = $matching->antrianValidasiBank($ta);
        $rekonsiliasiRingkas = [
            'belum_cocok'  => $rekonsiliasiAntrian->count(),
            'sudah_lunas'  => SppPembayaran::where('tahun_ajaran', $ta)
                ->where('status', SppPembayaran::STATUS_LUNAS)
                ->count(),
        ];

        $auditLogs = Activity::inLog(SppActivityLogger::LOG_NAME)
            ->latest()
            ->paginate(30);

        $tab = $request->query('tab');
        if (! in_array($tab, ['cek', 'validasi', 'audit'], true)) {
            $tab = $menungguCount > 0 ? 'cek' : ($terverifikasiCount > 0 ? 'validasi' : 'cek');
        }

        return view('keuangan.verifikasi', [
            'menungguGroups'      => $menungguGroups,
            'terverifikasiGroups' => $terverifikasiGroups,
            'menungguCount'       => $menungguCount,
            'terverifikasiCount'  => $terverifikasiCount,
            'anomaliMap'          => $anomaliMap,
            'anomaliCount'        => $anomaliMap->count(),
            'rekonsiliasiAntrian' => $rekonsiliasiAntrian,
            'rekonsiliasiRingkas' => $rekonsiliasiRingkas,
            'auditLogs'           => $auditLogs,
            'prioritas'           => $prioritas,
            'filterAnomali'       => $filterAnomali,
            'activeTab'           => $tab,
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

        $n = 0;
        $terverifikasi = collect();
        DB::transaction(function () use ($data, &$n, &$terverifikasi) {
            foreach ($data['ids'] as $id) {
                $p = SppPembayaran::where('uuid', $id)
                    ->where('status', SppPembayaran::STATUS_MENUNGGU)
                    ->lockForUpdate()
                    ->first();

                if (! $p) {
                    continue;
                }

                $sebelum = $p->status;
                $p->status = SppPembayaran::STATUS_TERVERIFIKASI;
                $p->diverifikasi_oleh = auth()->id();
                $p->diverifikasi_pada = now();
                $p->catatan = null;
                $p->save();
                SppActivityLogger::logStatusChange($p, 'spp_verifikasi_disetujui', $sebelum, $p->status, auth()->id());
                app(SppOcrAssistService::class)->purgeForPembayaran($p);
                $terverifikasi->push($p);
                $n++;
            }
        });

        SppNotifier::statusDiperbarui($terverifikasi, 'terverifikasi');

        return back()->with('success', $n > 1
            ? "{$n} bulan terverifikasi. Lanjut validasi via rekening koran bank."
            : 'Bukti terverifikasi. Lanjut validasi via rekening koran bank.');
    }

    /** Tahap 2: validasi via rekening koran (terverifikasi → lunas). */
    public function validateBatch(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'string']);

        $n = 0;
        $lunas = collect();
        DB::transaction(function () use ($data, &$n, &$lunas) {
            foreach ($data['ids'] as $id) {
                $p = SppPembayaran::where('uuid', $id)
                    ->where('status', SppPembayaran::STATUS_TERVERIFIKASI)
                    ->lockForUpdate()
                    ->first();

                if (! $p) {
                    continue;
                }

                $sebelum = $p->status;
                $this->applyStatus($p, SppPembayaran::STATUS_LUNAS, $p->tanggal_bayar?->toDateString());
                $p->catatan = null;
                $p->save();
                SppActivityLogger::logStatusChange($p, 'spp_validasi_lunas', $sebelum, $p->status, auth()->id());
                $lunas->push($p);
                $n++;
            }
        });

        SppNotifier::statusDiperbarui($lunas, 'lunas');

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

        $n = 0;
        $ditolak = collect();
        DB::transaction(function () use ($data, &$n, &$ditolak) {
            foreach ($data['ids'] as $id) {
                $p = SppPembayaran::where('uuid', $id)
                    ->whereIn('status', [SppPembayaran::STATUS_MENUNGGU, SppPembayaran::STATUS_TERVERIFIKASI])
                    ->lockForUpdate()
                    ->first();

                if (! $p) {
                    continue;
                }

                $sebelum = $p->status;
                $p->status = SppPembayaran::STATUS_DITOLAK;
                $p->catatan = $data['catatan'];
                $p->diverifikasi_oleh = auth()->id();
                $p->diverifikasi_pada = now();
                $p->save();
                SppActivityLogger::logStatusChange($p, 'spp_verifikasi_ditolak', $sebelum, $p->status, auth()->id());
                $ditolak->push($p);
                $n++;
            }
        });

        SppNotifier::statusDiperbarui($ditolak, 'ditolak');

        return back()->with('success', "{$n} bulan ditolak. Ortu/siswa dapat mengunggah ulang.");
    }

    /**
     * Upload laporan transaksi VA (rekening koran BCA, format .txt "R-5401") lalu
     * cocokkan dengan tagihan SPP via 6 digit belakang VA siswa — HANYA pratinjau,
     * belum ada yang ditulis ke DB. Bendahara meninjau daftar ini (bisa terima saran
     * otomatis apa adanya, atau ganti bulan manual per baris) sebelum submit ke
     * applyImportRekeningKoran(). Halaman ini sendiri isinya form besar berisi seluruh
     * data yg dibutuhkan applyImportRekeningKoran — tak perlu simpan file/state di sesi.
     */
    public function previewImportRekeningKoran(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:txt|max:2048',
        ], [
            'file.mimes' => 'File harus berupa .txt (laporan transaksi VA dari bank).',
        ]);

        $content = (string) file_get_contents($request->file('file')->getRealPath());
        $parsed  = RekeningKoranParserResolver::resolve($content);
        $transaksi = $parsed['transaksi'];

        if (empty($transaksi)) {
            return back()->with('error', 'Tidak ada baris transaksi yang terbaca dari file ini. Pastikan file laporan VA asli dari bank (BCA R-5401 atau Mandiri CSV).');
        }

        $preview = $this->spp->previewRekeningKoran($transaksi);

        return view('keuangan.import-rekening-koran-preview', [
            'preview'    => $preview,
            'bankParser' => $parsed['bank'],
        ]);
    }

    /** Terapkan baris-baris yang dicentang bendahara di halaman pratinjau import rekening koran. */
    public function applyImportRekeningKoran(Request $request)
    {
        $data = $request->validate([
            'baris'                     => 'required|array',
            'baris.*.terapkan'          => 'nullable',
            'baris.*.pembayaran_uuid'   => 'nullable|string',
            'baris.*.nominal'           => 'required|integer|min:0',
            'baris.*.tanggal_bayar'     => 'required|date',
        ]);

        $keputusan = collect($data['baris'])
            ->filter(fn ($b) => !empty($b['terapkan']) && !empty($b['pembayaran_uuid']))
            ->map(fn ($b) => [
                'pembayaran_uuid' => $b['pembayaran_uuid'],
                'nominal'         => $b['nominal'],
                'tanggal_bayar'   => $b['tanggal_bayar'],
            ])
            ->values()->all();

        if (empty($keputusan)) {
            return back()->with('error', 'Tidak ada baris yang dicentang untuk diterapkan.');
        }

        $hasil = $this->spp->applyRekeningKoran($keputusan, auth()->id());

        $berhasil = collect($hasil)->where('berhasil', true)->count();
        $dilewati = collect($hasil)->where('berhasil', false)->values();

        SppActivityLogger::logImport(
            $request->input('bank_parser', 'BCA'),
            $berhasil,
            $dilewati->count(),
            auth()->id(),
        );

        $msg = "{$berhasil} pembayaran ditandai LUNAS.";
        if ($dilewati->isNotEmpty()) {
            $n = $dilewati->count();
            $shown = $dilewati->take(5)->pluck('pesan')->implode(' ');
            $msg .= " {$n} baris dilewati: {$shown}";
            if ($n > 5) {
                $msg .= ' (dan ' . ($n - 5) . ' baris lainnya).';
            }
        }

        return redirect()->route('keuangan.verifikasi')->with($berhasil > 0 ? 'success' : 'error', $msg);
    }

    /** Halaman pengaturan bank/metode pembayaran. */
    public function bank(SppMutasiMatchingService $matching)
    {
        $ta = TahunAjaran::current();
        $rekonsiliasiAntrian = $matching->antrianValidasiBank($ta);

        return view('keuangan.bank', [
            'banks' => KeuanganBank::all(),
            'ta'    => $ta,
            'rekonsiliasiRingkas' => [
                'belum_cocok' => $rekonsiliasiAntrian->count(),
                'sudah_lunas' => SppPembayaran::where('tahun_ajaran', $ta)
                    ->where('status', SppPembayaran::STATUS_LUNAS)
                    ->count(),
            ],
            'rekonsiliasiAntrian' => $rekonsiliasiAntrian,
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
            'catatan_bendahara' => $p->catatan_bendahara,
        ];
    }
}
