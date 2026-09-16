<?php

namespace App\Services;

use App\Models\DemoAccess;
use App\Support\DemoHmac;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DemoCallbackClient
{
    /*
    | Callback ke landing adalah HTTP keluar dengan connectTimeout(3)+timeout(10).
    | Pemanggilnya ada di jalur request pengguna (LoginController::loginResilient dan
    | EnforceDemoAccess), jadi host landing yang lambat/black-hole dulu menahan login
    | siswa ~13 detik sebelum redirect. Kirim SETELAH response diflush; pengiriman
    | ini best-effort dan tidak memengaruhi hasil request.
    */
    public function notify(DemoAccess $access, string $event): void
    {
        if (app()->runningInConsole()) {
            $this->send($access, $event);

            return;
        }

        app()->terminating(function () use ($access, $event) {
            $this->send($access, $event);
        });
    }

    public function send(DemoAccess $access, string $event): void
    {
        $url = trim((string) config('demo.landing_callback_url', ''));
        $secret = (string) config('demo.landing_hmac_secret', '');
        if ($url === '' || strlen($secret) < 32) {
            return;
        }

        $payload = [
            'source_request_id' => $access->source_request_id,
            'demo_access_id' => $access->id,
            'event' => $event,
            'occurred_at' => now()->utc()->toIso8601String(),
            'starts_at' => $access->starts_at?->utc()->toIso8601String(),
            'expires_at' => $access->expires_at?->utc()->toIso8601String(),
            'first_login_at' => $access->first_login_at?->utc()->toIso8601String(),
        ];

        $body = DemoHmac::encodeBody($payload);
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: '/internal/v1/demo/provision-callbacks');
        $timestamp = (string) time();
        $nonce = (string) Str::uuid();
        $idempotencyKey = (string) Str::uuid();
        $canonical = DemoHmac::canonicalString('POST', $path, $timestamp, $nonce, $idempotencyKey, DemoHmac::bodyHash($body));

        try {
            Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-SIMS-Key-Id' => (string) config('demo.landing_hmac_key_id', 'sandbox-v1'),
                'X-SIMS-Timestamp' => $timestamp,
                'X-SIMS-Nonce' => $nonce,
                'X-SIMS-Idempotency-Key' => $idempotencyKey,
                'X-SIMS-Correlation-Id' => (string) $access->correlation_id,
                'X-SIMS-Signature' => DemoHmac::signature($canonical, $secret),
            ])
                ->withBody($body, 'application/json')
                ->withOptions(['allow_redirects' => false])
                ->connectTimeout(3)
                ->timeout(10)
                ->post($url);
        } catch (\Throwable) {
            Log::warning('demo.callback.failed', [
                'access_id' => $access->id,
                'event' => $event,
            ]);
        }
    }
}
