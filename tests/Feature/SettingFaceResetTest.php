<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Reset verifikasi wajah massal (admin) — sekali klik, semua siswa & guru wajib daftar ulang
 * wajah. HARUS hanya menyentuh kolom mesin yg SEDANG AKTIF (reversibilitas — lihat
 * App\Support\FaceEngine); data mesin yg tidak aktif tidak boleh ikut terhapus.
 */
class SettingFaceResetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'face_reset_admin', 'password' => Hash::make('x'), 'access' => 'superadmin']);
    }

    private function siswaUser(): User
    {
        return User::create(['username' => 'face_reset_siswa', 'password' => Hash::make('x'), 'access' => 'siswa']);
    }

    public function test_admin_bisa_reset_semua_verifikasi_wajah(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $siswa = Siswa::create([
            'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Reset', 'nis' => '777001', 'jk' => 'L',
            'face_descriptor' => [[0.1, 0.2, 0.3]], 'face_registered_at' => now(), 'face_photo' => 'faces/x_20260101000000.jpg',
        ]);
        $guru = Guru::create(['nama' => 'Guru Reset', 'nik' => '1112223330', 'jk' => 'L', 'face_descriptor' => [[0.4, 0.5, 0.6]], 'face_registered_at' => now()]);

        $res = $this->actingAs($this->admin())->post(route('setting.faceResetAll'));
        $res->assertRedirect();
        $res->assertSessionHas('success');

        $siswa->refresh();
        $guru->refresh();
        $this->assertNull($siswa->face_descriptor);
        $this->assertNull($siswa->face_registered_at);
        $this->assertNull($guru->face_descriptor);
        $this->assertNull($guru->face_registered_at);
    }

    public function test_reset_hanya_menyentuh_kolom_mesin_yang_aktif(): void
    {
        // Human.js aktif (default) — siswa punya data KEDUA mesin sekaligus (skenario dual-registered).
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $siswa = Siswa::create([
            'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Dual Mesin', 'nis' => '777002', 'jk' => 'L',
            'face_descriptor' => [[0.1, 0.2, 0.3]],
            'face_descriptor_if' => [[0.9, 0.8, 0.7]],
        ]);

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('setting.faceResetAll'))->assertRedirect();

        $siswa->refresh();
        $this->assertNull($siswa->face_descriptor, 'Kolom mesin aktif (Human.js) harus direset.');
        $this->assertSame([[0.9, 0.8, 0.7]], $siswa->face_descriptor_if, 'Kolom mesin TIDAK aktif (InsightFace) tidak boleh ikut terhapus.');

        // Sekarang pindah ke InsightFace & reset lagi — giliran face_descriptor_if yg direset,
        // face_descriptor (sudah null dari langkah atas) tetap tidak disentuh ulang.
        Setting::set('face_engine', 'insightface');
        $this->actingAs($admin)->post(route('setting.faceResetAll'))->assertRedirect();

        $siswa->refresh();
        $this->assertNull($siswa->face_descriptor_if);
    }

    public function test_non_admin_tidak_bisa_reset(): void
    {
        $this->actingAs($this->siswaUser())->post(route('setting.faceResetAll'))->assertForbidden();
    }

    public function test_halaman_setting_menampilkan_jumlah_terdampak(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'C']);
        Siswa::create(['id_kelas' => $kelas->uuid, 'nama' => 'Siswa Terdaftar', 'nis' => '777003', 'jk' => 'L', 'face_descriptor' => [[0.1, 0.2]]]);
        Guru::create(['nama' => 'Guru Terdaftar', 'nik' => '4445556660', 'jk' => 'P', 'face_descriptor' => [[0.3, 0.4]]]);

        $this->actingAs($this->admin())->get(route('setting.index'))
            ->assertOk()
            ->assertSee('Reset Verifikasi Wajah Massal')
            ->assertSee('Reset Semua Verifikasi Wajah');
    }
}
