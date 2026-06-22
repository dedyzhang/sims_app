<?php

namespace App\Notifications;

use App\Models\ForumComment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ForumReplyNotification extends Notification
{
    use Queueable;

    public function __construct(public ForumComment $comment)
    {
    }

    /** Hanya database channel (tanpa broadcast/mail) agar ringan. */
    public function via(object $notifiable): array
    {
        return ['database'];
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
