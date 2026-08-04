<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Pengingat satu sesi mengajar, dikirim beberapa menit sebelum jam mulai.
 * sesi_key = "{jadwal_uuid}:{tanggal}" — karena Jadwal rekuren per hari (tanpa
 * tanggal), kunci wajib memuat tanggal supaya guard idempoten tidak menganggap
 * sesi hari ini sudah dikirim gara-gara minggu lalu.
 */
class JadwalSesiReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $sesiKey,
        public string $pelajaran,
        public string $kelas,
        public string $jam,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

    /** Payload data-only untuk FCM; reuse judul/pesan/url dari toArray(). */
    public function toFcm(object $notifiable): array
    {
        $data = $this->toArray($notifiable);

        return [
            'title' => $data['judul'],
            'message' => $data['message'],
            'url' => $data['url'],
            'type' => $data['type'],
        ];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'jadwal_sesi',
            'sesi_key' => $this->sesiKey,
            'judul' => 'Sebentar Lagi Mengajar',
            'message' => 'Sebentar lagi Anda mengajar '.$this->pelajaran.' di '.$this->kelas.', jam '.$this->jam.'.',
            'url' => '/jadwal/guru',
        ];
    }
}
