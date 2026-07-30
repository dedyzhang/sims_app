<?php

namespace App\Support;

use App\Models\ChatbotConversation;
use App\Models\Classroom;
use App\Models\ClassroomMember;
use App\Models\GrupChatMember;
use App\Models\Orangtua;
use App\Models\Pengumuman;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Menentukan apakah notifikasi boleh ditampilkan/dikirim ke user tertentu.
 * Lapisan pertahanan agar notifikasi privat (mis. chat siswa↔admin) tidak bocor ke peran lain.
 */
class NotificationGate
{
    /**
     * Tipe yang mustahil relevan untuk peran user — difilter di SQL agar tidak
     * memenuhi window prefetch / mengunci unreadCount.
     *
     * @return list<string>
     */
    public static function excludedTypesFor(User $user): array
    {
        $excluded = [];

        if (! $user->isAdmin()) {
            $excluded[] = 'chatbot_inbox';
        }

        if ($user->isAdmin()) {
            $excluded[] = 'chatbot_admin_reply';
        }

        if ($user->access !== 'orangtua') {
            $excluded[] = 'absensi_siswa';
        }

        if (! in_array($user->access, ['kepala', 'admin', 'superadmin'], true)) {
            $excluded[] = 'presensi_terlambat';
            $excluded[] = 'presensi_izin_pulang';
        }

        return $excluded;
    }

    /**
     * Prefetch data yang dibutuhkan gate untuk sekumpulan notifikasi (hindari N+1).
     *
     * @param  Collection<int, \Illuminate\Notifications\DatabaseNotification>  $notifications
     * @return array{
     *   pengumuman: array<string, Pengumuman>,
     *   conversations: array<string, string>,
     *   classrooms: array<string, Classroom>,
     *   classroom_member_ids: array<string, true>,
     *   parent_siswa_ids: array<string, true>,
     *   grup_chat_member_ids: array<string, true>
     * }
     */
    public static function preload(User $user, Collection $notifications): array
    {
        $pengumumanIds = [];
        $conversationIds = [];
        $classroomIds = [];
        $siswaIds = [];
        $grupChatIds = [];

        foreach ($notifications as $n) {
            $data = (array) ($n->data ?? []);
            $type = (string) ($data['type'] ?? '');

            if ($type === 'pengumuman' && is_string($data['pengumuman_id'] ?? null)) {
                $pengumumanIds[] = $data['pengumuman_id'];
            }
            if ($type === 'chatbot_admin_reply' && is_string($data['conversation_id'] ?? null)) {
                $conversationIds[] = $data['conversation_id'];
            }
            if ($type === 'arena_live' && is_string($data['classroom_id'] ?? null)) {
                $classroomIds[] = $data['classroom_id'];
            }
            if ($type === 'absensi_siswa' && is_string($data['siswa_id'] ?? null)) {
                $siswaIds[] = $data['siswa_id'];
            }
            if ($type === 'grup_chat_digest') {
                foreach ((array) ($data['groups'] ?? []) as $g) {
                    if (is_string($g['grup_id'] ?? null)) {
                        $grupChatIds[] = $g['grup_id'];
                    }
                }
            }
        }

        $pengumuman = $pengumumanIds === []
            ? []
            : Pengumuman::query()->whereIn('uuid', array_unique($pengumumanIds))->get()->keyBy('uuid')->all();

        // map conversation_id => user_id pemilik
        $conversations = [];
        if ($conversationIds !== []) {
            $conversations = ChatbotConversation::query()
                ->whereIn('id', array_unique($conversationIds))
                ->pluck('user_id', 'id')
                ->all();
        }

        $classrooms = $classroomIds === []
            ? []
            : Classroom::query()->whereIn('uuid', array_unique($classroomIds))->get()->keyBy('uuid')->all();

        $memberIds = [];
        if ($classroomIds !== []) {
            $memberIds = ClassroomMember::query()
                ->whereIn('classroom_id', array_unique($classroomIds))
                ->where('user_id', $user->getKey())
                ->pluck('classroom_id')
                ->flip()
                ->map(fn () => true)
                ->all();
        }

        $parentSiswaIds = [];
        if ($user->access === 'orangtua' && $siswaIds !== []) {
            $parentSiswaIds = Orangtua::query()
                ->where('id_login', $user->getKey())
                ->whereIn('id_siswa', array_unique($siswaIds))
                ->pluck('id_siswa')
                ->flip()
                ->map(fn () => true)
                ->all();
        }

        $grupChatMemberIds = [];
        if ($grupChatIds !== []) {
            $grupChatMemberIds = GrupChatMember::query()
                ->whereIn('grup_id', array_unique($grupChatIds))
                ->where('user_id', $user->getKey())
                ->whereNull('left_at')
                ->pluck('grup_id')
                ->flip()
                ->map(fn () => true)
                ->all();
        }

        return [
            'pengumuman' => $pengumuman,
            'conversations' => $conversations,
            'classrooms' => $classrooms,
            'classroom_member_ids' => $memberIds,
            'parent_siswa_ids' => $parentSiswaIds,
            'grup_chat_member_ids' => $grupChatMemberIds,
        ];
    }

    /** @param array<string,mixed> $data */
    public static function userCanView(User $user, array $data, ?array $preload = null): bool
    {
        $type = (string) ($data['type'] ?? '');

        return match ($type) {
            'chatbot_inbox' => $user->isAdmin(),
            'chatbot_admin_reply' => self::ownsChatbotConversation($user, $data['conversation_id'] ?? null, $preload),
            'pengumuman' => self::canViewPengumuman($user, $data['pengumuman_id'] ?? null, $preload),
            'absensi_siswa' => self::canViewAbsensiSiswa($user, $data['siswa_id'] ?? null, $preload),
            'arena_live' => self::isClassroomMember($user, $data['classroom_id'] ?? null, $preload),
            'grup_chat_digest' => self::isGrupChatMemberOfAny($user, $data['groups'] ?? [], $preload),
            'sarpras_kerusakan', 'sarpras_pemeliharaan' => self::canViewSarpras($user),
            'presensi_terlambat', 'presensi_izin_pulang' => in_array($user->access, ['kepala', 'admin', 'superadmin'], true),
            default => self::canViewByUrl($user, $data),
        };
    }

    private static function ownsChatbotConversation(User $user, mixed $conversationId, ?array $preload): bool
    {
        if (! is_string($conversationId) || $conversationId === '') {
            return false;
        }

        if ($user->isAdmin()) {
            return false;
        }

        if ($preload !== null) {
            $ownerId = $preload['conversations'][$conversationId] ?? null;

            return $ownerId !== null && (string) $ownerId === (string) $user->getKey();
        }

        return ChatbotConversation::query()
            ->whereKey($conversationId)
            ->where('user_id', $user->getKey())
            ->exists();
    }

    private static function canViewPengumuman(User $user, mixed $pengumumanId, ?array $preload): bool
    {
        if (! is_string($pengumumanId) || $pengumumanId === '') {
            return false;
        }

        $pengumuman = $preload !== null
            ? ($preload['pengumuman'][$pengumumanId] ?? null)
            : Pengumuman::query()->find($pengumumanId);

        return $pengumuman ? $pengumuman->menyasar($user) : false;
    }

    private static function canViewAbsensiSiswa(User $user, mixed $siswaId, ?array $preload): bool
    {
        if ($user->access !== 'orangtua') {
            return false;
        }

        if (! is_string($siswaId) || $siswaId === '') {
            return false;
        }

        if ($preload !== null) {
            return isset($preload['parent_siswa_ids'][$siswaId]);
        }

        return Orangtua::query()
            ->where('id_login', $user->getKey())
            ->where('id_siswa', $siswaId)
            ->exists();
    }

    private static function isClassroomMember(User $user, mixed $classroomId, ?array $preload): bool
    {
        if (! is_string($classroomId) || $classroomId === '') {
            return false;
        }

        $classroom = $preload !== null
            ? ($preload['classrooms'][$classroomId] ?? null)
            : Classroom::query()->find($classroomId);

        if (! $classroom) {
            return false;
        }

        if (Gate::forUser($user)->allows('manage', $classroom)) {
            return true;
        }

        if ($preload !== null) {
            return isset($preload['classroom_member_ids'][$classroomId]);
        }

        return ClassroomMember::query()
            ->where('classroom_id', $classroomId)
            ->where('user_id', $user->getKey())
            ->exists();
    }

    /**
     * Defense-in-depth: anggota bisa saja keluar dari grup setelah digest
     * dikirim (lulus/pindah kelas) sebelum ia sempat membuka bell notifikasi.
     *
     * @param  list<array{grup_id?: mixed}>  $groups
     */
    private static function isGrupChatMemberOfAny(User $user, mixed $groups, ?array $preload): bool
    {
        $grupIds = collect((array) $groups)->pluck('grup_id')->filter(fn ($v) => is_string($v))->values();

        if ($grupIds->isEmpty()) {
            return true; // notifikasi lama tanpa metadata
        }

        if ($preload !== null) {
            return $grupIds->contains(fn ($id) => isset($preload['grup_chat_member_ids'][$id]));
        }

        return GrupChatMember::query()
            ->whereIn('grup_id', $grupIds->all())
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->exists();
    }

    private static function canViewSarpras(User $user): bool
    {
        return $user->isAdmin()
            || UserRole::matches((string) $user->access, 'sarpras')
            || $user->canAccess('manage_sarpras');
    }

    /** @param array<string,mixed> $data */
    private static function canViewByUrl(User $user, array $data): bool
    {
        $url = (string) ($data['url'] ?? '');

        if ($url !== '' && str_starts_with($url, '/chatbot/admin')) {
            return $user->isAdmin();
        }

        if ($url !== '' && str_contains($url, '/sarpras/')) {
            return self::canViewSarpras($user);
        }

        if (isset($data['laporan_id'])) {
            return self::canViewSarpras($user);
        }

        // Notifikasi lama tanpa metadata: tetap tampilkan bila sudah tersimpan untuk user ini.
        return true;
    }
}
