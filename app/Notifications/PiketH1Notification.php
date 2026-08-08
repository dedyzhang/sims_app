<?php

namespace App\Notifications;

use App\Models\JadwalPiket;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Pengingat in-app H-1 ke guru yang dijadwalkan piket besok. */
class PiketH1Notification extends Notification
{
    use Queueable;

    public function __construct(
        public JadwalPiket $jadwalPiket,
        public string $tanggalPiket,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $tanggal = Carbon::parse($this->tanggalPiket)->locale('id');

        return [
            'type' => 'piket_h1',
            'jadwal_piket_id' => $this->jadwalPiket->uuid,
            'tanggal_piket' => $this->tanggalPiket,
            'judul' => 'Jadwal Piket Besok',
            'message' => 'Anda dijadwalkan piket besok, '.$tanggal->isoFormat('dddd, D MMMM Y').'.',
            'url' => '/piket',
        ];
    }
}
