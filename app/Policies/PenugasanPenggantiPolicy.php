<?php

namespace App\Policies;

use App\Models\JadwalPiket;
use App\Models\PenugasanPengganti;
use App\Models\User;

/**
 * Otorisasi penugasan pengganti. Ditemukan otomatis oleh Laravel (App\Models\PenugasanPengganti →
 * App\Policies\PenugasanPenggantiPolicy), tidak perlu didaftarkan di AppServiceProvider.
 */
class PenugasanPenggantiPolicy
{
    private const AKSES_LIHAT = ['admin', 'superadmin', 'kepala', 'kurikulum'];

    public function viewAny(User $user): bool
    {
        if (in_array($user->access, self::AKSES_LIHAT, true)) {
            return true;
        }

        $idGuru = $user->guru?->uuid;

        return $idGuru !== null && JadwalPiket::isPiketAktif($idGuru);
    }

    public function view(User $user, PenugasanPengganti $penugasanPengganti): bool
    {
        return $this->viewAny($user);
    }

    /**
     * Assign/ambil-alih/mark-selesai — hanya admin atau guru piket AKTIF PADA TANGGAL slot
     * terkait (bukan hanya "piket hari ini"), supaya guru piket tetap bisa beres-beres slot
     * hari piketnya sendiri walau baru dibuka keesokan harinya.
     */
    public function manage(User $user, string $tanggal): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $idGuru = $user->guru?->uuid;

        return $idGuru !== null && JadwalPiket::isPiketAktif($idGuru, $tanggal);
    }
}
