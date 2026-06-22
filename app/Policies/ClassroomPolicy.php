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

    private function isMember(User $user, Classroom $classroom): bool
    {
        return ClassroomMember::where('classroom_id', $classroom->uuid)->where('user_id', $user->uuid)->exists();
    }

    /** Guru pengampu mapel ini di kelas ini (id_guru + id_kelas + id_pelajaran). */
    private function teachesSubject(User $user, Classroom $classroom): bool
    {
        $guru = $user->guru;
        if (!$guru || !$classroom->id_kelas) {
            return false;
        }
        return Ngajar::where('id_guru', $guru->uuid)
            ->where('id_kelas', $classroom->id_kelas)
            ->where('id_pelajaran', $classroom->id_pelajaran)
            ->exists();
    }

    /** Guru yang mengajar kelas ini (mapel apa pun) atau wali kelasnya. */
    private function teachesKelas(User $user, Classroom $classroom): bool
    {
        $guru = $user->guru;
        if (!$guru || !$classroom->id_kelas) {
            return false;
        }
        return Ngajar::where('id_guru', $guru->uuid)->where('id_kelas', $classroom->id_kelas)->exists()
            || \App\Models\Walikelas::where('id_guru', $guru->uuid)->where('id_kelas', $classroom->id_kelas)->exists();
    }
}
