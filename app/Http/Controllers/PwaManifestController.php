<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

/*
| Manifest PWA dirender per-deployment, BUKAN file statis.
|
| SIMS dipasang di banyak sekolah dari satu basis kode yang sama. Manifest statis
| (dulu public/WebVIEW_SMP_MW_TPI.json) membuat setiap sekolah yang memasang PWA
| mendapat ikon + nama "SMP Maitreyawira TPI" di layar utama. Nama, deskripsi, dan
| ikon karenanya dibaca dari Pengaturan (`nama_sekolah`, `sekolah_logo`) — sama
| dengan sumber yang dipakai view composer di AppServiceProvider.
|
| Ikon bawaan di public/icons/ HANYA dipakai kalau sekolah belum mengunggah logo.
*/
class PwaManifestController extends Controller
{
    /** Ekstensi logo yang boleh jadi ikon home-screen (SVG ditolak Safari/iOS). */
    private const RASTER_EXT = ['png', 'jpg', 'jpeg', 'webp'];

    /** GET /manifest.webmanifest */
    public function __invoke(): JsonResponse
    {
        $identity = self::identity();
        $nama = $identity['nama'];

        return response()
            ->json([
                'id' => '/?app='.$identity['slug'],
                'name' => $nama,
                'short_name' => \Illuminate\Support\Str::limit($nama, 12, ''),
                'description' => $nama.' — Sistem Informasi Manajemen Sekolah (SIMS): absensi, akademik, nilai, dan informasi sekolah.',
                'lang' => 'id',
                'dir' => 'ltr',
                'start_url' => '/?source=pwa-ios',
                'scope' => '/',
                'display' => 'standalone',
                'display_override' => ['standalone', 'fullscreen', 'minimal-ui'],
                'orientation' => 'portrait-primary',
                'theme_color' => (string) config('pwa.theme_color', '#1e1b4b'),
                'background_color' => '#ffffff',
                'categories' => ['education'],
                'prefer_related_applications' => false,
                'icons' => self::icons($identity['logoUrl'], $identity['logoExt']),
                'shortcuts' => [
                    [
                        'name' => 'Masuk / Login',
                        'short_name' => 'Masuk',
                        'url' => '/?source=pwa-shortcut-login',
                    ],
                    [
                        'name' => 'Panduan Pasang iPhone',
                        'short_name' => 'Pasang iOS',
                        'url' => '/unduh-aplikasi-tamu/ios?source=pwa-shortcut',
                    ],
                ],
            ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json')
            // Pendek saja: admin bisa ganti nama/logo sekolah kapan pun dan manifest
            // yang basi berarti ikon sekolah lama menempel di layar utama pengguna.
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * Nama + URL logo sekolah dari Pengaturan, aman dipanggil sebelum migrasi jalan.
     *
     * @return array{nama: string, slug: string, logoUrl: ?string, logoExt: ?string}
     */
    public static function identity(): array
    {
        $nama = 'Edutive';
        $logoUrl = null;
        $logoExt = null;

        try {
            if (Schema::hasTable('settings')) {
                $nama = Setting::get('nama_sekolah', 'Edutive') ?: 'Edutive';
                $logoPath = Setting::get('sekolah_logo');
                if ($logoPath && file_exists(storage_path('app/public/'.$logoPath))) {
                    $ext = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                    if (in_array($ext, self::RASTER_EXT, true)) {
                        $logoUrl = asset('storage/'.$logoPath);
                        $logoExt = $ext;
                    }
                }
            }
        } catch (\Throwable) {
            // settings belum ada (mis. saat migrate) — pakai default.
        }

        return [
            'nama' => $nama,
            'slug' => \Illuminate\Support\Str::slug($nama) ?: 'sims',
            'logoUrl' => $logoUrl,
            'logoExt' => $logoExt,
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    private static function icons(?string $logoUrl, ?string $logoExt): array
    {
        if ($logoUrl !== null) {
            $mime = $logoExt === 'jpg' || $logoExt === 'jpeg' ? 'image/jpeg' : 'image/'.($logoExt ?: 'png');

            return [
                ['src' => $logoUrl, 'sizes' => 'any', 'type' => $mime, 'purpose' => 'any'],
            ];
        }

        return [
            ['src' => '/icons/favicon-32.png', 'sizes' => '32x32', 'type' => 'image/png'],
            ['src' => '/icons/apple-touch-icon.png', 'sizes' => '180x180', 'type' => 'image/png'],
            ['src' => '/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/icons/icon-192-maskable.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => '/icons/icon-512-maskable.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];
    }
}
