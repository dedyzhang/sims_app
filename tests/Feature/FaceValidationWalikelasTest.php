<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class FaceValidationWalikelasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'username' => 'validasi_admin',
            'password' => Hash::make('password'),
            'access'   => 'superadmin',
        ]);
    }

    /** Guru biasa (bukan walikelas, tanpa manage_absensi) — wajib sudah daftar wajah agar tak kena redirect EnsureFaceRegistered. */
    private function guruBiasa(): User
    {
        $user = User::create([
            'username' => 'validasi_guru_biasa',
            'password' => Hash::make('password'),
            'access'   => 'guru',
        ]);
        Guru::create([
            'id_login' => $user->getKey(),
            'nama'     => 'Guru Biasa',
            'nik'      => 'VALGURU001',
            'face_descriptor' => [array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63))],
        ]);

        return $user;
    }

    /** Guru yang jadi wali kelas $kelas. */
    private function walikelasUser(Kelas $kelas): User
    {
        $user = User::create([
            'username' => 'validasi_wali_' . $kelas->uuid,
            'password' => Hash::make('password'),
            'access'   => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $user->getKey(),
            'nama'     => 'Wali Kelas Validasi',
            'nik'      => 'VALWALI' . $kelas->uuid,
            'face_descriptor' => [array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63))],
        ]);
        Walikelas::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid]);

        return $user;
    }

    private function constVec(float $v = 1.0): array
    {
        return array_fill(0, 64, $v);
    }

    /** Vektor alternating +1/-1 — arahnya jauh dari constVec(), dipakai spy "klaster lain". */
    private function altVec(): array
    {
        return array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63));
    }

    public function test_walikelas_bisa_akses_halaman_validasi_wajah(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $wali = $this->walikelasUser($kelas);

        $this->actingAs($wali)->get(route('wajah.galeri'))->assertOk();
        $this->actingAs($wali)->get(route('wajah.ganda'))->assertOk();
        $this->actingAs($wali)->get(route('wajah.takTerbaca'))->assertOk();
    }

    public function test_guru_biasa_tanpa_walikelas_ditolak_403(): void
    {
        $guru = $this->guruBiasa();

        $this->actingAs($guru)->get(route('wajah.galeri'))->assertForbidden();
        $this->actingAs($guru)->get(route('wajah.ganda'))->assertForbidden();
        $this->actingAs($guru)->get(route('wajah.takTerbaca'))->assertForbidden();
    }

    public function test_galeri_walikelas_hanya_tampilkan_kelasnya_sendiri(): void
    {
        $kelasSaya = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'C']);
        $wali = $this->walikelasUser($kelasSaya);

        Siswa::create(['nama' => 'Siswa Kelas Saya', 'nis' => 'VAL001', 'jk' => 'L', 'id_kelas' => $kelasSaya->uuid, 'face_descriptor' => [$this->constVec()]]);
        Siswa::create(['nama' => 'Siswa Kelas Lain', 'nis' => 'VAL002', 'jk' => 'L', 'id_kelas' => $kelasLain->uuid, 'face_descriptor' => [$this->constVec()]]);

        $response = $this->actingAs($wali)->get(route('wajah.galeri'));

        $response->assertOk()
            ->assertSee('Siswa Kelas Saya')
            ->assertDontSee('Siswa Kelas Lain');
    }

    public function test_wajah_ganda_walikelas_hanya_pasangan_yg_melibatkan_kelasnya(): void
    {
        $kelasSaya = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        $kelasLain = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);
        $wali = $this->walikelasUser($kelasSaya);

        // Pasangan ganda melibatkan siswa kelas SAYA — harus terlihat.
        Siswa::create(['nama' => 'Siswa Ganda A', 'nis' => 'VAL010', 'jk' => 'L', 'id_kelas' => $kelasSaya->uuid, 'face_descriptor' => [$this->constVec(1.0)]]);
        Siswa::create(['nama' => 'Siswa Ganda B', 'nis' => 'VAL011', 'jk' => 'L', 'id_kelas' => $kelasSaya->uuid, 'face_descriptor' => [$this->constVec(1.0)]]);

        // Pasangan ganda di kelas LAIN sepenuhnya (vektor beda arah dari klaster di atas, supaya
        // tak ikut match dgn A/B) — TIDAK boleh terlihat oleh wali kelas saya.
        Siswa::create(['nama' => 'Siswa Ganda C', 'nis' => 'VAL012', 'jk' => 'L', 'id_kelas' => $kelasLain->uuid, 'face_descriptor' => [$this->altVec()]]);
        Siswa::create(['nama' => 'Siswa Ganda D', 'nis' => 'VAL013', 'jk' => 'L', 'id_kelas' => $kelasLain->uuid, 'face_descriptor' => [$this->altVec()]]);

        $response = $this->actingAs($wali)->get(route('wajah.ganda', ['min' => 0.9]));

        $response->assertOk()
            ->assertSee('Siswa Ganda A')
            ->assertSee('Siswa Ganda B')
            ->assertDontSee('Siswa Ganda C')
            ->assertDontSee('Siswa Ganda D');
    }

    public function test_wajah_tak_terbaca_walikelas_hanya_kelasnya_sendiri(): void
    {
        $kelasSaya = Kelas::create(['tingkat' => 9, 'kelas' => 'A']);
        $kelasLain = Kelas::create(['tingkat' => 9, 'kelas' => 'B']);
        $wali = $this->walikelasUser($kelasSaya);

        Siswa::create(['nama' => 'Siswa Rusak Kelas Saya', 'nis' => 'VAL020', 'jk' => 'L', 'id_kelas' => $kelasSaya->uuid, 'face_descriptor' => []]);
        Siswa::create(['nama' => 'Siswa Rusak Kelas Lain', 'nis' => 'VAL021', 'jk' => 'L', 'id_kelas' => $kelasLain->uuid, 'face_descriptor' => []]);

        $response = $this->actingAs($wali)->get(route('wajah.takTerbaca'));

        $response->assertOk()
            ->assertSee('Siswa Rusak Kelas Saya')
            ->assertDontSee('Siswa Rusak Kelas Lain');
    }

    public function test_admin_tetap_melihat_semua_kelas(): void
    {
        $kelasA = Kelas::create(['tingkat' => 7, 'kelas' => 'X']);
        $kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'Y']);
        Siswa::create(['nama' => 'Siswa X', 'nis' => 'VAL030', 'jk' => 'L', 'id_kelas' => $kelasA->uuid, 'face_descriptor' => []]);
        Siswa::create(['nama' => 'Siswa Y', 'nis' => 'VAL031', 'jk' => 'L', 'id_kelas' => $kelasB->uuid, 'face_descriptor' => []]);

        $response = $this->actingAs($this->admin())->get(route('wajah.takTerbaca'));

        $response->assertOk()->assertSee('Siswa X')->assertSee('Siswa Y');
    }
}
