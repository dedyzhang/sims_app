<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate khusus halaman kiosk absensi (scan wajah / tampilan QR): mengizinkan akses PUBLIK
 * (tanpa login sama sekali) bila request membawa token kiosk yang valid lewat query string
 * `_kiosk` — divalidasi ULANG setiap request, TIDAK PERNAH disimpan ke session/Auth::login().
 *
 * Ini sengaja menghindari session/Auth sama sekali supaya membuka link kiosk di browser yang
 * SAMA dengan tab lain yang sudah login (mis. admin) tidak pernah menimpa/mengeluarkan sesi
 * login orang itu — beda dari pendekatan lama yang memanggil Auth::login() ke akun kiosk.
 *
 * Tanpa token valid → jatuh kembali ke aturan biasa: wajib login + wajah terdaftar + izin manage_absensi.
 */
class EnsureKioskOrPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        if (self::hasValidToken($request)) {
            return $next($request);
        }

        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();
        $profile = $user->siswa ?: $user->guru;
        if ($profile && empty($profile->face_descriptor)) {
            return redirect()->route('face.self');
        }

        if (!$user->canAccess('manage_absensi')) {
            abort(403, 'AKSES TIDAK DIIZINKAN. Peran Anda belum diberi izin untuk fitur ini.');
        }

        return $next($request);
    }

    /** Cocokkan token kiosk di request (query atau body) dgn token asli tersimpan di Setting. */
    public static function hasValidToken(Request $request): bool
    {
        $token = $request->query('_kiosk') ?: $request->input('_kiosk');
        $real = Setting::get('kiosk_token');
        return $token && $real && hash_equals((string) $real, (string) $token);
    }
}
