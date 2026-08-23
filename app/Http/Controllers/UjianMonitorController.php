<?php

namespace App\Http\Controllers;

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
 */
class UjianMonitorController extends Controller
{
    public function index(Request $request, Ujian $ujian)
    {
        $this->authorize('monitor', $ujian);
        $ujian->load('kelas.kelas');

        return view('ujian.monitor.index', compact('ujian'));
    }

    /**
     * Roster-driven (Fase 7 lanjutan) — SEMUA siswa kelas ter-assign tampil, bukan cuma yg
     * sudah mulai mengerjakan (siswa "Belum Mulai" dulu diam2 tak muncul sama sekali). Dukung
     * filter ?kelas=uuid utk mempersempit ke satu kelas pada ujian multi-kelas (PTS/PAS).
     */
    public function poll(Request $request, Ujian $ujian, UjianRoster $rosterService)
    {
        $this->authorize('monitor', $ujian);

        $ujianKelasList = $ujian->kelas()->with('kelas')->get();
        $idKelasFilter = $request->string('kelas')->toString();
        $untukRoster = $idKelasFilter ? $ujianKelasList->where('id_kelas', $idKelasFilter)->values() : $ujianKelasList;

        $roster = $rosterService->untukKelas($untukRoster);
        $attemptUuids = $roster->pluck('attempt.uuid')->filter();
        $pelanggaranCount = UjianPelanggaran::whereIn('id_attempt', $attemptUuids)
            ->selectRaw('id_attempt, count(*) as jumlah')->groupBy('id_attempt')->pluck('jumlah', 'id_attempt');

        $data = $roster->map(function (array $row) use ($pelanggaranCount) {
            $a = $row['attempt'];
            $uk = $row['ujianKelas'];

            return [
                'attempt_uuid'     => $a?->uuid,
                'nama'             => $row['siswa']->nama,
                'kelas'            => trim(($uk?->kelas?->tingkat ?? '') . ($uk?->kelas?->kelas ?? '')),
                'status'           => $a?->status ?? 'belum_mulai',
                'status_label'     => $a?->statusLabel() ?? 'Belum Mulai',
                'dikunci'          => $a?->isLocked() ?? false,
                'batas_waktu_pada' => $a?->batas_waktu_pada?->toIso8601String(),
                'pelanggaran'      => $a ? (int) ($pelanggaranCount[$a->uuid] ?? 0) : 0,
            ];
        })->values();

        $kelasOpsi = $ujianKelasList->map(fn ($uk) => [
            'uuid'  => $uk->id_kelas,
            'label' => trim(($uk->kelas?->tingkat ?? '') . ($uk->kelas?->kelas ?? '')),
        ])->unique('uuid')->sortBy('label')->values();

        return response()->json(['attempts' => $data, 'kelasOpsi' => $kelasOpsi]);
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
