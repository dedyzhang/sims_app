<?php

namespace App\Policies;

use App\Models\Ngajar;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianKelas;
use App\Models\User;

/**
 * Ujian modul terpisah dari Ruang Kelas/Arena Belajar — akses guru ditentukan
 * langsung dari Ngajar (guru+mapel+kelas), bukan dari keanggotaan Classroom.
 */
class UjianPolicy
{
    public function create(User $user): bool
    {
        // $user->guru (bukan $user->access==='guru') supaya staf dual-role — kurikulum/
        // kesiswaan/sapras yg JUGA punya penugasan mengajar (Ngajar) via profil Guru mereka —
        // ikut bisa buat ujian utk mapel yg mereka ajar, sama spt pola Buku Guru di sidebar.
        return $user->isAdmin() || $user->canAccess('manage_ujian') || $user->guru !== null;
    }

    public function manage(User $user, Ujian $ujian): bool
    {
        if ($user->isAdmin() || $user->canAccess('manage_ujian')) {
            return true;
        }
        if ($ujian->created_by === $user->uuid) {
            return true;
        }

        $guru = $user->guru;
        if (!$guru) {
            return false;
        }

        $idKelas = $ujian->relationLoaded('kelas')
            ? $ujian->kelas->pluck('id_kelas')
            : $ujian->kelas()->pluck('id_kelas');

        if ($idKelas->isEmpty()) {
            return false;
        }

        return Ngajar::where('id_guru', $guru->uuid)
            ->where('id_pelajaran', $ujian->id_pelajaran)
            ->whereIn('id_kelas', $idKelas)
            ->exists();
    }

    public function view(User $user, Ujian $ujian): bool
    {
        if ($this->manage($user, $ujian)) {
            return true;
        }
        if (!$ujian->isPublished() && !$ujian->isClosed()) {
            return false;
        }

        $siswa = $user->siswa;
        if (!$siswa) {
            return false;
        }

        $idKelas = $ujian->kelas()->pluck('id_kelas');
        return $idKelas->contains($siswa->id_kelas);
    }

    public function take(User $user, UjianKelas $ujianKelas): bool
    {
        if ($user->access !== 'siswa') {
            return false;
        }
        $siswa = Siswa::where('id_login', $user->uuid)->first();
        if (!$siswa || $siswa->id_kelas !== $ujianKelas->id_kelas) {
            return false;
        }
        if (!$ujianKelas->isOpenNow()) {
            return false;
        }

        // Defense-in-depth: gate ramah utamanya di UjianSiswaController::gate(), ini
        // jaga-jaga kalau ada yg coba POST token langsung ke start() tanpa lewat gate().
        $ujian = $ujianKelas->ujian;
        if ($ujian?->wajibScanQr() && !$ujian->paket->sudahDicekSiswa($siswa)) {
            return false;
        }

        return true;
    }

    public function gradeEssay(User $user, Ujian $ujian): bool
    {
        return $this->manage($user, $ujian);
    }

    /**
     * Beda dgn manage() — ini per-KELAS, bukan per-ujian: kalau satu ujian punya beberapa
     * kelas yg diajar guru BERBEDA (mis. PTS lintas kelas satu tingkat), tiap guru cuma
     * berwenang atas kelas yg dia jadi pengampu-nya, bukan seluruh kelas ujian ini. Dipakai
     * utk membatasi Nilai Esai & unduh Analisis Excel supaya guru tak bisa lihat/nilai kelas
     * rekan mengajarnya di ujian yg sama.
     */
    public function mengampuiKelas(User $user, UjianKelas $ujianKelas): bool
    {
        $ujian = $ujianKelas->ujian ?? $ujianKelas->ujian()->first();
        if (!$ujian) {
            return false;
        }
        if ($user->isAdmin() || $user->canAccess('manage_ujian') || $ujian->created_by === $user->uuid) {
            return true;
        }

        $guru = $user->guru;
        if (!$guru) {
            return false;
        }

        if ($ujianKelas->id_guru_pengampu) {
            return $ujianKelas->id_guru_pengampu === $guru->uuid;
        }

        // Baris lama (dibuat sebelum fitur pengampu ada) belum punya pengampu tersimpan —
        // jatuh balik ke cek Ngajar lama supaya guru yg memang mengajar tetap bisa akses.
        return Ngajar::where('id_guru', $guru->uuid)
            ->where('id_pelajaran', $ujian->id_pelajaran)
            ->where('id_kelas', $ujianKelas->id_kelas)
            ->exists();
    }

    public function resetLock(User $user, Ujian $ujian): bool
    {
        return $this->manage($user, $ujian);
    }

    public function resetAttempt(User $user, Ujian $ujian): bool
    {
        return $this->manage($user, $ujian);
    }

    public function monitor(User $user, Ujian $ujian): bool
    {
        return $this->manage($user, $ujian) || in_array($user->access, ['kepala', 'kurikulum'], true);
    }

    public function viewAttempt(User $user, UjianAttempt $attempt): bool
    {
        if ($attempt->id_siswa === $user->uuid) {
            return true;
        }
        $ujian = $attempt->ujianKelas?->ujian;

        return $ujian ? $this->manage($user, $ujian) : false;
    }
}
