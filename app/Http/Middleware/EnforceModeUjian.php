<?php

namespace App\Http\Middleware;

use App\Support\ModeUjian;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengunci siswa di halaman ujian saat Mode Ujian aktif.
 * Seluruh halaman lain (Dashboard, Ruang Kelas, Arena Belajar, Forum, Chat, Profil, Nilai, dll)
 * TIDAK BISA diakses oleh siswa dan otomatis dialihkan ke /ujian/saya.
 */
class EnforceModeUjian
{
    private const ALLOWED_ROUTE_PREFIXES = [
        'ujian.',
        'ganti.',
        'face.',
    ];

    private const ALLOWED_EXACT_ROUTES = [
        'logout',
        'login',
        'login.post',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (ModeUjian::nonaktif()) {
            return $next($request);
        }

        $user = $request->user();

        // Hanya terapkan penguncian ketat pada role siswa
        if ($user && $user->access === 'siswa') {
            $routeName = $request->route()?->getName() ?? '';

            if ($this->isAllowedRoute($routeName)) {
                return $next($request);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'redirect' => route('ujian.siswa.index'),
                    'message' => 'Mode Ujian sedang aktif. Akses terbatas hanya ke ruang ujian.',
                ], 403);
            }

            return redirect()->route('ujian.siswa.index')->with('info', 'Mode Ujian sedang aktif. Anda langsung diarahkan ke ruang ujian.');
        }

        return $next($request);
    }

    private function isAllowedRoute(string $routeName): bool
    {
        if (in_array($routeName, self::ALLOWED_EXACT_ROUTES, true)) {
            return true;
        }

        foreach (self::ALLOWED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
