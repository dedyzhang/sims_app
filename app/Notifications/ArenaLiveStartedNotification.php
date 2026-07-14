<?php

namespace App\Notifications;

use App\Models\Classroom;
use App\Models\GameQuiz;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class ArenaLiveStartedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public GameQuiz $quiz,
        public Classroom $classroom
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title'   => 'Arena Belajar Live',
            'message' => $data['message'],
            'url'     => $data['url'],
            'type'    => 'arena_live',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'         => 'arena_live',
            'quiz_id'      => $this->quiz->uuid,
            'classroom_id' => $this->classroom->uuid,
            'message'      => 'Kuis live "'.$this->quiz->title.'" dimulai. Gabung sekarang!',
            'url'          => '/ruang-kelas/'.$this->classroom->class_code.'/arena-belajar/'.$this->quiz->uuid.'/live',
        ];
    }
}
