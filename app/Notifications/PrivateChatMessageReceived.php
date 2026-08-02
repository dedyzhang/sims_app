<?php

namespace App\Notifications;

use App\Models\PrivateChatConversation;
use App\Models\PrivateChatMessage;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class PrivateChatMessageReceived extends Notification
{
    public function __construct(
        public PrivateChatConversation $conversation,
        public PrivateChatMessage $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'private_chat',
            'conversation_id' => $this->conversation->uuid,
            'sender_id' => $this->message->sender_id,
            'sender_nama' => $this->message->sender_nama,
            'message' => Str::limit($this->message->body, 120),
            'url' => '/private-chat/'.$this->conversation->uuid,
        ];
    }

    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => 'Pesan langsung dari '.$data['sender_nama'],
            'message' => $data['message'],
            'url' => '/private-chat/'.$data['conversation_id'],
            'type' => 'private_chat',
            'sound' => 'notif_sims',
        ];
    }
}
