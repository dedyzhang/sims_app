<?php

namespace App\Policies;

use App\Models\GrupChat;
use App\Models\PrivateChatConversation;
use App\Models\User;

class PrivateChatConversationPolicy
{
    public function view(User $user, PrivateChatConversation $conversation): bool
    {
        if (! $conversation->includes($user->getKey())) {
            return false;
        }

        $other = $conversation->otherParticipant($user->getKey());
        if (! $other) {
            return false;
        }

        return $this->waliRelatesTo($user, $other)
            || $this->waliRelatesTo($other, $user);
    }

    public function send(User $user, PrivateChatConversation $conversation): bool
    {
        return $this->view($user, $conversation);
    }

    /** Hubungan privat hanya wali kelas dengan siswa/orang tua di kelasnya. */
    private function waliRelatesTo(User $wali, User $target): bool
    {
        if ($wali->access !== 'walikelas' || ! in_array($target->access, ['siswa', 'orangtua'], true)) {
            return false;
        }

        $tipe = $target->access === 'orangtua'
            ? GrupChat::TIPE_PAGUYUBAN
            : GrupChat::TIPE_KELAS;
        $peranTarget = $target->access;

        return GrupChat::query()
            ->where('tipe', $tipe)
            ->aktif()
            ->whereHas('members', fn ($q) => $q
                ->where('user_id', $wali->getKey())
                ->where('peran', 'walikelas')
                ->whereNull('left_at'))
            ->whereHas('members', fn ($q) => $q
                ->where('user_id', $target->getKey())
                ->where('peran', $peranTarget)
                ->whereNull('left_at'))
            ->exists();
    }
}
