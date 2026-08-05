<?php

namespace App\Notifications;

use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Digest jadwal mengajar hari ini untuk seorang guru — satu notifikasi berisi
 * seluruh sesi hari itu, bukan satu per sesi, supaya bell/push tidak dibanjiri.
 */
class JadwalDigestHarianNotification extends Notification
{
    use Queueable;

    /**
     * @param  list<array{jam_ke: ?int, jam: string, pelajaran: string, kelas: string}>  $sesi
     * @param  string  $tanggal  Y-m-d — dipakai sbg kunci idempoten (1 digest per hari)
     */
    public function __construct(public array $sesi, public string $tanggal)
    {
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
        $jumlah = count($this->sesi);
        $ringkas = collect($this->sesi)
            ->take(4)
            ->map(fn (array $s) => $s['jam'].' '.$s['pelajaran'].' ('.$s['kelas'].')')
            ->implode(', ');
        if ($jumlah > 4) {
            $ringkas .= ', …';
        }

        return [
            'type' => 'jadwal_harian',
            'tanggal' => $this->tanggal,
            'judul' => 'Jadwal Mengajar Hari Ini',
            'message' => $jumlah.' sesi mengajar hari ini: '.$ringkas,
            'url' => '/jadwal/guru',
        ];
    }
}
