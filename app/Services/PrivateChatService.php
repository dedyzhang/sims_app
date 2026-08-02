<?php

namespace App\Services;

use App\Models\GrupChat;
use App\Models\PrivateChatConversation;
use App\Models\PrivateChatMessage;
use App\Models\User;
use App\Notifications\PrivateChatMessageReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PrivateChatService
{
    public const MAX_BODY = 4000;

    public function open(User $user, User $target, GrupChat $context): PrivateChatConversation
    {
        Gate::forUser($user)->authorize('privateChat', [$context, $target]);

        [$one, $two] = $this->orderedParticipants($user->getKey(), $target->getKey());

        return PrivateChatConversation::firstOrCreate([
            'participant_one_id' => $one,
            'participant_two_id' => $two,
        ]);
    }

    public function send(PrivateChatConversation $conversation, User $sender, string $body): PrivateChatMessage
    {
        Gate::forUser($sender)->authorize('send', $conversation);

        $body = trim($body);
        if ($body === '') {
            abort(422, 'Pesan tidak boleh kosong.');
        }

        $recipient = null;
        $message = DB::transaction(function () use ($conversation, $sender, $body, &$recipient) {
            $locked = PrivateChatConversation::whereKey($conversation->uuid)->lockForUpdate()->firstOrFail();
            $seq = ((int) $locked->last_seq) + 1;
            $recipient = $locked->otherParticipant($sender->getKey());

            $message = PrivateChatMessage::create([
                'conversation_id' => $locked->uuid,
                'seq' => $seq,
                'sender_id' => $sender->getKey(),
                'sender_nama' => Str::limit($sender->displayName(), 78, ''),
                'body' => $body,
            ]);

            $locked->update([
                'last_seq' => $seq,
                'last_message_at' => now(),
                'last_message_preview' => Str::limit($body, 155),
                $locked->participant_one_id === $sender->getKey() ? 'one_read_seq' : 'two_read_seq' => $seq,
            ]);

            return $message;
        });

        if ($recipient) {
            $recipient->notify(new PrivateChatMessageReceived($conversation->fresh(), $message));
        }

        return $message;
    }

    public function markRead(PrivateChatConversation $conversation, User $user): void
    {
        Gate::forUser($user)->authorize('view', $conversation);

        $column = $conversation->participant_one_id === $user->getKey()
            ? 'one_read_seq'
            : 'two_read_seq';

        $conversation->update([$column => $conversation->last_seq]);
    }

    public function serialize(PrivateChatMessage $message): array
    {
        return [
            'uuid' => $message->uuid,
            'seq' => (int) $message->seq,
            'sender_id' => $message->sender_id,
            'nama' => $message->sender_nama,
            'body' => $message->isDihapus() ? 'Pesan ini dihapus.' : $message->body,
            'dihapus' => $message->isDihapus(),
            'jam' => $message->created_at?->format('H:i'),
            'tanggal' => $message->created_at?->format('Y-m-d'),
        ];
    }

    /** @return array{0:string,1:string} */
    private function orderedParticipants(string $first, string $second): array
    {
        return strcmp($first, $second) < 0 ? [$first, $second] : [$second, $first];
    }
}
