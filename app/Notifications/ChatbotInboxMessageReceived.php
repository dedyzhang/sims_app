<?php

namespace App\Notifications;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ChatbotInboxMessageReceived extends Notification
{
    use Queueable;

    public function __construct(
        public ChatbotConversation $conversation,
        public ?ChatbotMessage $message = null,
        public string $event = 'message',
    ) {
    }

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User || ! $notifiable->isAdmin()) {
            return [];
        }

        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['judul'],
            'message' => $data['message'],
            'url' => $data['url'],
            'type' => $data['type'],
            'conversation_id' => $data['conversation_id'],
            'message_id' => $data['message_id'] ?? '',
            'sound' => 'notif_sims',
        ];
    }

    public function toArray(object $notifiable): array
    {
        $this->conversation->loadMissing('user');

        $sender = $this->conversation->user?->displayName() ?? 'Pengguna';
        $body = trim((string) ($this->message?->body ?? ''));
        $hasAttachment = (bool) $this->message?->attachment_path;

        $message = match (true) {
            $body !== '' => $sender.': '.Str::limit($body, 110),
            $hasAttachment => $sender.' mengirim lampiran chat.',
            default => $sender.' meminta dihubungkan ke admin.',
        };

        return [
            'type' => 'chatbot_inbox',
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message?->id,
            'judul' => 'Chat masuk',
            'message' => $message,
            'url' => '/chatbot/admin/inbox',
        ];
    }
}