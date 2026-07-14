<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk semua respons web:
 * - Cegah clickjacking (situs lain meng-iframe halaman SIMS lalu menipu klik user).
 * - Cegah MIME-sniffing pada file upload (upload "gambar" berisi HTML/JS tak akan
 *   dieksekusi browser sebagai halaman).
 * - Batasi info Referer yang bocor ke situs eksternal (URL internal bisa memuat ID).
 * - Matikan API browser yang tidak dipakai; kamera tetap diizinkan untuk halaman
 *   sendiri (absensi wajah & scan QR).
 * - HSTS hanya saat HTTPS agar browser menolak downgrade ke HTTP di kunjungan berikutnya.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        // geolocation=(self): WAJIB agar Absen QR bisa membaca lokasi utk cek radius sekolah.
        // Tanpa "self", getCurrentPosition selalu ditolak walau izin browser sudah diaktifkan.
        $response->headers->set('Permissions-Policy', 'camera=(self), microphone=(), geolocation=(self), payment=()');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
