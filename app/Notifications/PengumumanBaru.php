<?php

namespace App\Notifications;

use App\Models\Pengumuman;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notifikasi pengumuman baru: masuk bell icon (database) sekaligus push FCM
 * ke perangkat Android. Payload data-only konsisten dgn notifikasi lain.
 */
class PengumumanBaru extends Notification
{
    use Queueable;

    public function __construct(public Pengumuman $pengumuman)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /** Payload data-only untuk FCM; 'sound' menandai ringtone khusus di Android. */
    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title'   => $data['judul'],
            'message' => $data['message'],
            'url'     => '/pengumuman/'.$data['pengumuman_id'],
            'type'    => 'pengumuman',
            'sound'   => 'notif_sims',
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type'          => 'pengumuman',
            'pengumuman_id' => $this->pengumuman->uuid,
            'judul'         => $this->pengumuman->judul,
            'message'       => Str::limit(strip_tags($this->pengumuman->isi), 120),
        ];
    }
}
