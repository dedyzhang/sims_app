<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Fitur Unduh Aplikasi: admin upload APK/installer, user mengunduh saat aktif. */
class AppDownloadTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $access, string $username): User
    {
        return User::create([
            'username' => $username,
            'password' => bcrypt('rahasia123'),
            'access'   => $access,
        ]);
    }

    public function test_admin_upload_apk_dan_aktifkan(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin', 'appdl_admin');

        $this->actingAs($admin)->post(route('setting.appDownload'), [
            'app_download_aktif' => '1',
            'app_apk'            => UploadedFile::fake()->create('sims.apk', 120),
            'app_apk_version'    => 'v1.0.0',
        ])->assertRedirect();

        $this->assertSame('1', Setting::get('app_download_aktif'));
        $path = Setting::get('app_apk_path');
        $this->assertNotEmpty($path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame('sims.apk', Setting::get('app_apk_name'));
    }

    public function test_upload_apk_tetap_berekstensi_apk_walau_mime_zip(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin', 'appdl_admin_zip_mime');

        $this->actingAs($admin)->post(route('setting.appDownload'), [
            'app_download_aktif' => '1',
            'app_apk'            => UploadedFile::fake()->create('sims-release.apk', 120, 'application/zip'),
        ])->assertRedirect();

        $path = Setting::get('app_apk_path');
        $this->assertNotEmpty($path);
        $this->assertStringEndsWith('.apk', $path);
        $this->assertStringNotContainsString('.zip', $path);
        Storage::disk('local')->assertExists($path);

        $siswa = $this->user('siswa', 'appdl_siswa_zip_mime');
        $response = $this->actingAs($siswa)->get(route('app.download.file', 'apk'));

        $response->assertOk();
        $response->assertDownload('sims-release.apk');
        $this->assertSame('application/vnd.android.package-archive', $response->headers->get('content-type'));
        $this->assertSame('nosniff', $response->headers->get('x-content-type-options'));
    }

    public function test_validasi_tolak_ekstensi_salah(): void
    {
        Storage::fake('local');
        $admin = $this->user('admin', 'appdl_admin2');

        $this->actingAs($admin)->post(route('setting.appDownload'), [
            'app_download_aktif' => '1',
            'app_apk'            => UploadedFile::fake()->create('virus.txt', 10),
        ])->assertSessionHasErrors('app_apk');
    }

    public function test_user_unduh_saat_aktif(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('app-downloads/apk_test.apk', 'DUMMYAPK');
        Setting::set('app_download_aktif', '1');
        Setting::set('app_apk_path', 'app-downloads/apk_test.apk');
        Setting::set('app_apk_name', 'sims.apk');

        $siswa = $this->user('siswa', 'appdl_siswa');

        $this->actingAs($siswa)->get(route('app.download'))
            ->assertOk()
            ->assertSee('Unduh Aplikasi');

        $this->actingAs($siswa)->get(route('app.download.file', 'apk'))
            ->assertOk()
            ->assertDownload('sims.apk');
    }

    /**
     * Saat fitur unduhan nonaktif, FILE tetap 404 — tapi halamannya tidak, karena
     * kartu "iPhone / iPad" (panduan pasang PWA) tak bergantung file unggahan dan
     * halaman ini satu-satunya jalur in-app menuju panduan itu.
     */
    public function test_halaman_tetap_terbuka_saat_nonaktif_tanpa_kartu_unduhan(): void
    {
        Setting::set('app_download_aktif', '0');
        $siswa = $this->user('siswa', 'appdl_siswa2');

        $this->actingAs($siswa)->get(route('app.download'))
            ->assertOk()
            ->assertSee('Aplikasi iPhone / iPad')
            ->assertDontSee('Aplikasi Android');

        $this->actingAs($siswa)->get(route('app.download.file', 'apk'))->assertNotFound();
    }

    /**
     * Halaman login (sebelum auth) juga bisa langsung unduh APK/installer — dipakai lewat
     * route TERPISAH (guest.app.download.file) yg SENGAJA di luar middleware 'auth', supaya
     * pengunjung yg belum punya akun pun bisa pasang aplikasinya duluan. Controller yg dipakai
     * SAMA (AppDownloadController::download()) — method itu sendiri tak pernah baca
     * auth()->user(), jadi aman diekspos publik jg.
     */
    public function test_tamu_belum_login_bisa_unduh_apk_lewat_route_publik(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('app-downloads/guest_apk_test.apk', 'DUMMYAPK');
        Setting::set('app_download_aktif', '1');
        Setting::set('app_apk_path', 'app-downloads/guest_apk_test.apk');
        Setting::set('app_apk_name', 'sims.apk');

        // Tanpa actingAs() sama sekali — benar-benar tamu.
        $this->get(route('guest.app.download.file', 'apk'))
            ->assertOk()
            ->assertDownload('sims.apk');
    }

    public function test_tamu_belum_login_bisa_unduh_windows_lewat_route_publik(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('app-downloads/guest_win_test.exe', 'DUMMYEXE');
        Setting::set('app_download_aktif', '1');
        Setting::set('app_windows_path', 'app-downloads/guest_win_test.exe');
        Setting::set('app_windows_name', 'sims-setup.exe');

        $this->get(route('guest.app.download.file', 'windows'))
            ->assertOk()
            ->assertDownload('sims-setup.exe');
    }

    public function test_route_publik_juga_404_saat_nonaktif(): void
    {
        Setting::set('app_download_aktif', '0');

        $this->get(route('guest.app.download.file', 'apk'))->assertNotFound();
    }

    /**
     * Manifest PWA dirender dari Pengaturan sekolah, bukan file statis milik satu
     * sekolah: satu basis kode dipakai banyak sekolah, dan manifest statis membuat
     * semuanya terpasang di layar utama dengan nama + ikon sekolah yang sama.
     */
    public function test_manifest_pwa_memakai_identitas_sekolah_dari_pengaturan(): void
    {
        Setting::set('nama_sekolah', 'SMP Uji Coba Manifest');

        $res = $this->get(route('pwa.manifest'))->assertOk();

        $this->assertSame('SMP Uji Coba Manifest', $res->json('name'));
        $this->assertStringNotContainsString('Maitreyawira', $res->getContent());
        $this->assertSame('/', $res->json('scope'));
    }

    /** Halaman mana pun menautkan manifest dinamis itu, bukan JSON statis sekolah tertentu. */
    public function test_halaman_login_menautkan_manifest_dinamis(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('manifest.webmanifest', false)
            ->assertDontSee('WebVIEW_SMP_MW_TPI.json', false);
    }

    public function test_tamu_bisa_buka_panduan_ios_tanpa_login(): void
    {
        Setting::set('app_download_aktif', '0');

        $this->get(route('guest.app.ios'))
            ->assertOk()
            ->assertSee('Safari')
            ->assertSee('Tambahkan ke Layar Utama')
            ->assertSee('service-worker.js', false);
    }

    public function test_ios_tidak_dianggap_platform_file_unduh(): void
    {
        $this->get('/unduh-aplikasi-tamu/ios')->assertOk();
        $this->get('/unduh-aplikasi-tamu/mac')->assertNotFound();
    }
}
