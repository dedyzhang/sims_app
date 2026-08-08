<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Digest ringkas antrian verifikasi SPP menumpuk untuk bendahara.
 */
class BendaharaAntrianDigestNotification extends Notification
{
    use Queueable;

    /** @param array{tahun_ajaran:string, menunggu:int, terverifikasi:int, menunggu_lama:int, ambang:int, menumpuk:bool} $ringkasan */
    public function __construct(public array $ringkasan) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $m = $this->ringkasan['menunggu'];
        $l = $this->ringkasan['menunggu_lama'];
        $v = $this->ringkasan['terverifikasi'];

        $message = "{$m} bukti menunggu verifikasi";
        if ($l > 0) {
            $message .= " ({$l} sudah lebih dari ".config('keuangan-ai.digest.usia_hari_min', 3).' hari)';
        }
        if ($v > 0) {
            $message .= ", {$v} menunggu validasi bank";
        }

        return [
            'type'    => 'bendahara_antrian_digest',
            'judul'   => 'Antrian verifikasi SPP menumpuk',
            'message' => $message,
            'url'     => '/keuangan/verifikasi?prioritas=1',
            'ringkasan' => $this->ringkasan,
        ];
    }
}
