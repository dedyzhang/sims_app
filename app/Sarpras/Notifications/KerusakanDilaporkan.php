<?php

namespace App\Sarpras\Notifications;

use App\Notifications\Channels\FcmChannel;
use App\Sarpras\Models\LaporanKerusakan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/*
| Notifikasi in-app (database) ke Waka Sarpras saat ada laporan kerusakan baru.
*/
class KerusakanDilaporkan extends Notification
{
    use Queueable;

    public function __construct(public LaporanKerusakan $laporan)
    {
    }

    /** Database (in-app notification) + push FCM. */
    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul' => 'Laporan kerusakan baru',
            'pesan' => 'Laporan ' . ($this->laporan->kode ?? '') . ' — urgensi ' . $this->laporan->urgensi,
            'laporan_id' => $this->laporan->id,
            'url' => route('sarpras.kerusakan.show', $this->laporan->id),
        ];
    }

    /** Payload data-only untuk FCM; reuse judul/pesan/url dari toArray(). */
    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title'   => $data['judul'],
            'message' => $data['pesan'],
            'url'     => $data['url'],
            'type'    => 'sarpras_kerusakan',
        ];
    }
}
