<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug nyata (dilaporkan user via screenshot): membuka halaman kiosk absensi
 * (/absensi/scan?_kiosk=...) TANPA login sama sekali crash dgn
 * "ErrorException: Undefined variable $modulOn" di layouts/app.blade.php.
 *
 * Penyebab: $modulOn (closure ModulAktif::aktif) cuma didefinisikan DI DALAM blok
 * <aside> sidebar, yg dibungkus @unless($kioskChrome) — jadi tak pernah terdefinisi
 * di mode kiosk. Tapi initGrupBadge() (skrip badge Grup Chat, ditambahkan fitur
 * belakangan) memanggil $modulOn('grup_chat') di LUAR blok sidebar itu, jadi
 * meledak persis di mode kiosk yg justru dirancang bisa diakses TANPA login sama
 * sekali (lihat EnsureKioskOrPermission — kios publik divalidasi via token, bukan
 * Auth::login()). Perbaikan: definisi $modulOn dipindah ke luar blok sidebar,
 * sejajar dgn $access/$isAdmin yg sudah lebih dulu ditangani dgn cara yg sama.
 */
class KioskModulOnUndefinedTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_kiosk_scan_tanpa_login_tidak_crash(): void
    {
        Setting::set('kiosk_token', 'tok-modulon-regresi');

        $response = $this->get('/absensi/scan?_kiosk=tok-modulon-regresi');

        $response->assertOk();
    }

    public function test_halaman_kiosk_qr_tanpa_login_tidak_crash(): void
    {
        Setting::set('kiosk_token', 'tok-modulon-regresi-qr');
        Setting::set('cara_absensi_guru', 'barcode');

        $response = $this->get('/absensi/scan?_kiosk=tok-modulon-regresi-qr');

        $response->assertOk();
    }
}
