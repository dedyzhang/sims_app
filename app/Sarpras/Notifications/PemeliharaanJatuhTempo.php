<?php

namespace App\Sarpras\Notifications;

use App\Sarpras\Models\JadwalPemeliharaan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/*
| Notifikasi in-app ke Waka Sarpras saat jadwal pemeliharaan jatuh tempo.
*/
class PemeliharaanJatuhTempo extends Notification
{
    use Queueable;

    public function __construct(public JadwalPemeliharaan $jadwal)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'judul' => 'Pemeliharaan jatuh tempo',
            'pesan' => $this->jadwal->nama . ($this->jadwal->aset ? ' — ' . $this->jadwal->aset->nama : ''),
            'jadwal_id' => $this->jadwal->id,
            'url' => route('sarpras.jadwal.index'),
        ];
    }
}
