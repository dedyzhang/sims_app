<?php

namespace App\Notifications;

use App\Models\ClassroomComment;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ClassroomCommentNotification extends Notification
{
    use Queueable;

    public function __construct(public ClassroomComment $comment, public string $messageType = 'new_comment')
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /** Payload data-only untuk FCM; url sama dgn navigasi bell icon. */
    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        $url = '/ruang-kelas/'.$data['commentable_type'].'/'.$data['commentable_id'];
        if (! empty($data['classroom_id'])) {
            $url .= '?class='.$data['classroom_id'];
        }
        $url .= '#c-'.$data['comment_id'];

        return [
            'title'   => 'Komentar baru',
            'message' => $data['message'],
            'url'     => $url,
            'type'    => 'classroom_comment',
        ];
    }

    public function toArray(object $notifiable): array
    {
        $commentable = $this->comment->commentable;
        $by = $this->comment->user?->displayName() ?? 'Seseorang';
        
        $typeLabel = 'komentar';
        if ($this->messageType === 'reply') {
            $typeLabel = 'membalas komentar Anda';
        } else {
            $typeLabel = 'mengomentari';
        }

        $title = $commentable?->title ?? 'materi/tugas';
        $isMaterial = $this->comment->commentable_type === \App\Models\ClassroomMaterial::class;
        $itemType = $isMaterial ? 'materi' : 'tugas';

        return [
            'type'            => 'classroom_comment',
            'commentable_type'=> $itemType,
            'commentable_id'  => $commentable?->uuid,
            'comment_id'      => $this->comment->uuid,
            'classroom_id'    => $this->comment->classroom_id,
            'by'              => $by,
            'message'         => $by . ' ' . $typeLabel . ' di ' . $itemType . ' "' . $title . '"',
        ];
    }
}
