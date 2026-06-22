<?php

namespace App\Sarpras\Notifications;

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

    /** Simpan ke database (in-app notification). */
    public function via(object $notifiable): array
    {
        return ['database'];
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
}
