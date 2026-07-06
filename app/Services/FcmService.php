<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;

/*
| Pengirim push Firebase Cloud Messaging. Payload SELALU data-only (title,
| message, url, type) supaya Android membangun notifikasi manual secara
| konsisten baik foreground maupun background (lihat Fase 8).
*/
class FcmService
{
    private ?Messaging $messaging = null;
    private ?string $credentialsPath;

    public function __construct()
    {
        $this->credentialsPath = $this->resolvePath(config('services.firebase.credentials'));
    }

    /** FCM aktif hanya bila file service account tersedia. */
    public function enabled(): bool
    {
        return $this->credentialsPath !== null && is_file($this->credentialsPath);
    }

    /**
     * Kirim satu pesan data-only ke satu token. Melempar exception Kreait bila
     * token invalid/expired — biarkan pemanggil (job) yang menangani penghapusan.
     */
    public function send(string $token, array $payload): void
    {
        // FCM data map wajib string => string.
        $data = [];
        foreach ($payload as $key => $value) {
            if ($value !== null) {
                $data[$key] = (string) $value;
            }
        }

        $message = CloudMessage::withTarget('token', $token)->withData($data);
        $this->messaging()->send($message);
    }

    private function messaging(): Messaging
    {
        if ($this->messaging === null) {
            $this->messaging = (new Factory())
                ->withServiceAccount($this->credentialsPath)
                ->createMessaging();
        }

        return $this->messaging;
    }

    /** Path relatif (dari .env) diselesaikan terhadap root project. */
    private function resolvePath(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        $isAbsolute = preg_match('/^([A-Za-z]:[\\\\\/]|\/)/', $path) === 1;

        return $isAbsolute ? $path : base_path($path);
    }
}
