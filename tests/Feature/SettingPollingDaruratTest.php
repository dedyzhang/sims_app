<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mode darurat hemat server (Setting::polling_darurat_aktif) — toggle admin yg menonaktifkan
 * SEMUA polling widget latar belakang (window.simsPollInterval non-essential) lewat satu flag
 * global window.SIMS_HEMAT_POLLING, TANPA menyentuh polling fitur inti (ujian berjalan,
 * pemantauan ruangan ujian, Arena Belajar Live/Latihan — dipanggil dgn essential=true).
 */
class SettingPollingDaruratTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
    }

    private function admin(): User
    {
        return User::create([
            'username' => 'polling_darurat_admin',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);
    }

    private function siswaUser(): User
    {
        return User::create([
            'username' => 'polling_darurat_siswa',
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
    }

    public function test_admin_bisa_nyalakan_dan_matikan_mode_darurat(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('setting.pollingDarurat'), [
            'polling_darurat_aktif' => '1',
        ]);
        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertSame('1', Setting::get('polling_darurat_aktif'));

        // Checkbox tak terkirim sama sekali saat unchecked (bukan '0') — pola form toggle app ini.
        $this->actingAs($admin)->post(route('setting.pollingDarurat'), [])->assertRedirect();
        $this->assertSame('0', Setting::get('polling_darurat_aktif'));
    }

    public function test_non_admin_tidak_bisa_ubah_mode_darurat(): void
    {
        $siswa = $this->siswaUser();

        $this->actingAs($siswa)->post(route('setting.pollingDarurat'), [
            'polling_darurat_aktif' => '1',
        ])->assertForbidden();

        $this->assertNull(Setting::get('polling_darurat_aktif'));
    }

    public function test_flag_global_di_layout_ikut_nilai_setting(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('setting.index'))
            ->assertOk()
            ->assertSee('window.SIMS_HEMAT_POLLING = false', false);

        Setting::set('polling_darurat_aktif', '1');

        $this->actingAs($admin)->get(route('setting.index'))
            ->assertOk()
            ->assertSee('window.SIMS_HEMAT_POLLING = true', false);
    }
}
