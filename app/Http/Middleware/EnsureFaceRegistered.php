<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memaksa SEMUA role (siswa, guru, kepala, kurikulum, kesiswaan, sapras, admin, dll)
 * mendaftarkan wajah sebelum mengakses halaman lain. HANYA orang tua yang dikecualikan.
 * User yang belum ganti password bawaan dipaksa ke halaman ganti password dulu.
 */
class EnsureFaceRegistered
{
    /** Route yang tetap boleh diakses meski wajah belum terdaftar (hindari loop) */
    private array $allowed = [
        'face.self', 'face.self.store',
        'logout', 'auth.home',
        'ganti.password', 'ganti.password.post', 'ganti.username', 'ganti.pin', 'ganti.pin.post',
        'profile.style',
        'absen.qr', 'absen.qr.mark',   // absen QR boleh tanpa wajib daftar wajah
        'notifications.fcmToken.store', 'notifications.fcmToken.destroy',
        'fcm.token.store', 'fcm.token.destroy',   // alias legacy APK Android
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

        // Wajib selesaikan ganti password dulu — JANGAN dilewatkan begitu saja
        // (kalau dilewatkan, user bisa menerobos ke dashboard tanpa daftar wajah).
        // Rute ganti password sudah di-whitelist di atas, jadi tidak loop.
        if ($user->must_change_password) {
            return redirect()->route('ganti.password');
        }

        // Selain ortu (siswa, guru, kepala, kurikulum, kesiswaan, sapras, admin, dll)
        // wajib daftar wajah — descriptor disimpan di profil siswa/guru-nya. Kolom yg dicek
        // ikut mesin pengenalan wajah yg AKTIF (Setting → Mesin Pengenalan Wajah): default
        // 'human' → face_descriptor (perilaku lama, tidak berubah); 'insightface' →
        // face_descriptor_if (konsisten dgn kios scan — orang yg baru pernah daftar via Human.js
        // memang wajib daftar ulang sekali begitu mesin dipindah, sesuai yg sudah diberitahukan).
        $profile = $user->siswa ?: $user->guru;
        if ($profile && empty($profile->{\App\Support\FaceEngine::kolomDescriptor()})) {
            return redirect()->route('face.self');
        }

        return $next($request);
    }
}
