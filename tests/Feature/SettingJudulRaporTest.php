<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SettingJudulRaporTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'username' => 'test_admin',
            'password' => Hash::make('password'),
            'access'   => 'superadmin',
        ]);
    }

    public function test_admin_can_view_kop_rapor_page_with_judul_rapor_fields(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('setting.kopRapor'));

        $response->assertOk();
        $response->assertSee('Judul Rapor');
        $response->assertSee('name="judul_rapor"', false);
        $response->assertSee('name="subjudul_penjabaran"', false);
    }

    public function test_admin_can_save_and_update_judul_rapor(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->post(route('setting.kopRapor.save'), [
            'judul_rapor' => 'LAPORAN HASIL BELAJAR PESERTA DIDIK',
            'subjudul_penjabaran' => 'RINCIAN CAPAIAN NILAI',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Pengaturan kop surat dan judul rapor disimpan.');

        $this->assertSame('LAPORAN HASIL BELAJAR PESERTA DIDIK', Setting::get('judul_rapor'));
        $this->assertSame('RINCIAN CAPAIAN NILAI', Setting::get('subjudul_penjabaran'));
    }

    public function test_non_admin_cannot_update_judul_rapor(): void
    {
        $user = User::create([
            'username' => 'guru_biasa',
            'password' => Hash::make('password'),
            'access'   => 'guru',
        ]);

        $response = $this->actingAs($user)->post(route('setting.kopRapor.save'), [
            'judul_rapor' => 'JUDUL DITOLAK',
        ]);

        $response->assertForbidden();
    }

    public function test_cetak_rapor_uses_custom_judul_rapor_when_set(): void
    {
        $admin = $this->admin();

        Setting::set('judul_rapor', 'RAPOR SISWA MAITREYAWIRA');

        $sem = Semester::create([
            'tahun' => '2026/2027',
            'semester' => 1,
            'aktif' => true,
        ]);

        $kelas = Kelas::create([
            'tingkat' => 10,
            'kelas' => 'X 1',
        ]);

        $siswa = Siswa::create([
            'id_kelas' => $kelas->uuid,
            'nis' => '12345',
            'nama' => 'Budi Santoso',
            'jk' => 'L',
        ]);

        $response = $this->actingAs($admin)->get(route('cetak.rapor', [
            'kelas' => $kelas->uuid,
            'siswa' => $siswa->uuid,
        ]));

        $response->assertOk();
        $response->assertSee('RAPOR SISWA MAITREYAWIRA');
    }

    public function test_cetak_rapor_uses_default_when_judul_rapor_is_empty(): void
    {
        $admin = $this->admin();

        Setting::set('judul_rapor', '');

        $sem = Semester::create([
            'tahun' => '2026/2027',
            'semester' => 1,
            'aktif' => true,
        ]);

        $kelas = Kelas::create([
            'tingkat' => 10,
            'kelas' => 'X 1',
        ]);

        $siswa = Siswa::create([
            'id_kelas' => $kelas->uuid,
            'nis' => '12345',
            'nama' => 'Budi Santoso',
            'jk' => 'L',
        ]);

        $response = $this->actingAs($admin)->get(route('cetak.rapor', [
            'kelas' => $kelas->uuid,
            'siswa' => $siswa->uuid,
        ]));

        $response->assertOk();
        $response->assertSee('PENCAPAIAN KOMPETENSI PESERTA DIDIK');
    }
}
