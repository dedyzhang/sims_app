<?php

namespace App\Http\Middleware;

use App\Support\DemoHmac;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class VerifyDemoHmac
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $this->header($request, 'X-SIMS-Correlation-Id') ?: (string) Str::uuid();
        $request->attributes->set('demo_correlation_id', $correlationId);

        if (! filter_var(config('demo.enabled'), FILTER_VALIDATE_BOOLEAN)) {
            return $this->error('DEMO_TEMPORARILY_UNAVAILABLE', 'Sandbox demo tidak aktif.', $correlationId, 503);
        }

        $secret = (string) config('demo.hmac.secret', '');
        if (strlen($secret) < 32) {
            return $this->error('DEMO_TEMPORARILY_UNAVAILABLE', 'Sandbox demo tidak aktif.', $correlationId, 503);
        }

        $keyId = $this->header($request, 'X-SIMS-Key-Id');
        $timestamp = $this->header($request, 'X-SIMS-Timestamp');
        $nonce = $this->header($request, 'X-SIMS-Nonce');
        $idempotencyKey = $this->header($request, 'X-SIMS-Idempotency-Key');
        $signature = $this->header($request, 'X-SIMS-Signature');

        if ($keyId !== (string) config('demo.hmac.key_id', 'landing-v1')) {
            return $this->error('SIGNATURE_INVALID', 'Signature tidak valid.', $correlationId, 401);
        }

        if ($timestamp === '' || ! ctype_digit($timestamp) || $nonce === '' || $idempotencyKey === '' || $signature === '') {
            return $this->error('SIGNATURE_INVALID', 'Signature tidak valid.', $correlationId, 401);
        }

        if (! Str::isUuid($nonce) || ! Str::isUuid($idempotencyKey)) {
            return $this->error('SIGNATURE_INVALID', 'Signature tidak valid.', $correlationId, 401);
        }

        $tolerance = (int) config('demo.hmac.timestamp_tolerance', 300);
        if (abs(time() - (int) $timestamp) > $tolerance) {
            return $this->error('SIGNATURE_INVALID', 'Signature tidak valid.', $correlationId, 401);
        }

        $nonceKey = 'demo-hmac-nonce:'.$nonce;
        if (! Cache::add($nonceKey, 1, (int) config('demo.hmac.nonce_ttl', 600))) {
            return $this->error('SIGNATURE_INVALID', 'Signature tidak valid.', $correlationId, 401);
        }

        $body = $request->getContent();
        $canonical = DemoHmac::canonicalString(
            $request->method(),
            $request->getPathInfo(),
            $timestamp,
            $nonce,
            $idempotencyKey,
            DemoHmac::bodyHash($body),
        );
        $expected = DemoHmac::signature($canonical, $secret);

        if (! hash_equals($expected, $signature)) {
            return $this->error('SIGNATURE_INVALID', 'Signature tidak valid.', $correlationId, 401);
        }

        $request->attributes->set('demo_idempotency_key', $idempotencyKey);
        $request->attributes->set('demo_body_hash', DemoHmac::bodyHash($body));

        return $next($request);
    }

    private function header(Request $request, string $name): string
    {
        return trim((string) $request->headers->get($name, ''));
    }

    private function error(string $code, string $message, string $correlationId, int $status): Response
    {
        return response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'correlation_id' => $correlationId,
            ],
        ], $status);
    }
}
