<?php

namespace App\Notifications;

use App\Models\ChatbotConversation;
use App\Models\ChatbotMessage;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class ChatbotAdminReplyReceived extends Notification
{
    use Queueable;

    public function __construct(public ChatbotConversation $conversation, public ChatbotMessage $message)
    {
    }

    public function via(object $notifiable): array
    {
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
            'message_id' => $data['message_id'],
            'sound' => 'notif_sims',
        ];
    }

    public function toArray(object $notifiable): array
    {
        $admin = $this->message->senderUser?->displayName() ?? 'Admin sekolah';
        $body = trim((string) $this->message->body);
        $hasAttachment = (bool) $this->message->attachment_path;

        $message = match (true) {
            $body !== '' => $admin.': '.Str::limit($body, 110),
            $hasAttachment => $admin.' mengirim lampiran chat.',
            default => $admin.' membalas chat Anda.',
        };

        return [
            'type' => 'chatbot_admin_reply',
            'conversation_id' => $this->conversation->id,
            'message_id' => $this->message->id,
            'judul' => 'Balasan chat admin',
            'message' => $message,
            'url' => '/chatbot',
        ];
    }
}