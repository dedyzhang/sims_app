<?php

namespace App\Policies;

use App\Models\ForumTopic;
use App\Models\Ngajar;
use App\Models\User;
use App\Models\Walikelas;
use App\Support\Forum;

/**
 * Deny-by-default. SEMUA keputusan berbasis User::canForum() (matriks yang dapat
 * diatur admin), BUKAN nama role. Lingkup visibilitas dihitung di canViewScope().
 */
class ForumTopicPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canForum('forum.view.all') || $user->canForum('forum.view.scope');
    }

    public function view(User $user, ForumTopic $topic): bool
    {
        if ($user->canForum('forum.view.all')) {
            return true;
        }
        if (!$user->canForum('forum.view.scope')) {
            return false;
        }
        return $this->canViewScope($user, $topic);
    }

    public function create(User $user): bool
    {
        return $user->canForum('forum.topic.create');
    }

    public function reply(User $user, ForumTopic $topic): bool
    {
        return $user->canForum('forum.comment.create')
            && !$topic->is_locked
            && $this->view($user, $topic);
    }

    public function update(User $user, ForumTopic $topic): bool
    {
        return $user->canForum('forum.moderate') || $topic->created_by === $user->uuid;
    }

    public function delete(User $user, ForumTopic $topic): bool
    {
        return $user->canForum('forum.moderate') || $topic->created_by === $user->uuid;
    }

    /** Pin / lock / hapus orang lain. */
    public function moderate(User $user, ForumTopic $topic): bool
    {
        return $user->canForum('forum.moderate') && $this->view($user, $topic);
    }

    public function markBestAnswer(User $user, ForumTopic $topic): bool
    {
        return ($user->canForum('forum.moderate') || $topic->created_by === $user->uuid)
            && $this->view($user, $topic);
    }

    public function announce(User $user): bool
    {
        return $user->canForum('forum.announce');
    }

    public function manageAccess(User $user): bool
    {
        return $user->canForum('forum.manage_access');
    }

    /**
     * Lingkup visibilitas saat hanya punya forum.view.scope.
     * - guru   : kelas yang diampu / diwalikelasi, topik buatan sendiri, atau forum umum tanpa kelas.
     * - siswa  : kelas sendiri atau forum umum tanpa kelas.
     * - ortu   : kelas anak DAN audience 'termasuk_ortu' (sesuai spec, ketat).
     * - staf   : kategori lingkupnya (Forum::categoryScope), mis. Waka Sarpras → sarpras.
     * - lain   : hanya topik buatan sendiri.
     */
    private function canViewScope(User $user, ForumTopic $topic): bool
    {
        // Selalu boleh melihat topik buatan sendiri.
        if ($topic->created_by === $user->uuid) {
            return true;
        }

        switch ($user->access) {
            case 'siswa':
                $kelas = $user->siswa?->id_kelas;
                return ($topic->id_kelas && $topic->id_kelas === $kelas) || $topic->id_kelas === null;

            case 'orangtua':
                return $topic->id_kelas !== null
                    && $topic->audience === 'termasuk_ortu'
                    && in_array($topic->id_kelas, $user->childrenClassroomIds(), true);

            case 'guru':
                $kelasIds = $this->guruKelasIds($user);
                return ($topic->id_kelas && in_array($topic->id_kelas, $kelasIds, true))
                    || $topic->id_kelas === null;

            default:
                // Staf kategori (mis. Waka Sarpras/Kesiswaan/Kurikulum tanpa view.all).
                $cats = Forum::categoryScope((string) $user->access);
                if ($cats !== null) {
                    return in_array($topic->category, $cats, true);
                }
                return false;
        }
    }

    /** id_kelas yang diampu guru (mengajar) + diwalikelasi. */
    private function guruKelasIds(User $user): array
    {
        $guru = $user->guru;
        if (!$guru) {
            return [];
        }
        $ajar = Ngajar::where('id_guru', $guru->uuid)->pluck('id_kelas')->all();
        $wali = Walikelas::where('id_guru', $guru->uuid)->pluck('id_kelas')->all();
        return array_values(array_unique(array_filter(array_merge($ajar, $wali))));
    }
}
