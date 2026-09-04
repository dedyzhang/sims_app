<?php

namespace App\Http\Controllers\Concerns;

use App\Support\DbBusy;
use Throwable;

/**
 * Coba ulang 3x dgn jeda singkat (200/400/600ms) SATU jenis error spesifik: koneksi MySQL
 * ditolak sesaat krn max_user_connections penuh (App\Support\DbBusy). Dipakai di titik yg
 * rawan tembakan bersamaan (buka halaman ujian, scan QR ruangan) — pola sama spt yg sudah
 * terbukti di LoginController, cuma diekstrak supaya dipakai ulang di controller lain.
 *
 * TIDAK melindungi query yg terjadi di route-model-binding (sblm method controller
 * dijalankan) — utk itu ada penangan global di bootstrap/app.php (App\Support\DbBusy).
 */
trait RetriesOnDbBusy
{
    protected function retryOnDbBusy(callable $fn, int $times = 3)
    {
        return retry(
            $times,
            $fn,
            fn (int $attempt) => $attempt * 200,
            fn (Throwable $e) => DbBusy::terdeteksi($e)
        );
    }
}
