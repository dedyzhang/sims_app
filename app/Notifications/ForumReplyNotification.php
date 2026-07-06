<?php

namespace App\Notifications;

use App\Models\ForumComment;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ForumReplyNotification extends Notification
{
    use Queueable;

    public function __construct(public ForumComment $comment)
    {
    }

    /** Database (bell icon) + push FCM. */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /** Payload data-only untuk FCM; reuse pesan dari toArray(). */
    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title'   => 'Balasan forum baru',
            'message' => $data['message'],
            'url'     => '/forum/'.$data['topic_slug'].'#c-'.$data['comment_id'],
            'type'    => 'forum_reply',
        ];
    }

    public function toArray(object $notifiable): array
    {
        $topic = $this->comment->topic;
        $by = $this->comment->user?->displayName() ?? 'Seseorang';

        return [
            'type'        => 'forum_reply',
            'topic_slug'  => $topic?->slug,
            'topic_title' => $topic?->title,
            'comment_id'  => $this->comment->uuid,
            'by'          => $by,
            'message'     => $by . ' membalas pada "' . ($topic?->title ?? 'topik') . '"',
        ];
    }
}
