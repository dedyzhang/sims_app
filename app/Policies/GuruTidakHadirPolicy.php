<?php

namespace App\Policies;

use App\Models\GuruTidakHadir;
use App\Models\JadwalPiket;
use App\Models\User;

/**
 * Otorisasi guru tidak hadir. Ditemukan otomatis oleh Laravel (App\Models\GuruTidakHadir →
 * App\Policies\GuruTidakHadirPolicy), tidak perlu didaftarkan di AppServiceProvider.
 */
class GuruTidakHadirPolicy
{
    private const AKSES_LIHAT = ['admin', 'superadmin', 'kepala', 'kurikulum'];

    /** Admin/kepala/kurikulum selalu boleh lihat; guru piket boleh lihat hari piketnya sendiri. */
    public function viewAny(User $user): bool
    {
        if (in_array($user->access, self::AKSES_LIHAT, true)) {
            return true;
        }

        $idGuru = $user->guru?->uuid;

        return $idGuru !== null && JadwalPiket::isPiketAktif($idGuru);
    }

    public function view(User $user, GuruTidakHadir $guruTidakHadir): bool
    {
        return $this->viewAny($user);
    }

    /** Input manual — hanya guru piket aktif hari ini (atau admin). */
    public function create(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $idGuru = $user->guru?->uuid;

        return $idGuru !== null && JadwalPiket::isPiketAktif($idGuru);
    }

    /** Guru mana pun boleh melapor ketidakhadirannya sendiri (di hari yang sama). */
    public function laporMandiri(User $user): bool
    {
        return $user->guru?->uuid !== null;
    }
}
