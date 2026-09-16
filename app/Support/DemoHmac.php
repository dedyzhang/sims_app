<?php

namespace App\Support;

final class DemoHmac
{
    public static function bodyHash(string $body): string
    {
        return hash('sha256', $body);
    }

    public static function canonicalString(
        string $method,
        string $path,
        string $timestamp,
        string $nonce,
        string $idempotencyKey,
        string $bodyHash,
    ): string {
        return implode("\n", [
            strtoupper($method),
            $path,
            $timestamp,
            $nonce,
            $idempotencyKey,
            $bodyHash,
        ]);
    }

    public static function signature(string $canonical, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $canonical, $secret);
    }

    public static function encodeBody(array $payload): string
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (! is_string($json) || $json === '') {
            throw new \RuntimeException('DEMO_REQUEST_INVALID');
        }

        return $json;
    }
}
