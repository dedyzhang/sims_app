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

    /*
    | Memo per-request. WAJIB di-key per user: static ini hidup selama proses PHP,
    | jadi tanpa key-nya pengecekan pertama akan "mengunci" jawaban untuk SEMUA user
    | berikutnya di proses yang sama — di worker panjang-umur (Octane/queue) itu
    | berarti user A memakai keanggotaan/jadwal ngajar user B.
    |
    | @var array<string, array<string, int>>
    */
    private static array $memberCache = [];

    /**
     * Buang memo keanggotaan (satu user, atau semuanya). Dipanggil dari model event
     * ClassroomMember: enrolment bisa berubah DI TENGAH request/proses yang sama
     * (auto-enroll saat pindah kelas, set kelas massal, perintah repair), dan memo
     * basi membuat siswa yang baru didaftarkan tetap ditolak 403.
     */
    public static function forgetMemberMemo(?string $userId = null): void
    {
        if ($userId === null) {
            self::$memberCache = [];

            return;
        }

        unset(self::$memberCache[$userId]);
    }

    private function isMember(User $user, Classroom $classroom): bool
    {
        $key = (string) $user->uuid;
        if (! isset(self::$memberCache[$key])) {
            self::$memberCache[$key] = ClassroomMember::where('user_id', $user->uuid)->pluck('classroom_id')->flip()->toArray();
        }

        return isset(self::$memberCache[$key][$classroom->uuid]);
    }

    /** Guru pengampu mapel ini di kelas ini (id_guru + id_kelas + id_pelajaran). */
    /** @var array<string, array<string, int>> — lihat catatan di $memberCache. */
    private static array $teachingSubjectCache = [];

    private function teachesSubject(User $user, Classroom $classroom): bool
    {
        $guru = $user->guru;
        if (!$guru || !$classroom->id_kelas) {
            return false;
        }

        $key = (string) $guru->uuid;
        if (! isset(self::$teachingSubjectCache[$key])) {
            self::$teachingSubjectCache[$key] = Ngajar::where('id_guru', $guru->uuid)
                ->get(['id_kelas', 'id_pelajaran'])
                ->map(fn($n) => $n->id_kelas . '_' . $n->id_pelajaran)
                ->flip()
                ->toArray();
        }

        return isset(self::$teachingSubjectCache[$key][$classroom->id_kelas . '_' . $classroom->id_pelajaran]);
    }

    /** @var array<string, array<string, int>> — lihat catatan di $memberCache. */
    private static array $waliKelasCache = [];

    private function isWaliKelas(User $user, Classroom $classroom): bool
    {
        $guru = $user->guru;
        if (!$guru || !$classroom->id_kelas) {
            return false;
        }

        $key = (string) $guru->uuid;
        if (! isset(self::$waliKelasCache[$key])) {
            self::$waliKelasCache[$key] = \App\Models\Walikelas::where('id_guru', $guru->uuid)->pluck('id_kelas')->flip()->toArray();
        }

        return isset(self::$waliKelasCache[$key][$classroom->id_kelas]);
    }

    /** @var array<string, array<string, int>> — lihat catatan di $memberCache. */
    private static array $teachingKelasCache = [];

    /** Guru yang mengajar kelas ini (mapel apa pun) atau wali kelasnya. */
    private function teachesKelas(User $user, Classroom $classroom): bool
    {
        $guru = $user->guru;
        if (!$guru || !$classroom->id_kelas) {
            return false;
        }

        $key = (string) $guru->uuid;
        if (! isset(self::$teachingKelasCache[$key])) {
            $ngajar = Ngajar::where('id_guru', $guru->uuid)->pluck('id_kelas');
            $wali = \App\Models\Walikelas::where('id_guru', $guru->uuid)->pluck('id_kelas');
            self::$teachingKelasCache[$key] = $ngajar->concat($wali)->flip()->toArray();
        }

        return isset(self::$teachingKelasCache[$key][$classroom->id_kelas]);
    }
}
