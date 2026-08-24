<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\RolePermission;
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

    /**
     * Bug report FL: staf kurikulum yg KEBETULAN juga py profil Guru + penugasan mengajar
     * sendiri (dual-role, pola sama spt UjianPolicy::create()) harus lihat penugasannya
     * sendiri dulu di section "Data Ngajar Saya", terpisah dari daftar lengkap semua
     * pelajaran/tingkat ("Semua Data Ngajar") — bukan cuma salah satu.
     */
    public function test_kurikulum_dual_role_lihat_ngajar_sendiri_dan_semua_data(): void
    {
        RolePermission::create(['role' => 'kurikulum', 'permission' => 'view_all_nilai']);

        [$userKurikulum, $guruKurikulum] = $this->buatGuru('kurikulumguru', '3333333333');
        $userKurikulum->update(['access' => 'kurikulum']);
        $ngajarSendiri = $this->ngajarMilik($guruKurikulum);

        [, $guruLain] = $this->buatGuru('gurulain', '4444444444');
        $kelasLain = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);
        $ngajarLain = Ngajar::create([
            'id_guru' => $guruLain->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $kelasLain->uuid,
        ]);

        $response = $this->actingAs($userKurikulum)->get('/nilai');

        $response->assertOk();
        $response->assertViewHas('ngajarsSaya', fn ($list) => $list->count() === 1 && $list->first()->uuid === $ngajarSendiri->uuid);
        $response->assertViewHas('ngajars', fn ($list) => $list->count() === 2 && $list->pluck('uuid')->contains($ngajarLain->uuid));
        $response->assertViewHas('canViewAll', true);
        $response->assertSee('Data Ngajar Saya');
        $response->assertSee('Semua Data Ngajar');
    }

    /** Guru biasa (view_all_nilai TIDAK dimiliki) tak boleh dapat section "Data Ngajar Saya" — cukup daftar biasa spt sebelumnya. */
    public function test_guru_biasa_tidak_dapat_section_ngajar_saya_terpisah(): void
    {
        [$user, $guru] = $this->buatGuru('gurubiasa', '5555555555');
        $this->ngajarMilik($guru);

        $response = $this->actingAs($user)->get('/nilai');

        $response->assertOk();
        $response->assertViewHas('ngajarsSaya', fn ($list) => $list->isEmpty());
        $response->assertDontSee('Data Ngajar Saya');
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        [, $guruA] = $this->buatGuru('gurua', '1111111111');
        $ngajarA = $this->ngajarMilik($guruA);

        $this->post("/nilai/{$ngajarA->uuid}/materi", ['nama' => 'X'])
            ->assertRedirect(route('login'));
    }
}
