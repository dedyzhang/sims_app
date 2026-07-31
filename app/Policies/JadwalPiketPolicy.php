<?php

namespace App\Policies;

use App\Models\JadwalPiket;
use App\Models\User;

/**
 * Otorisasi rotasi piket. Ditemukan otomatis oleh Laravel (App\Models\JadwalPiket →
 * App\Policies\JadwalPiketPolicy), tidak perlu didaftarkan di AppServiceProvider —
 * sama seperti GrupChatPolicy/GameQuizPolicy.
 */
class JadwalPiketPolicy
{
    /** Kalender piket boleh dilihat semua user yang login. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, JadwalPiket $jadwalPiket): bool
    {
        return true;
    }

    /** Susun/ubah/hapus/tukar rotasi — sesuai PRD Fase 1, admin yang mengatur rotasi. */
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }
}
