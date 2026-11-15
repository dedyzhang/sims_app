<?php

namespace App\Demo;

use App\Http\Controllers\Controller;
use App\Models\Guru;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DemoController extends Controller
{
    /**
     * Masuk ke fitur demo secara instan lewat link atau scan QR code.
     * Tidak memerlukan login manual; otomatis mencari atau membuat akun dummy
     * lalu mengarahkan deep-link ke halaman fitur yang bersangkutan.
     */
    public function enter(Request $request, string $slug)
    {
        try {
            // 1. Kunci Utama: Cek apakah modul demo aktif
            if (!config('demo.enabled', false)) {
                abort(404, 'Modul demo tidak diaktifkan.');
            }

            // 2. Kunci Subdomain: Jika dikonfigurasi domain khusus (mis. demo.btivesolution.com)
            $allowedDomain = config('demo.allowed_domain');
            if (!empty($allowedDomain) && strcasecmp($request->getHost(), $allowedDomain) !== 0) {
                abort(404, 'Akses demo tidak diizinkan pada host ini.');
            }

            // 3. Validasi slug fitur
            $features = config('demo.features', []);
            if (!array_key_exists($slug, $features)) {
                abort(404, "Fitur demo '{$slug}' tidak ditemukan.");
            }

            $feature = $features[$slug];
            $targetRole = $feature['role'];

            // 4. Cari atau Buat Akun Demo Secara Otomatis (Self-Healing)
            $dummyUser = $this->resolveOrCreateDemoUser($targetRole);

            if (!$dummyUser) {
                return $this->renderErrorView(
                    "Gagal Menyiapkan Akun Demo",
                    "Tidak dapat menemukan atau membuat akun demo dengan peran [{$targetRole}]. Silakan periksa koneksi database atau permission tabel users."
                );
            }

            // 5. Pastikan must_change_password tidak menghalangi calon pengguna
            if (!empty($dummyUser->must_change_password)) {
                try {
                    $dummyUser->update(['must_change_password' => false]);
                } catch (\Throwable) {
                    $dummyUser->must_change_password = false;
                }
            }

            // 6. Login sesi pengguna
            Auth::login($dummyUser);

            // Pre-populate profil jika siswa (agar ujian & dashboard siswa tidak query redundant)
            if ($dummyUser->access === 'siswa') {
                try {
                    $siswa = Siswa::where('id_login', $dummyUser->uuid)->first();
                    if ($siswa) {
                        session()->put('profil_siswa_' . $dummyUser->uuid, $siswa);
                    }
                } catch (\Throwable) {
                    // Abaikan jika query profil ada gangguan
                }
            }

            // 7. Tandai sesi sebagai sesi demo aktif & cegah popup pembaruan
            session([
                'is_demo_session' => true,
                'demo_feature'    => $slug,
                'demo_started_at' => now()->toIso8601String(),
            ]);

            if (class_exists(\App\Models\AppUpdate::class)) {
                try {
                    $latestUpdate = \App\Models\AppUpdate::where('is_published', true)->latest('created_at')->first();
                    if ($latestUpdate && $dummyUser->dismissed_update_id !== $latestUpdate->uuid) {
                        $dummyUser->dismissed_update_id = $latestUpdate->uuid;
                        $dummyUser->saveQuietly();
                    }
                } catch (\Throwable) {
                    // Abaikan jika skema pembaruan belum termigrasi
                }
            }

            // 8. Redirect langsung ke fitur tujuan
            return redirect($feature['target_url'])
                ->header('X-Robots-Tag', 'noindex, nofollow, noarchive');

        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error("[DEMO ENTER ERROR] Slug: {$slug} | Message: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->renderErrorView(
                "Terjadi Kendala pada Sesi Demo",
                $e->getMessage(),
                $e->getFile() . ':' . $e->getLine()
            );
        }
    }

    /**
     * Cari akun yang ada, atau buat akun demo otomatis on-the-fly jika database masih kosong.
     */
    protected function resolveOrCreateDemoUser(string $targetRole): ?User
    {
        // 1. Cari user yang sudah ada dengan peran yang cocok
        $existing = User::where('access', $targetRole)
            ->where('username', 'like', '%demo%')
            ->first()
            ?? User::where('access', $targetRole)->first();

        if ($existing) {
            return $existing;
        }

        // 2. Jika database kosong / belum ada user untuk peran ini, buat akun demo secara otomatis!
        try {
            $username = 'demo_' . $targetRole;

            // Pastikan username unik
            if (User::where('username', $username)->exists()) {
                $username = 'demo_' . $targetRole . '_' . Str::lower(Str::random(4));
            }

            $newUser = User::create([
                'uuid'                 => (string) Str::uuid(),
                'username'             => $username,
                'password'             => Hash::make('demo12345'),
                'access'               => $targetRole,
                'must_change_password' => false,
            ]);

            // Buat record relasi jika peran adalah siswa
            if ($targetRole === 'siswa' && class_exists(Siswa::class)) {
                try {
                    Siswa::firstOrCreate(
                        ['id_login' => $newUser->uuid],
                        [
                            'uuid'   => (string) Str::uuid(),
                            'nama'   => 'Siswa Demo (B\'tive)',
                            'nis'    => '99' . mt_rand(1000, 9999),
                            'nisn'   => '00' . mt_rand(10000000, 99999999),
                            'status' => 'aktif',
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning("[DEMO AUTO-SISWA WARNING] " . $e->getMessage());
                }
            }

            // Buat record relasi jika peran adalah guru / walikelas
            if (in_array($targetRole, ['guru', 'walikelas']) && class_exists(Guru::class)) {
                try {
                    Guru::firstOrCreate(
                        ['id_login' => $newUser->uuid],
                        [
                            'uuid' => (string) Str::uuid(),
                            'nama' => 'Guru Demo, S.Pd.',
                            'nip'  => '19900101' . mt_rand(100000, 999999) . '1001',
                        ]
                    );
                } catch (\Throwable $e) {
                    Log::warning("[DEMO AUTO-GURU WARNING] " . $e->getMessage());
                }
            }

            return $newUser;
        } catch (\Throwable $e) {
            Log::error("[DEMO AUTO-PROVISION ERROR] " . $e->getMessage());
            return null;
        }
    }

    /**
     * Tampilan error yang informatif dan ramah pengguna (bukan 500 polos).
     */
    protected function renderErrorView(string $title, string $message, ?string $detail = null)
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} — B'tive Demo</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-800 flex items-center justify-center min-h-screen p-4">
    <div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 shadow-xl p-6 sm:p-8 text-center">
        <div class="w-12 h-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center mx-auto mb-4 font-bold text-xl">
            !
        </div>
        <h1 class="text-lg font-bold text-slate-900 mb-2">{$title}</h1>
        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed mb-6">{$message}</p>
HTML;

        if ($detail) {
            $html .= <<<HTML
        <div class="text-[11px] font-mono text-left bg-slate-100 text-slate-700 p-3 rounded-lg mb-6 overflow-x-auto border border-slate-200">
            <strong>Detail:</strong><br>{$detail}
        </div>
HTML;
        }

        $catalogUrl = \Illuminate\Support\Facades\Route::has('demo.showcase') ? route('demo.showcase') : url('/demo-access/showcase');
        $html .= <<<HTML
        <div class="flex flex-col gap-2">
            <a href="{$catalogUrl}" class="w-full py-2.5 px-4 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold rounded-xl transition">
                Kembali ke Katalog Demo
            </a>
            <a href="/" class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-xl transition">
                Halaman Utama
            </a>
        </div>
    </div>
</body>
</html>
HTML;

        return response($html, 500)->header('Content-Type', 'text/html');
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
