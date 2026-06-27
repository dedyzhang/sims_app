<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test otorisasi penilaian: guru hanya boleh menilai penugasan
 * (ngajar) miliknya sendiri; admin boleh semua. Menguji NilaiController::ngajarOrAbort
 * lewat endpoint POST materiStore (redirect, tanpa render view).
 */
class NilaiAccessTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        Semester::create(['semester' => 1, 'tahun' => '2024/2025', 'aktif' => true]);
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'ringkasan' => 'MTK', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
    }

    /** Buat user guru lengkap dengan profil Guru (wajah terdaftar agar lolos gate). */
    private function buatGuru(string $username, string $nik): array
    {
        $user = User::create([
            'username' => $username,
            'password' => 'rahasia123',
            'access'   => 'guru',
        ]);
        $guru = Guru::create([
            'id_login'        => $user->uuid,
            'nama'            => ucfirst($username),
            'nik'             => $nik,
            'jk'              => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        return [$user, $guru];
    }

    private function ngajarMilik(Guru $guru): Ngajar
    {
        return Ngajar::create([
            'id_guru'      => $guru->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas'     => $this->kelas->uuid,
        ]);
    }

    public function test_guru_pemilik_boleh_menambah_materi_di_penugasannya(): void
    {
        [$user, $guru] = $this->buatGuru('gurua', '1111111111');
        $ngajar = $this->ngajarMilik($guru);

        $response = $this->actingAs($user)->post("/nilai/{$ngajar->uuid}/materi", [
            'nama' => 'Bab 1 Bilangan',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('materi', [
            'id_ngajar' => $ngajar->uuid,
            'nama'      => 'Bab 1 Bilangan',
        ]);
    }

    public function test_guru_lain_dilarang_menambah_materi_pada_penugasan_bukan_miliknya(): void
    {
        [, $guruA] = $this->buatGuru('gurua', '1111111111');
        [$userB] = $this->buatGuru('gurub', '2222222222');
        $ngajarA = $this->ngajarMilik($guruA);

        $response = $this->actingAs($userB)->post("/nilai/{$ngajarA->uuid}/materi", [
            'nama' => 'Materi Sabotase',
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseMissing('materi', ['nama' => 'Materi Sabotase']);
    }

    public function test_admin_boleh_menambah_materi_pada_penugasan_guru_manapun(): void
    {
        [, $guruA] = $this->buatGuru('gurua', '1111111111');
        $ngajarA = $this->ngajarMilik($guruA);

        $admin = User::create([
            'username' => 'adminx',
            'password' => 'rahasia123',
            'access'   => 'admin',
        ]);

        $response = $this->actingAs($admin)->post("/nilai/{$ngajarA->uuid}/materi", [
            'nama' => 'Bab Admin',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('materi', [
            'id_ngajar' => $ngajarA->uuid,
            'nama'      => 'Bab Admin',
        ]);
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        [, $guruA] = $this->buatGuru('gurua', '1111111111');
        $ngajarA = $this->ngajarMilik($guruA);

        $this->post("/nilai/{$ngajarA->uuid}/materi", ['nama' => 'X'])
            ->assertRedirect(route('login'));
    }
}
