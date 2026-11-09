<?php

namespace App\Http\Middleware;

use App\Support\DbBusy;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Tangani lonjakan koneksi DB sesaat (micro-spike / max_user_connections)
 * pada tingkat pipeline request. Jika koneksi atau query di awal request
 * (middleware, auth, route-model-binding) gagal karena database sibuk,
 * coba ulang otomatis hingga 5x dengan exponential backoff + jitter dan
 * pembersihan koneksi (DB::purge()) sebelum melempar exception ke view error 503.
 */
class RetryOnDatabaseException
{
    public function handle(Request $request, Closure $next): Response
    {
        $attempt = 0;
        $maxAttempts = 5;

        while (true) {
            $attempt++;
            try {
                return $next($request);
            } catch (Throwable $e) {
                if ($attempt < $maxAttempts && DbBusy::terdeteksi($e)) {
                    try {
                        DB::purge();
                    } catch (Throwable) {
                        // abaikan bila purge gagal
                    }

                    // Exponential backoff bertahap dengan random jitter:
                    // attempt 1: ~200ms - 350ms
                    // attempt 2: ~450ms - 700ms
                    // attempt 3: ~800ms - 1150ms
                    // attempt 4: ~1400ms - 1850ms
                    // Memberikan ruang nafas total hingga 3-4 detik agar antrean MySQL reda
                    $baseUs = match ($attempt) {
                        1 => 200000,
                        2 => 450000,
                        3 => 800000,
                        default => 1400000,
                    };
                    $jitterUs = random_int(40000, 250000);
                    usleep($baseUs + $jitterUs);
                    continue;
                }

                throw $e;
            }
        }
    }
}
