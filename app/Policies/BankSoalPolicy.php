<?php

namespace App\Policies;

use App\Models\BankSoal;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\User;

/**
 * Bank Soal dibagi rata per-mapel (bukan per-guru) — sama spt kolaborasi soal Ujian:
 * guru manapun yg mengajar mapel ini (via Ngajar) boleh kelola bank soalnya bersama.
 */
class BankSoalPolicy
{
    public function create(User $user): bool
    {
        // $user->guru (bukan $user->access==='guru') — sama alasan spt UjianPolicy::create():
        // staf dual-role (kurikulum/kesiswaan/sapras) yg juga mengajar tetap bisa kelola bank
        // soal mapel yg diajarnya.
        return $user->isAdmin() || $user->canAccess('manage_ujian') || $user->guru !== null;
    }

    public function viewPelajaran(User $user, string $idPelajaran): bool
    {
        if ($user->isAdmin() || $user->canAccess('manage_ujian')) {
            return true;
        }

        $guru = $user->guru;
        if (!$guru) {
            return false;
        }

        return Ngajar::where('id_guru', $guru->uuid)->where('id_pelajaran', $idPelajaran)->exists();
    }

    public function manage(User $user, BankSoal $soal): bool
    {
        return $this->viewPelajaran($user, $soal->id_pelajaran);
    }
}
