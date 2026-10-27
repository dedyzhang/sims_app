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
 * coba ulang otomatis hingga 3x dengan pembersihan koneksi (DB::purge())
 * sebelum melempar exception ke view error 503.
 */
class RetryOnDatabaseException
{
    public function handle(Request $request, Closure $next): Response
    {
        $attempt = 0;
        $maxAttempts = 3;

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

                    // Jeda progresif: 150ms pada retry 1, 300ms pada retry 2
                    usleep($attempt * 150000);
                    continue;
                }

                throw $e;
            }
        }
    }
}
