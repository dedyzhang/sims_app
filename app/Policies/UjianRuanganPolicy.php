<?php

namespace App\Policies;

use App\Models\UjianRuangan;
use App\Models\User;

/**
 * Otorisasi Ruangan Ujian — SENGAJA terpisah dari UjianPolicy::manage() (yg
 * mengharuskan jadi guru pengampu mapel via Ngajar): siapa pun guru yg secara
 * FISIK ada di ruangan (dibuktikan lewat scan QR ruangan, lihat
 * UjianRuanganScanController) boleh mengawasi, tak peduli dia mengajar mapel
 * yg diujikan atau tidak — TIDAK ADA penugasan pengawas tersimpan lagi.
 */
class UjianRuanganPolicy
{
    /** Kelola ruangan (CRUD, atur peserta) — setara "admin/pengelola paket ujian", BUKAN per-mapel. */
    public function kelola(User $user, UjianRuangan $ruangan): bool
    {
        if ($user->isAdmin() || $user->canAccess('manage_ujian')) {
            return true;
        }

        return $ruangan->paket?->created_by === $user->uuid;
    }

    /**
     * Masuk halaman monitor ruangan (lihat status siswa, reset kunci, isi daftar
     * hadir/berita acara) — pengelola SELALU boleh; selain itu GURU MANA PUN
     * boleh, ASAL ruangan ini punya ujian dijadwalkan (UjianJadwal) HARI INI.
     * Bukti "ada di ruangan" = tahu URL scan-nya (QR fisik tertempel di ruangan),
     * bukan lagi lewat tabel penugasan.
     */
    public function awasi(User $user, UjianRuangan $ruangan): bool
    {
        if ($this->kelola($user, $ruangan)) {
            return true;
        }

        return $user->guru && $ruangan->adaJadwalPada();
    }
}
