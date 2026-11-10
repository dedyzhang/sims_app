<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingTampilanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'SMP Test']);
    }

    private function admin(): User
    {
        return User::create([
            'username' => 'tampilan_admin',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);
    }

    private function siswa(): User
    {
        return User::create([
            'username' => 'tampilan_siswa',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
    }

    public function test_default_ticker_dan_animasi_fade_aktif_jika_belum_diset(): void
    {
        $admin = $this->admin();

        $this->assertSame('1', Setting::get('ticker_bawah_aktif', '1'));
        $this->assertSame('1', Setting::get('animasi_fade_aktif', '1'));

        $response = $this->actingAs($admin)->get(route('setting.index'));
        $response->assertOk();

        // Ticker bar dirender
        $response->assertSee('data-sims-ticker', false);
        $response->assertSee('SIMS-NET', false);
        $response->assertSee('ticker-val-semester', false);

        // Animasi fade-in disertakan
        $response->assertSee('anim-fade', false);
        $response->assertSee('animation:fadeUp', false);
    }

    public function test_admin_dapat_menonaktifkan_ticker_dan_animasi_fade(): void
    {
        $admin = $this->admin();

        // Uncheck ticker dan animasi fade
        $response = $this->actingAs($admin)->post(route('setting.tampilan'), [
            'tab' => 'sekolah',
        ]);

        $response->assertRedirect(route('setting.index', ['tab' => 'sekolah']));
        $response->assertSessionHas('success');

        $this->assertSame('0', Setting::get('ticker_bawah_aktif'));
        $this->assertSame('0', Setting::get('animasi_fade_aktif'));

        // Cek halaman setelah dinonaktifkan
        $viewResponse = $this->actingAs($admin)->get(route('setting.index'));
        $viewResponse->assertOk();

        // Ticker bar tidak dirender sama sekali
        $viewResponse->assertDontSee('data-sims-ticker', false);
        $viewResponse->assertDontSee("window.simsPollInterval(updateTickerStats, 30000, 'ticker');", false);

        // Animasi fade dinonaktifkan
        $viewResponse->assertSee('.anim-fade { animation:none !important; }', false);
        $viewResponse->assertDontSee('.anim-fade { animation:fadeUp', false);
        $viewResponse->assertDontSee('class="anim-fade', false);
    }

    public function test_admin_dapat_mengaktifkan_kembali_ticker_dan_animasi_fade(): void
    {
        $admin = $this->admin();

        // Mula-mula nonaktif
        Setting::set('ticker_bawah_aktif', '0');
        Setting::set('animasi_fade_aktif', '0');

        $response = $this->actingAs($admin)->post(route('setting.tampilan'), [
            'ticker_bawah_aktif' => '1',
            'animasi_fade_aktif' => '1',
            'tab' => 'sekolah',
        ]);

        $response->assertRedirect(route('setting.index', ['tab' => 'sekolah']));
        $response->assertSessionHas('success');

        $this->assertSame('1', Setting::get('ticker_bawah_aktif'));
        $this->assertSame('1', Setting::get('animasi_fade_aktif'));

        // Cek kembali halaman
        $viewResponse = $this->actingAs($admin)->get(route('setting.index'));
        $viewResponse->assertOk();
        $viewResponse->assertSee('data-sims-ticker', false);
        $viewResponse->assertSee('animation:fadeUp', false);
    }

    public function test_user_tanpa_permission_manage_settings_dilarang_mengubah_tampilan(): void
    {
        $siswa = $this->siswa();

        $response = $this->actingAs($siswa)->post(route('setting.tampilan'), [
            'ticker_bawah_aktif' => '0',
            'animasi_fade_aktif' => '0',
        ]);

        $response->assertForbidden();
        // Setting bawaan tetap tidak berubah
        $this->assertSame('1', Setting::get('ticker_bawah_aktif', '1'));
        $this->assertSame('1', Setting::get('animasi_fade_aktif', '1'));
    }
}
