<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Pengingat mengisi agenda mengajar (jurnal) setelah sebuah sesi selesai.
 * agenda_key = "{id_kelas}:{id_pelajaran}:{tanggal}" — sama seperti kunci dedup
 * agenda (1 agenda per guru+tanggal+kelas+mapel), jadi guard tidak menganggap
 * sesi hari ini sudah dinotif gara-gara minggu lalu, dan double-period yg
 * berbagi satu agenda hanya memicu satu pengingat.
 */
class JadwalAgendaReminderNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $agendaKey,
        public string $pelajaran,
        public string $kelas,
        public string $rentang,
        public string $url,
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
            'type' => 'jadwal_agenda',
            'agenda_key' => $this->agendaKey,
            'judul' => 'Isi Agenda Mengajar',
            'message' => 'Sesi '.$this->pelajaran.' di '.$this->kelas.' ('.$this->rentang.') sudah selesai. Jangan lupa isi agenda mengajar.',
            'url' => $this->url,
        ];
    }
}
