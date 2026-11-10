<?php

namespace App\Demo;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    /**
     * Masuk ke fitur demo secara instan lewat link atau scan QR code.
     * Tidak memerlukan login manual; langsung membuat sesi auth persona dummy
     * dan mengarahkan deep-link ke halaman fitur yang bersangkutan.
     */
    public function enter(Request $request, string $slug)
    {
        // 1. Kunci Utama: Cek apakah modul demo aktif
        if (!config('demo.enabled', false)) {
            abort(404);
        }

        // 2. Kunci Subdomain: Jika dikonfigurasi domain khusus (mis. demo.btive.sch.id)
        $allowedDomain = config('demo.allowed_domain');
        if (!empty($allowedDomain) && strcasecmp($request->getHost(), $allowedDomain) !== 0) {
            abort(404);
        }

        // 3. Validasi slug fitur
        $features = config('demo.features', []);
        if (!array_key_exists($slug, $features)) {
            abort(404, 'Fitur demo tidak ditemukan.');
        }

        $feature = $features[$slug];
        $targetRole = $feature['role'];

        // 4. Cari akun demo dummy (prioritas akun dengan nama berawalan demo_, fallback ke user peran yang sama)
        $dummyUser = User::where('access', $targetRole)
            ->where('username', 'like', 'demo%')
            ->first()
            ?? User::where('access', $targetRole)->first();

        if (!$dummyUser) {
            abort(500, "Akun demo dengan peran '{$targetRole}' belum tersedia di database.");
        }

        // 5. Bypass kewajiban ganti password agar calon pengguna langsung ke tujuan
        if ($dummyUser->must_change_password) {
            $dummyUser->update(['must_change_password' => false]);
        }

        // 6. Login sesi pengguna
        Auth::login($dummyUser);

        // Pre-populate profil jika siswa (mengikuti konvensi LoginController untuk performa)
        if ($dummyUser->access === 'siswa') {
            try {
                $siswa = Siswa::where('id_login', $dummyUser->uuid)->first();
                if ($siswa) {
                    session()->put('profil_siswa_' . $dummyUser->uuid, $siswa);
                }
            } catch (\Throwable) {
                // Abaikan jika ada gangguan query sesaat
            }
        }

        // 7. Tandai sesi sebagai sesi demo aktif
        session([
            'is_demo_session' => true,
            'demo_feature'    => $slug,
            'demo_started_at' => now()->toIso8601String(),
        ]);

        // Kirim response dengan header anti-index robot
        return redirect($feature['target_url'])
            ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
    }

    /**
     * Keluar dari sesi demo.
     */
    public function exit(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('info', 'Sesi demo telah selesai.');
    }
}
