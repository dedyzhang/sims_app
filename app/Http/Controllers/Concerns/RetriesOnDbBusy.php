<?php

namespace App\Http\Controllers\Concerns;

use App\Support\DbBusy;
use Throwable;

/**
 * Coba ulang SEKALI saja dgn jeda pendek (150–400ms) SATU jenis error spesifik: koneksi MySQL
 * ditolak sesaat krn max_user_connections penuh (App\Support\DbBusy). Dipakai di titik yg
 * rawan tembakan bersamaan (buka halaman ujian, scan QR ruangan).
 *
 * Kenapa cuma 1x (dulu 4x dgn jeda total s/d ±2 dtk): selama jeda itu PHP worker TERTAHAN
 * tidur. Saat server sedang penuh, menahan ratusan worker justru membuat antrean makin
 * panjang & memicu error "Resource limit". Lebih sehat gagal CEPAT dgn 503 + Retry-After
 * (lihat bootstrap/app.php) — browser siswa yg menunggu & mencoba lagi dgn jeda acak lebih
 * panjang, tanpa memakan worker server sama sekali selama menunggu.
 *
 * TIDAK melindungi query yg terjadi di route-model-binding (sblm method controller
 * dijalankan) — utk itu ada penangan global di bootstrap/app.php (App\Support\DbBusy).
 */
trait RetriesOnDbBusy
{
    protected function retryOnDbBusy(callable $fn, int $times = 2)
    {
        return retry(
            $times,
            function ($attempt) use ($fn) {
                if ($attempt > 1) {
                    try {
                        \Illuminate\Support\Facades\DB::purge();
                    } catch (Throwable) {}
                }
                return $fn($attempt);
            },
            fn (int $attempt) => 150 + random_int(0, 250),
            fn (Throwable $e) => DbBusy::terdeteksi($e)
        );
    }
}
