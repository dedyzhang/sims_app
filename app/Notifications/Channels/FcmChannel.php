<?php

namespace App\Notifications\Channels;

use App\Jobs\SendFcmNotificationJob;
use Illuminate\Notifications\Notification;

/*
| Custom channel notifikasi Firebase Cloud Messaging.
|
| Payload dibangun via toFcm() milik tiap Notification, lalu pengiriman ke
| Firebase DIDORONG ke queue (SendFcmNotificationJob) — token di-loop & token
| invalid dihapus di dalam job itu, bukan di sini, supaya HTTP call tidak
| menahan request user. Channel ini sengaja TIDAK melempar exception apa pun
| sehingga channel 'database' dan penerima lain tetap jalan meski FCM gagal.
*/
class FcmChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toFcm')) {
            return;
        }

        $payload = $notification->toFcm($notifiable);
        $userUuid = $notifiable->getKey();

        if (empty($payload) || empty($userUuid)) {
            return;
        }

        SendFcmNotificationJob::dispatch((string) $userUuid, $payload);
    }
}
