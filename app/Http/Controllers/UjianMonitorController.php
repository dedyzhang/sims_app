<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RetriesOnDbBusy;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianPelanggaran;
use App\Services\UjianKuncian;
use App\Services\UjianNilaiTransfer;
use App\Services\UjianRoster;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard pemantauan live guru/panitia saat ujian berlangsung — status tiap
 * siswa, sisa waktu, pelanggaran, dan dua aksi reset: buka-kunci (lanjut dari
 * titik terakhir) vs reset-ulang (soft-cancel, mulai dari nol dgn token sama).
 *
 * Menggunakan pendekatan Class-First: Pada ujian multi-kelas, tampilkan kartu ringkasan
 * per-kelas terlebih dahulu. Saat salah satu kelas dibuka, baru muat data pengerjaan
 * siswa kelas tersebut secara live agar tidak membebani performa server.
 */
class UjianMonitorController extends Controller
{
    use RetriesOnDbBusy;

    public function index(Request $request, Ujian $ujian)
    {
        return $this->retryOnDbBusy(function () use ($request, $ujian) {
            $this->authorize('monitor', $ujian);

            $ujianKelasList = $ujian->kelas()->with('kelas', 'guruPengampu')->get()
                ->sortBy(fn ($uk) => [$uk->kelas?->tingkat, $uk->kelas?->kelas])
                ->values();

            $kelasFilter = $request->string('kelas')->toString();

            // Jika hanya ada 1 kelas pada ujian ini, otomatis langsung buka kelas tersebut
            if (! $kelasFilter && $ujianKelasList->count() === 1) {
                $kelasFilter = $ujianKelasList->first()->id_kelas;
            }

            // TAMPILAN 1: PILIH KELAS (Ringkasan per-kelas agar server ringan & tidak meload ratusan siswa sekaligus)
            if (! $kelasFilter) {
                $idKelasList = $ujianKelasList->pluck('id_kelas')->filter()->all();
                $idUjianKelasList = $ujianKelasList->pluck('uuid')->all();

                $siswaCounts = Siswa::whereIn('id_kelas', $idKelasList)
                    ->selectRaw('id_kelas, count(*) as total')
                    ->groupBy('id_kelas')
                    ->pluck('total', 'id_kelas');

                $attemptStats = UjianAttempt::whereIn('id_ujian_kelas', $idUjianKelasList)
                    ->selectRaw('id_ujian_kelas, status, count(*) as total')
                    ->groupBy('id_ujian_kelas', 'status')
                    ->get()
                    ->groupBy('id_ujian_kelas');

                $terkunciCounts = UjianAttempt::whereIn('id_ujian_kelas', $idUjianKelasList)
                    ->where('dikunci', true)
                    ->selectRaw('id_ujian_kelas, count(*) as total')
                    ->groupBy('id_ujian_kelas')
                    ->pluck('total', 'id_ujian_kelas');

                $kelasSummary = $ujianKelasList->map(function ($uk) use ($siswaCounts, $attemptStats, $terkunciCounts) {
                    $totalSiswa = $siswaCounts->get($uk->id_kelas, 0);
                    $stats = $attemptStats->get($uk->uuid, collect())->keyBy('status');
                    $dinilai = $stats->get(UjianAttempt::STATUS_DINILAI)?->total ?? 0;
                    $submitted = $stats->get(UjianAttempt::STATUS_SUBMITTED)?->total ?? 0;
                    $inProgress = $stats->get(UjianAttempt::STATUS_IN_PROGRESS)?->total ?? 0;
                    $terkunci = $terkunciCounts->get($uk->uuid, 0);

                    $totalAttemptAktif = $dinilai + $submitted + $inProgress;
                    $belumMulai = max(0, $totalSiswa - $totalAttemptAktif);

                    return [
                        'ujianKelas'   => $uk,
                        'kelas'        => $uk->kelas,
                        'total_siswa'  => $totalSiswa,
                        'in_progress'  => $inProgress,
                        'selesai'      => $dinilai + $submitted,
                        'terkunci'     => $terkunci,
                        'belum_mulai'  => $belumMulai,
                    ];
                });

                return view('ujian.monitor.index', compact('ujian', 'ujianKelasList', 'kelasSummary', 'kelasFilter'));
            }

            // TAMPILAN 2: DAFTAR SISWA KELAS TERPILIH
            return view('ujian.monitor.index', compact('ujian', 'ujianKelasList', 'kelasFilter'));
        });
    }

    /**
     * Roster-driven (Fase 7 lanjutan) — SEMUA siswa kelas ter-assign tampil, bukan cuma yg
     * sudah mulai mengerjakan (siswa "Belum Mulai" dulu diam2 tak muncul sama sekali). Dukung
     * filter ?kelas=uuid utk mempersempit ke satu kelas pada ujian multi-kelas (PTS/PAS).
     */
    public function poll(Request $request, Ujian $ujian, UjianRoster $rosterService)
    {
        return $this->retryOnDbBusy(function () use ($request, $ujian, $rosterService) {
            $this->authorize('monitor', $ujian);

            $ujianKelasList = $ujian->kelas()->with('kelas')->get();
            $idKelasFilter = $request->string('kelas')->toString();
            $untukRoster = ($idKelasFilter && $idKelasFilter !== 'semua') ? $ujianKelasList->where('id_kelas', $idKelasFilter)->values() : $ujianKelasList;

            $roster = $rosterService->untukKelas($untukRoster);
            $attemptUuids = $roster->pluck('attempt.uuid')->filter();
            $pelanggaranGroup = UjianPelanggaran::whereIn('id_attempt', $attemptUuids)
                ->latest()
                ->get()
                ->groupBy('id_attempt')
                ->map(fn ($group) => [
                    'jumlah' => $group->count(),
                    'alasan' => $group->first()->tipeLabel(),
                    'waktu'  => $group->first()->created_at?->diffForHumans() ?? '',
                ]);

            $data = $roster->map(function (array $row) use ($pelanggaranGroup) {
                $a = $row['attempt'];
                $uk = $row['ujianKelas'];
                $pInfo = $a ? ($pelanggaranGroup[$a->uuid] ?? null) : null;

                return [
                    'attempt_uuid'       => $a?->uuid,
                    'nama'               => $row['siswa']->nama,
                    'kelas'              => trim(($uk?->kelas?->tingkat ?? '') . ($uk?->kelas?->kelas ?? '')),
                    'status'             => $a?->status ?? 'belum_mulai',
                    'status_label'       => $a?->statusLabel() ?? 'Belum Mulai',
                    'dikunci'            => $a?->isLocked() ?? false,
                    'batas_waktu_pada'   => $a?->batas_waktu_pada?->toIso8601String(),
                    'pelanggaran'        => (int) ($pInfo['jumlah'] ?? 0),
                    'alasan_pelanggaran' => $pInfo['alasan'] ?? null,
                ];
            })->values();

            $kelasOpsi = $ujianKelasList->map(fn ($uk) => [
                'uuid'  => $uk->id_kelas,
                'label' => trim(($uk->kelas?->tingkat ?? '') . ($uk->kelas?->kelas ?? '')),
            ])->unique('uuid')->sortBy('label')->values();

            return response()->json(['attempts' => $data, 'kelasOpsi' => $kelasOpsi]);
        });
    }

    public function resetLock(Request $request, Ujian $ujian, UjianAttempt $attempt, UjianKuncian $kuncian)
    {
        $this->authorize('resetLock', $ujian);
        abort_unless($attempt->ujianKelas->id_ujian === $ujian->uuid, 404);
        abort_unless($attempt->isLocked(), 422, 'Attempt ini tidak sedang terkunci.');

        $kuncian->bukaKunci($attempt, 'reset_oleh_guru');

        return back()->with('success', 'Kunci dibuka — siswa bisa melanjutkan dari titik terakhir, jawaban tersimpan tidak hilang.');
    }

    /**
     * "Reset Ulang" di Pemantauan Live — khusus attempt in_progress/terkunci yg butuh mulai
     * dari NOL krn kegagalan teknis total (BEDA dari "Buka Kembali Akses" di halaman Hasil,
     * lihat UjianController::bukaAksesSelesai(), yg membuka kembali attempt SUDAH SELESAI
     * dgn jawaban tetap tersimpan). Soft-cancel: attempt lama TETAP ada (status='dibatalkan')
     * utk audit, siswa lalu bisa start() lagi dgn token yg sama & dapat attempt baru (urutan
     * diacak ulang, jawaban lama tak ikut pindah). Kalau attempt ini nilainya SUDAH
     * tertransfer ke buku nilai, nilai itu ikut dihapus (UjianNilaiTransfer::revert())
     * supaya tak "nyantol" diam2 padahal sumbernya dibatalkan.
     */
    public function resetAttempt(Request $request, Ujian $ujian, UjianAttempt $attempt, UjianNilaiTransfer $nilaiTransfer)
    {
        $this->authorize('resetAttempt', $ujian);
        abort_unless($attempt->ujianKelas->id_ujian === $ujian->uuid, 404);
        abort_if($attempt->status === UjianAttempt::STATUS_DIBATALKAN, 422, 'Attempt ini sudah direset sebelumnya.');

        $nilaiIkutTerhapus = $attempt->status_transfer_nilai === 'berhasil';

        DB::transaction(function () use ($attempt, $nilaiTransfer, $nilaiIkutTerhapus) {
            if ($nilaiIkutTerhapus) {
                $nilaiTransfer->revert($attempt);
            }
            $attempt->update(['status' => UjianAttempt::STATUS_DIBATALKAN, 'dikunci' => false]);
            UjianPelanggaran::create(['id_attempt' => $attempt->uuid, 'id_siswa' => $attempt->id_siswa, 'tipe' => 'direset_admin']);
        });

        return back()->with('success', $nilaiIkutTerhapus
            ? 'Attempt direset & nilai yang sudah tertransfer ikut dihapus — siswa bisa memulai ujian ini lagi dari awal dengan token yang sama.'
            : 'Attempt direset — siswa bisa memulai ujian ini lagi dari awal dengan token yang sama.');
    }
}
