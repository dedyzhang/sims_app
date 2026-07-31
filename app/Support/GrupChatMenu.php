<?php

namespace App\Support;

use App\Models\GrupChat;
use App\Models\GrupChatMember;
use App\Models\User;
use App\Policies\GrupChatPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Helper ringan untuk sidebar & endpoint badge. Dipisah dari controller supaya
 * layout tidak perlu meng-instantiate controller apa pun.
 */
class GrupChatMenu
{
    /** True bila menu Grup Chat layak ditampilkan untuk user ini. */
    public static function tampil(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $user->canAccess(GrupChatPolicy::IZIN_KELOLA)) {
            return true;
        }

        return GrupChatMember::where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->exists();
    }

    /**
     * Total pesan belum dibaca di semua grup user.
     *
     * Murni aritmatika (grup.last_seq - member.last_read_seq) — tabel pesan tidak
     * pernah disentuh, sehingga badge tetap murah walau di-poll tiap 30 detik.
     */
    public static function unreadTotal(?User $user): int
    {
        if (! $user) {
            return 0;
        }

        return (int) GrupChatMember::query()
            ->join('grup_chats', 'grup_chats.uuid', '=', 'grup_chat_members.grup_id')
            ->where('grup_chat_members.user_id', $user->getKey())
            ->whereNull('grup_chat_members.left_at')
            ->where('grup_chats.status', 'aktif')
            ->whereColumn('grup_chats.last_seq', '>', 'grup_chat_members.last_read_seq')
            ->sum(DB::raw('grup_chats.last_seq - grup_chat_members.last_read_seq'));
    }

    /**
     * Daftar grup untuk halaman index — satu query, tanpa menyentuh tabel pesan.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    public static function daftar(User $user)
    {
        $isAdmin = $user->isAdmin() || $user->canAccess(GrupChatPolicy::IZIN_KELOLA);

        $query = DB::table('grup_chats')
            ->join('kelas', 'kelas.uuid', '=', 'grup_chats.id_kelas')
            ->leftJoin('grup_chat_members', function ($join) use ($user) {
                $join->on('grup_chats.uuid', '=', 'grup_chat_members.grup_id')
                     ->where('grup_chat_members.user_id', '=', $user->getKey())
                     ->whereNull('grup_chat_members.left_at');
            });

        if (! $isAdmin) {
            $query->whereNotNull('grup_chat_members.grup_id');
        }

        return $query
            ->orderByRaw('grup_chats.last_message_at IS NULL')
            ->orderByDesc('grup_chats.last_message_at')
            ->orderBy('kelas.tingkat')
            ->orderBy('kelas.kelas')
            ->get([
                'grup_chats.uuid',
                'grup_chats.tipe',
                'grup_chats.nama',
                'grup_chats.mode',
                'grup_chats.status',
                'grup_chats.tahun_ajaran',
                'grup_chats.last_seq',
                'grup_chats.last_message_at',
                'grup_chats.last_message_preview',
                'grup_chats.last_message_by',
                'grup_chat_members.last_read_seq',
                'grup_chat_members.peran',
            ])
            ->map(function ($row) use ($isAdmin) {
                $lastRead = $row->last_read_seq ?? 0;
                $row->unread = max(0, (int) $row->last_seq - (int) $lastRead);
                $row->is_paguyuban = $row->tipe === GrupChat::TIPE_PAGUYUBAN;
                $row->peran = $row->peran ?? ($isAdmin ? 'admin' : 'anggota');

                return $row;
            });
    }
}
