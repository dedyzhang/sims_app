<?php

namespace App\Support;

use App\Models\Absensi;
use App\Models\Siswa;
use App\Notifications\StudentAttendanceRecorded;
use Illuminate\Support\Facades\Log;

class AttendanceParentNotifier
{
    /**
     * Kirim notifikasi ke orang tua saat status absensi siswa berubah
     * (hadir / izin / sakit / alpa). Diam jika status sama (anti-spam).
     *
     * $siswa opsional: kalau pemanggil SUDAH punya Siswa (dgn relasi kelas+orangtua.user
     * ter-eager-load) dari query lain, teruskan di sini supaya tak query ulang per
     * pemanggilan — penting utk pemanggil yg memberi notifikasi ke BANYAK siswa sekaligus
     * (mis. AbsensiController::store() utk satu kelas), krn tanpa ini tiap panggilan query
     * Siswa::find() sendiri (N query utk N siswa).
     */
    public static function notifyIfStatusChanged(?string $previousStatus, Absensi $absensi, ?Siswa $siswa = null): void
    {
        if ($previousStatus === $absensi->status) {
            return;
        }

        self::notify($absensi, $siswa);
    }

    public static function notify(Absensi $absensi, ?Siswa $siswa = null): void
    {
        $siswa = $siswa ?? Siswa::with(['kelas', 'orangtua.user'])->find($absensi->id_siswa);
        $parentUser = $siswa?->orangtua?->user;

        if (! $siswa) {
            return;
        }

        if (! $parentUser) {
            Log::warning('Absensi ortu: siswa belum punya akun orang tua terhubung', [
                'siswa_id' => $siswa->uuid,
                'siswa_nama' => $siswa->nama,
                'tanggal' => $absensi->tanggal?->format('Y-m-d'),
                'status' => $absensi->status,
            ]);

            return;
        }

        $parentUser->notify(new StudentAttendanceRecorded($siswa, $absensi));
    }
}
