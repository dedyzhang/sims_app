<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Support\Facades\Storage;

/*
| Halaman "Unduh Aplikasi" untuk SEMUA pengguna yang login. File (APK/EXE/MSI)
| diunggah admin lewat SettingController::setAppDownload dan disimpan di disk
| privat `local`. Unduhan hanya lewat route ber-auth ini — file tidak bisa
| diakses langsung via URL publik. Fitur muncul hanya bila diaktifkan admin.
|
| iOS tidak punya file .ipa di sini: PWA (Safari → Tambahkan ke Layar Utama).
| Route /unduh-aplikasi-tamu/ios selalu publik dan tidak bergantung Setting APK.
*/
class AppDownloadController extends Controller
{
    /** Peta platform → key path/nama/versi di tabel Setting. */
    private const PLATFORMS = [
        'apk'     => ['path' => 'app_apk_path',     'name' => 'app_apk_name',     'version' => 'app_apk_version'],
        'windows' => ['path' => 'app_windows_path', 'name' => 'app_windows_name', 'version' => 'app_windows_version'],
    ];

    /** GET /unduh-aplikasi-tamu/ios — panduan pasang PWA, tanpa file unduhan. */
    public function ios()
    {
        return response()
            ->view('guest.ios')
            ->header('Cache-Control', 'public, max-age=600');
    }

    /*
    | Halaman daftar aplikasi. TIDAK lagi digerbangi app_download_aktif: kartu
    | "iPhone / iPad" (panduan pasang PWA) selalu dirender oleh view dan tidak
    | butuh file unggahan apa pun. Gerbang lama membuat sekolah tanpa APK 404 di
    | satu-satunya jalur in-app menuju panduan iOS, padahal halaman login sudah
    | menautkan guest.app.ios tanpa syarat. Flag hanya menentukan APK/Windows.
    */
    public function page()
    {
        $apps = [];
        if (Setting::get('app_download_aktif') !== '1') {
            return view('app-download.index', compact('apps'));
        }

        foreach (self::PLATFORMS as $key => $meta) {
            $path = Setting::get($meta['path']);
            if ($path && Storage::disk('local')->exists($path)) {
                $apps[$key] = [
                    'name'    => Setting::get($meta['name']) ?: basename($path),
                    'version' => Setting::get($meta['version']) ?: null,
                    'size'    => Storage::disk('local')->size($path),
                ];
            }
        }

        return view('app-download.index', compact('apps'));
    }

    /** Streaming unduhan file satu platform (apk|windows). */
    public function download(string $platform)
    {
        abort_unless(Setting::get('app_download_aktif') === '1', 404);
        abort_unless(isset(self::PLATFORMS[$platform]), 404);

        $meta = self::PLATFORMS[$platform];
        $path = Setting::get($meta['path']);
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        $downloadName = Setting::get($meta['name']) ?: basename($path);
        $headers = [];

        if ($platform === 'apk') {
            if (! str_ends_with(strtolower($downloadName), '.apk')) {
                $downloadName .= '.apk';
            }

            $headers = [
                'Content-Type' => 'application/vnd.android.package-archive',
                'X-Content-Type-Options' => 'nosniff',
            ];
        }

        return Storage::disk('local')->download($path, $downloadName, $headers);
    }
}
