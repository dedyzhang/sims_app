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
        if ($classroom->created_by === $user->uuid || $this->teachesSubject($user, $classroom)) {
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

    /** Kelola materi/tugas/penilaian — hanya guru pengampu mapel ini (sesuai jam ngajar). */
    public function manage(User $user, Classroom $classroom): bool
    {
        return $user->isAdmin() || $classroom->created_by === $user->uuid || $this->teachesSubject($user, $classroom);
    }

    /** Siswa anggota mengumpulkan tugas. */
    public function submit(User $user, Classroom $classroom): bool
    {
        return $user->access === 'siswa' && $classroom->isPublished() && $this->isMember($user, $classroom);
    }

    private static ?array $memberCache = null;

    private function isMember(User $user, Classroom $classroom): bool
    {
        if (self::$memberCache === null) {
            self::$memberCache = ClassroomMember::where('user_id', $user->uuid)->pluck('classroom_id')->flip()->toArray();
        }
        return isset(self::$memberCache[$classroom->uuid]);
    }

    /** Guru pengampu mapel ini di kelas ini (id_guru + id_kelas + id_pelajaran). */
    private static ?array $teachingSubjectCache = null;

    private function teachesSubject(User $user, Classroom $classroom): bool
    {
        $guru = $user->guru;
        if (!$guru || !$classroom->id_kelas) {
            return false;
        }

        if (self::$teachingSubjectCache === null) {
            self::$teachingSubjectCache = Ngajar::where('id_guru', $guru->uuid)
                ->get(['id_kelas', 'id_pelajaran'])
                ->map(fn($n) => $n->id_kelas . '_' . $n->id_pelajaran)
                ->flip()
                ->toArray();
        }

        return isset(self::$teachingSubjectCache[$classroom->id_kelas . '_' . $classroom->id_pelajaran]);
    }

    private static ?array $teachingKelasCache = null;

    /** Guru yang mengajar kelas ini (mapel apa pun) atau wali kelasnya. */
    private function teachesKelas(User $user, Classroom $classroom): bool
    {
        $guru = $user->guru;
        if (!$guru || !$classroom->id_kelas) {
            return false;
        }

        if (self::$teachingKelasCache === null) {
            $ngajar = Ngajar::where('id_guru', $guru->uuid)->pluck('id_kelas');
            $wali = \App\Models\Walikelas::where('id_guru', $guru->uuid)->pluck('id_kelas');
            self::$teachingKelasCache = $ngajar->concat($wali)->flip()->toArray();
        }

        return isset(self::$teachingKelasCache[$classroom->id_kelas]);
    }
}
