<?php

namespace App\Notifications;

use App\Models\Siswa;
use App\Models\SppPembayaran;
use App\Models\User;
use App\Notifications\Channels\FcmChannel;
use App\Services\Keuangan\SppNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * Notifikasi ke orang tua/siswa saat pembayaran SPP diverifikasi, ditolak, atau lunas.
 */
class SppPembayaranDiperbaruiNotification extends Notification
{
    use Queueable;

    /** @param Collection<int, SppPembayaran> $pembayaran */
    public function __construct(
        public Siswa $siswa,
        public Collection $pembayaran,
        public string $event,
        public ?string $catatan = null,
    ) {}

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User || ! SppNotifier::userIsRecipient($notifiable, $this->siswa)) {
            return [];
        }

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
            'sound' => 'notif_sims',
        ];
    }

    public function toArray(object $notifiable): array
    {
        [$judul, $message] = $this->copyForEvent();

        $first = $this->pembayaran->first();
        $url = $first
            ? '/tagihan-spp/'.$first->uuid.'?anak='.$this->siswa->uuid
            : '/tagihan-spp?anak='.$this->siswa->uuid;

        return [
            'type' => 'spp_pembayaran_status',
            'event' => $this->event,
            'judul' => $judul,
            'message' => $message,
            'url' => $url,
            'siswa_id' => $this->siswa->uuid,
            'siswa_nama' => $this->siswa->nama,
            'jumlah_bulan' => $this->pembayaran->count(),
            'bulan_labels' => $this->pembayaran->map(fn (SppPembayaran $p) => $p->label_bulan)->values()->all(),
            'catatan' => $this->catatan,
        ];
    }

    /** @return array{0: string, 1: string} */
    private function copyForEvent(): array
    {
        $nama = $this->siswa->nama;
        $bulanText = $this->formatBulanText();

        return match ($this->event) {
            'terverifikasi' => [
                'Bukti pembayaran diverifikasi',
                $this->pembayaran->count() > 1
                    ? "Bukti pembayaran SPP {$bulanText} ananda {$nama} telah diverifikasi. Menunggu validasi bank."
                    : "Bukti pembayaran SPP {$bulanText} ananda {$nama} telah diverifikasi. Menunggu validasi bank.",
            ],
            'lunas' => [
                'Pembayaran SPP lunas',
                $this->pembayaran->count() > 1
                    ? "Pembayaran SPP {$bulanText} ananda {$nama} sudah LUNAS."
                    : "Pembayaran SPP {$bulanText} ananda {$nama} sudah LUNAS.",
            ],
            'ditolak' => [
                'Bukti pembayaran ditolak',
                $this->catatan
                    ? "Bukti pembayaran SPP {$bulanText} ananda {$nama} ditolak: {$this->catatan}"
                    : "Bukti pembayaran SPP {$bulanText} ananda {$nama} ditolak. Silakan unggah ulang.",
            ],
            default => [
                'Update pembayaran SPP',
                "Status pembayaran SPP {$bulanText} ananda {$nama} diperbarui.",
            ],
        };
    }

    private function formatBulanText(): string
    {
        $labels = $this->pembayaran->map(fn (SppPembayaran $p) => $p->label_bulan)->values();

        if ($labels->count() <= 2) {
            return $labels->implode(' dan ');
        }

        return $labels->count().' bulan';
    }
}
