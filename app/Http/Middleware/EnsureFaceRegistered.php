<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memaksa siswa & guru yang BELUM mendaftarkan wajah untuk mendaftar dulu
 * sebelum bisa mengakses halaman lain. Role lain (admin, ortu, dll) dilewati.
 */
class EnsureFaceRegistered
{
    /** Route yang tetap boleh diakses meski wajah belum terdaftar (hindari loop) */
    private array $allowed = [
        'face.self', 'face.self.store',
        'logout', 'auth.home',
        'ganti.password', 'ganti.username', 'ganti.pin',
        'profile.style',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return $next($request);
        }

        $name = $request->route()?->getName() ?? '';
        // Selalu izinkan route onboarding/biometrik
        if (in_array($name, $this->allowed, true) || str_starts_with($name, 'webauthn')) {
            return $next($request);
        }

        // HANYA orang tua yang dikecualikan dari daftar wajah
        if (in_array($user->access, ['orangtua', 'ortu'], true)) {
            return $next($request);
        }

        // Biarkan alur ganti password bawaan selesai dulu
        if ($user->must_change_password) {
            return $next($request);
        }

        // Selain ortu (siswa, guru, kepala, kurikulum, kesiswaan, sapras, admin, dll)
        // wajib daftar wajah — descriptor disimpan di profil siswa/guru-nya
        $profile = $user->siswa ?: $user->guru;
        if ($profile && empty($profile->face_descriptor)) {
            return redirect()->route('face.self');
        }

        return $next($request);
    }
}
