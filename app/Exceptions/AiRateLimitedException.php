<?php

namespace App\Exceptions;

/**
 * Batas laju sementara (mis. 429 per-menit), bukan kuota harian habis.
 * Pemanggil sebaiknya menunda singkat (detik–menit), bukan menunggu reset harian.
 */
class AiRateLimitedException extends AiProviderUnavailableException
{
    public function __construct(
        string $message = '',
        public readonly int $retryAfterSeconds = 60,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
