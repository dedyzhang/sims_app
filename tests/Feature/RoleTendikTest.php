<?php

namespace Tests\Feature;

use App\Http\Controllers\GuruController;
use App\Http\Controllers\KartuGuruController;
use App\Models\Guru;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\User;
use App\Support\Forum;
use App\Support\TickerStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleTendikTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::create([
            'username' => 'admin_tendik_test',
            'password' => Hash::make('password'),
            'access'   => 'superadmin',
        ]);
    }

    public function test_role_tendik_terdaftar_di_setting_roles_matrix(): void
    {
        $admin = $this->superadmin();

        $response = $this->actingAs($admin)->get(route('setting.roles'));
        $response->assertOk();
        $response->assertSee('tendik');
    }

    public function test_admin_bisa_menyimpan_hak_akses_untuk_role_tendik(): void
    {
        $admin = $this->superadmin();

        $response = $this->actingAs($admin)->post(route('setting.roles.save'), [
            'perms' => [
                'tendik' => [
                    'manage_sarpras' => 1,
                    'manage_absensi' => 1,
                    'manage_users'   => 1,
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('role_permissions', ['role' => 'tendik', 'permission' => 'manage_sarpras']);
        $this->assertDatabaseHas('role_permissions', ['role' => 'tendik', 'permission' => 'manage_absensi']);
        $this->assertDatabaseHas('role_permissions', ['role' => 'tendik', 'permission' => 'manage_users']);

        $tendikUser = User::create([
            'username' => 'staf_tendik_1',
            'password' => Hash::make('password'),
            'access'   => 'tendik',
        ]);

        $this->assertTrue($tendikUser->canAccess('manage_sarpras'));
        $this->assertTrue($tendikUser->canAccess('manage_absensi'));
        $this->assertTrue($tendikUser->canAccess('manage_users'));
        $this->assertFalse($tendikUser->canAccess('manage_keuangan'));
    }

    public function test_tendik_tanpa_izin_hak_akses_dibatasi(): void
    {
        $tendikUser = User::create([
            'username' => 'staf_tendik_polos',
            'password' => Hash::make('password'),
            'access'   => 'tendik',
        ]);

        // Tanpa entri role_permissions, tendik tidak punya izin apapun
        $this->assertFalse($tendikUser->canAccess('manage_users'));
        $this->assertFalse($tendikUser->canAccess('manage_absensi'));
        $this->assertFalse($tendikUser->canAccess('manage_sarpras'));
        $this->assertFalse($tendikUser->canAccess('manage_keuangan'));
    }

    public function test_middleware_permission_mengizinkan_tendik_sesuai_hak_akses(): void
    {
        $admin = $this->superadmin();

        // 1. Berikan izin manage_absensi ke tendik
        $this->actingAs($admin)->post(route('setting.roles.save'), [
            'perms' => [
                'tendik' => ['manage_absensi' => 1],
            ],
        ]);

        $tendikUser = User::create([
            'username' => 'tendik_absensi',
            'password' => Hash::make('password'),
            'access'   => 'tendik',
        ]);

        // Akses route yg dilindungi permission:manage_absensi
        $this->actingAs($tendikUser)->get(route('presensi-guru.index'))
            ->assertOk();

        // Akses route yg dilindungi permission:manage_settings (harus 403)
        $this->actingAs($tendikUser)->get(route('setting.index'))
            ->assertForbidden();
    }

    public function test_role_tendik_bisa_diatur_lewat_guru_controller(): void
    {
        $admin = $this->superadmin();

        $this->assertArrayHasKey('tendik', GuruController::ROLES);
        $this->assertSame('Tendik', GuruController::ROLES['tendik']);

        // Buat guru/staf baru
        $guru = Guru::create([
            'nama' => 'Budi Staf Tendik',
            'jk'   => 'L',
            'nik'  => '1234567890123456',
        ]);
        $user = User::create([
            'username' => 'budi.tendik',
            'password' => Hash::make('password'),
            'access'   => 'guru',
        ]);
        $guru->update(['id_login' => $user->uuid]);

        // Update role menjadi tendik
        $response = $this->actingAs($admin)->put(route('guru.update', $guru->uuid), [
            'nama'   => 'Budi Staf Tendik',
            'jk'     => 'L',
            'nik'    => '1234567890123456',
            'access' => 'tendik',
        ]);

        $response->assertRedirect();
        $this->assertSame('tendik', $user->fresh()->access);
        $this->assertSame('Tendik', $user->fresh()->roleLabel());
    }

    public function test_tendik_masuk_dalam_ticker_dan_kartu(): void
    {
        $user = User::create([
            'username'     => 'tendik_online',
            'password'     => Hash::make('password'),
            'access'       => 'tendik',
        ]);
        $user->forceFill(['last_seen_at' => now()])->save();

        TickerStats::clear();
        $flags = TickerStats::flags('tendik');
        $this->assertTrue($flags['teacher']);
        $this->assertTrue($flags['online']);

        $stats = TickerStats::raw();
        $this->assertGreaterThanOrEqual(1, $stats['onlineGuru']);
    }

    public function test_tendik_terdaftar_di_forum_role_labels_dan_defaults(): void
    {
        $this->assertArrayHasKey('tendik', Forum::ROLE_LABELS);
        $this->assertSame('Tendik', Forum::ROLE_LABELS['tendik']);
        $this->assertArrayHasKey('tendik', Forum::DEFAULTS);
        $this->assertSame(['umum', 'pengumuman'], Forum::categoryScope('tendik'));
    }
}
