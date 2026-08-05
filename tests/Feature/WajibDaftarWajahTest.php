<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Toggle "Wajib Daftar Wajah" (Setting → Absensi) — mengatur apakah gate
 * EnsureFaceRegistered memaksa registrasi wajah sebelum akses halaman lain.
 * Default TETAP wajib (setting belum pernah diisi) supaya instalasi lama tidak
 * berubah perilakunya diam-diam begitu fitur ini dirilis.
 */
class WajibDaftarWajahTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'admin_wdw', 'password' => Hash::make('password'), 'access' => 'superadmin']);
    }

    private function siswaTanpaWajah(): User
    {
        $user = User::create(['username' => 'siswa_wdw', 'password' => Hash::make('password'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'nama' => 'Siswa WDW', 'nis' => 'WDW001', 'jk' => 'L']);
        return $user;
    }

    public function test_default_wajib_daftar_wajah_tanpa_setting(): void
    {
        $user = $this->siswaTanpaWajah();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('face.self'));
    }

    public function test_admin_bisa_matikan_wajib_daftar_wajah(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('setting.wajibDaftarWajah'), ['wajib_daftar_wajah' => '0'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('0', Setting::get('wajib_daftar_wajah'));
    }

    public function test_siswa_tanpa_wajah_tidak_diblokir_saat_setting_dimatikan(): void
    {
        Setting::set('wajib_daftar_wajah', '0');
        $user = $this->siswaTanpaWajah();

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_setting_dinyalakan_lagi_kembali_memblokir(): void
    {
        Setting::set('wajib_daftar_wajah', '0');
        $user = $this->siswaTanpaWajah();
        $this->actingAs($user)->get('/dashboard')->assertOk();

        Setting::set('wajib_daftar_wajah', '1');
        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('face.self'));
    }

    public function test_wajib_ganti_password_tetap_berlaku_walau_daftar_wajah_dimatikan(): void
    {
        Setting::set('wajib_daftar_wajah', '0');
        $user = User::create([
            'username' => 'siswa_wdw_pwd', 'password' => Hash::make('password'),
            'access' => 'siswa', 'must_change_password' => true,
        ]);
        Siswa::create(['id_login' => $user->uuid, 'nama' => 'Siswa WDW 2', 'nis' => 'WDW002', 'jk' => 'L']);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('ganti.password'));
    }

    public function test_orangtua_tetap_tidak_terdampak_setting_ini(): void
    {
        // Ortu selalu dikecualikan dari gate wajah, terlepas dari nilai setting ini.
        Setting::set('wajib_daftar_wajah', '1');
        $user = User::create(['username' => 'ortu_wdw', 'password' => Hash::make('password'), 'access' => 'orangtua']);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }
}
