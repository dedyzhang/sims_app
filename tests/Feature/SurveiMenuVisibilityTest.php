<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\RolePermission;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Support\ModeUjian;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SurveiMenuVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('wajib_daftar_wajah', '0');
        ModeUjian::nonaktifkan();
    }

    private function createUser(string $username, string $access): User
    {
        return User::create([
            'username' => $username,
            'password' => Hash::make('password'),
            'access'   => $access,
        ]);
    }

    public function test_admin_melihat_menu_survei_pembelajaran(): void
    {
        $admin = $this->createUser('admin_tester', 'admin');

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Survei Pembelajaran');
    }

    public function test_siswa_melihat_menu_survei_pembelajaran(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $siswaUser = $this->createUser('siswa_tester', 'siswa');
        Siswa::create([
            'id_login' => $siswaUser->uuid,
            'nama'     => 'Siswa Tester',
            'nis'      => '12345',
            'id_kelas' => $kelas->uuid,
            'jk'       => 'L',
        ]);

        $this->actingAs($siswaUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Survei Pembelajaran');
    }

    public function test_guru_biasa_tanpa_izin_tidak_melihat_menu_survei_pembelajaran(): void
    {
        $guruUser = $this->createUser('guru_biasa', 'guru');
        Guru::create([
            'id_login' => $guruUser->uuid,
            'nama'     => 'Guru Biasa',
        ]);

        $this->actingAs($guruUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Survei Pembelajaran');
    }

    public function test_role_yang_diberi_izin_view_survei_melihat_menu_survei_pembelajaran(): void
    {
        RolePermission::create([
            'role'       => 'guru',
            'permission' => 'view_survei',
        ]);

        $guruUser = $this->createUser('guru_rekap', 'guru');
        Guru::create([
            'id_login' => $guruUser->uuid,
            'nama'     => 'Guru Rekap',
        ]);

        $this->actingAs($guruUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Survei Pembelajaran');
    }

    public function test_role_yang_diberi_izin_jadi_surveyor_melihat_menu_survei_pembelajaran(): void
    {
        RolePermission::create([
            'role'       => 'kurikulum',
            'permission' => 'jadi_surveyor',
        ]);

        $kurikulum = $this->createUser('kurikulum_surveyor', 'kurikulum');

        $this->actingAs($kurikulum)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Survei Pembelajaran');
    }

    public function test_role_lain_tanpa_izin_seperti_tendik_dan_ortu_tidak_melihat_menu(): void
    {
        $tendik = $this->createUser('tendik_tester', 'tendik');
        $ortu = $this->createUser('ortu_tester', 'orangtua');

        $this->actingAs($tendik)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Survei Pembelajaran');

        $this->actingAs($ortu)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Survei Pembelajaran');
    }
}
