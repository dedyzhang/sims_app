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

    public function test_halaman_404_saat_nonaktif(): void
    {
        Setting::set('app_download_aktif', '0');
        $siswa = $this->user('siswa', 'appdl_siswa2');

        $this->actingAs($siswa)->get(route('app.download'))->assertNotFound();
        $this->actingAs($siswa)->get(route('app.download.file', 'apk'))->assertNotFound();
    }
}
