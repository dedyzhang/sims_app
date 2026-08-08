<?php

namespace App\Notifications;

use App\Models\Siswa;
use App\Notifications\Channels\FcmChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Notifikasi ke bendahara/admin saat orang tua/siswa mengunggah bukti pembayaran SPP.
 */
class SppBuktiDiunggahNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Siswa $siswa,
        public int $jumlahBulan,
        public string $batchId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', FcmChannel::class];
    }

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
        $kelas = $this->siswa->kelas
            ? trim($this->siswa->kelas->tingkat.' '.$this->siswa->kelas->kelas)
            : null;

        $n = $this->jumlahBulan;
        $message = $n > 1
            ? "{$this->siswa->nama} mengunggah bukti pembayaran untuk {$n} bulan. Perlu diverifikasi."
            : "{$this->siswa->nama} mengunggah bukti pembayaran SPP. Perlu diverifikasi.";

        return [
            'type' => 'spp_bukti_diunggah',
            'judul' => 'Bukti pembayaran SPP baru',
            'message' => $message,
            'url' => '/keuangan/verifikasi?prioritas=1',
            'siswa_id' => $this->siswa->uuid,
            'siswa_nama' => $this->siswa->nama,
            'kelas' => $kelas,
            'jumlah_bulan' => $n,
            'batch_id' => $this->batchId,
        ];
    }
}
