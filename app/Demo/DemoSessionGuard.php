<?php

namespace App\Demo;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoSessionGuard
{
    /**
     * Middleware pelindung sesi demo:
     * 1. Jika demo dimatikan (DEMO_ENABLED=false), pengguna demo yang sedang aktif
     *    seketika langsung ditendang keluar (session invalidate + logout).
     * 2. Mencegah calon pengguna demo melakukan aksi destruktif atau mengubah password.
     */
    public function handle(Request $request, Closure $next)
    {
        // Hanya berlaku jika sesi saat ini adalah sesi demo
        if ($request->hasSession() && $request->session()->get('is_demo_session', false)) {
            // Kunci 1: Jika DEMO_ENABLED di-switch ke false, langsung tendang keluar seketika
            if (!config('demo.enabled', false)) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('warning', 'Akses demo saat ini telah dinonaktifkan.');
            }

            // Kunci 2: Proteksi subdomain jika dispesifikasikan
            $allowedDomain = config('demo.allowed_domain');
            if (!empty($allowedDomain) && strcasecmp($request->getHost(), $allowedDomain) !== 0) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->with('warning', 'Sesi demo tidak diizinkan pada domain ini.');
            }

            // Kunci 3: Anti-Vandalisme (Blokir akses perubahan kredensial akun / pengaturan sistem)
            if ($request->is('ganti-password*', 'settings/security*', 'pembaruan*')) {
                return redirect()->route('dashboard')->with('error', 'Fitur perubahan kredensial sistem dinonaktifkan pada sesi demo.');
            }
        }

        return $next($request);
    }
}
