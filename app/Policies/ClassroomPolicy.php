<?php

namespace App\Policies;

use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\Ngajar;
use App\Models\User;

/**
 * Deny-by-default. Admin penuh; guru kelola ruang kelas miliknya / yang diampu;
 * siswa anggota: lihat (setelah terbit) & kumpulkan tugas.
 */
class ClassroomPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // index discope per peran di controller
    }

    public function view(User $user, Classroom $classroom): bool
    {
        if ($user->isAdmin() || in_array($user->access, ['kepala', 'kurikulum'], true)) {
            return true;
        }
        if ($classroom->created_by === $user->uuid || $this->teachesSubject($user, $classroom) || $this->isWaliKelas($user, $classroom)) {
            return true;
        }
        // Siswa/ortu anggota hanya setelah terbit.
        return $classroom->isPublished() && $this->isMember($user, $classroom);
    }

    public function create(User $user): bool
    {
        return $user->isAdmin() || in_array($user->access, ['guru', 'kurikulum'], true);
    }

    public function update(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin() || $classroom->created_by === $user->uuid;
    }

    public function delete(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin() || $classroom->created_by === $user->uuid;
    }

    /** Memantau kelas (lihat tugas, materi, dan submission siswa, tanpa ubah nilai). */
    public function monitor(User $user, Classroom $classroom): bool
    {
        return $this->manage($user, $classroom) || $this->isWaliKelas($user, $classroom) || in_array($user->access, ['kepala', 'kurikulum'], true);
    }

    /** Kelola materi/tugas/penilaian - hanya guru pengampu mapel ini (sesuai jam ngajar). */
    public function manage(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin() || $classroom->created_by === $user->uuid || $this->teachesSubject($user, $classroom);
    }

    /** Siswa anggota mengumpulkan tugas. */
    public function submit(User $user, Classroom $classroom): bool
    {
        return $user->access === 'siswa' && $classroom->isPublished() && $this->isMember($user, $classroom);
    }

    /**
     * 4 cache di bawah ini SEMUA dikunci per user/guru (array bertingkat, bukan array datar
     * spt sebelumnya) — versi lama diam2 SALAH kalau dalam satu proses PHP yg sama dicek utk
     * lebih dari satu user (persis begini yg terjadi di test suite: user A dicek duluan,
     * cache-nya "membeku" utk sisa proses, user B setelahnya ikut dibaca pakai data A). Di
     * PHP-FPM produksi normal (1 user login per request) jarang kena, tapi tetap bug laten yg
     * salah kalau dites/dipakai lintas-user dalam satu request — jadi tetap dibenahi, bukan
     * cuma demi test.
     */
    private static array $memberCache = [];

    private function isMember(User $user, Classroom $classroom): bool
    {
        if (!isset(self::$memberCache[$user->uuid])) {
            self::$memberCache[$user->uuid] = ClassroomMember::where('user_id', $user->uuid)->pluck('classroom_id')->flip()->toArray();
        }
        return isset(self::$memberCache[$user->uuid][$classroom->uuid]);
    }

    /** Dipanggil dari ClassroomMember::booted() tiap baris dibuat/dihapus, supaya cache di
     * atas tak "membeku" salah kalau keanggotaan berubah di tengah request/proses yang sama
     * yg sudah sempat mengecek user ini sebelumnya (mis. perintah classroom:repair-membership). */
    public static function lupakanCacheAnggota(string $userUuid): void
    {
        unset(self::$memberCache[$userUuid]);
    }

    /** Guru pengampu mapel ini di kelas ini (id_guru + id_kelas + id_pelajaran). */
    private static array $teachingSubjectCache = [];

    private function teachesSubject(User $user, Classroom $classroom): bool
    {
        $guru = $user->guru;
        if (!$guru || !$classroom->id_kelas) {
            return false;
        }

        if (!isset(self::$teachingSubjectCache[$guru->uuid])) {
            self::$teachingSubjectCache[$guru->uuid] = Ngajar::where('id_guru', $guru->uuid)
                ->get(['id_kelas', 'id_pelajaran'])
                ->map(fn($n) => $n->id_kelas . '_' . $n->id_pelajaran)
                ->flip()
                ->toArray();
        }

        return isset(self::$teachingSubjectCache[$guru->uuid][$classroom->id_kelas . '_' . $classroom->id_pelajaran]);
    }

    private static array $waliKelasCache = [];

    private function isWaliKelas(User $user, Classroom $classroom): bool
    {
        $guru = $user->guru;
        if (!$guru || !$classroom->id_kelas) {
            return false;
        }

        if (!isset(self::$waliKelasCache[$guru->uuid])) {
            self::$waliKelasCache[$guru->uuid] = \App\Models\Walikelas::where('id_guru', $guru->uuid)->pluck('id_kelas')->flip()->toArray();
        }

        return isset(self::$waliKelasCache[$guru->uuid][$classroom->id_kelas]);
    }

    private static array $teachingKelasCache = [];

    /** Guru yang mengajar kelas ini (mapel apa pun) atau wali kelasnya. */
    private function teachesKelas(User $user, Classroom $classroom): bool
    {
        $guru = $user->guru;
        if (!$guru || !$classroom->id_kelas) {
            return false;
        }

        if (!isset(self::$teachingKelasCache[$guru->uuid])) {
            $ngajar = Ngajar::where('id_guru', $guru->uuid)->pluck('id_kelas');
            $wali = \App\Models\Walikelas::where('id_guru', $guru->uuid)->pluck('id_kelas');
            self::$teachingKelasCache[$guru->uuid] = $ngajar->concat($wali)->flip()->toArray();
        }

        return isset(self::$teachingKelasCache[$guru->uuid][$classroom->id_kelas]);
    }
}
