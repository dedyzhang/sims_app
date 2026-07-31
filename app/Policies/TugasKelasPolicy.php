<?php

namespace App\Policies;

use App\Models\JadwalPiket;
use App\Models\PenugasanPengganti;
use App\Models\TugasKelas;
use App\Models\User;

/**
 * Otorisasi tugas kelas. Ditemukan otomatis oleh Laravel (App\Models\TugasKelas →
 * App\Policies\TugasKelasPolicy), tidak perlu didaftarkan di AppServiceProvider.
 */
class TugasKelasPolicy
{
    private const AKSES_LIHAT = ['admin', 'superadmin', 'kepala', 'kurikulum'];

    public function viewAny(User $user): bool
    {
        if (in_array($user->access, self::AKSES_LIHAT, true)) {
            return true;
        }

        $idGuru = optional($user->guru)->uuid;

        return $idGuru !== null && JadwalPiket::isPiketAktif($idGuru);
    }

    /** Guru asli boleh upload hanya untuk slot jam kosong miliknya sendiri. */
    public function upload(User $user, PenugasanPengganti $penugasanPengganti): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $idGuru = optional($user->guru)->uuid;

        return $idGuru !== null && optional($penugasanPengganti->guruTidakHadir)->id_guru === $idGuru;
    }

    /** Titip manual & konfirmasi — guru piket aktif pada tanggal slot terkait (atau admin). */
    public function manage(User $user, string $tanggal): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $idGuru = optional($user->guru)->uuid;

        return $idGuru !== null && JadwalPiket::isPiketAktif($idGuru, $tanggal);
    }

    /** Unduh file — admin/kepala/kurikulum, guru piket hari itu, atau guru pemilik slot. */
    public function view(User $user, TugasKelas $tugasKelas): bool
    {
        if (in_array($user->access, self::AKSES_LIHAT, true)) {
            return true;
        }

        $idGuru = optional($user->guru)->uuid;
        if ($idGuru === null) {
            return false;
        }

        $absen = optional($tugasKelas->penugasanPengganti)->guruTidakHadir;
        $tanggal = $absen && $absen->tanggal ? $absen->tanggal->toDateString() : null;

        if ($tanggal && JadwalPiket::isPiketAktif($idGuru, $tanggal)) {
            return true;
        }

        return optional($absen)->id_guru === $idGuru;
    }

    public function delete(User $user, TugasKelas $tugasKelas): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $idGuru = optional($user->guru)->uuid;
        if ($idGuru === null) {
            return false;
        }

        $absen = optional($tugasKelas->penugasanPengganti)->guruTidakHadir;

        if ($tugasKelas->dibuat_oleh === $user->uuid || optional($absen)->id_guru === $idGuru) {
            return true;
        }

        $tanggal = $absen && $absen->tanggal ? $absen->tanggal->toDateString() : null;

        if ($tanggal && JadwalPiket::isPiketAktif($idGuru, $tanggal)) {
            return true;
        }

        return false;
    }
}
