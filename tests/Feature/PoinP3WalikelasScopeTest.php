<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\RolePermission;
use App\Models\Sekretaris;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Regresi: user yang PUNYA DUA peran sekaligus (kesiswaan + wali kelas) sebelumnya melihat
 * SEMUA siswa se-sekolah di halaman "Poin Siswa Kelas"/"P3 Siswa Kelas" (menu Wali Kelas),
 * karena PoinController/P3Controller mengutamakan izin manage_disiplin (kesiswaan) di atas
 * status wali kelasnya sendiri. Wali kelas dgn peran lain harus tetap melihat kelasnya SAJA,
 * sama seperti wali kelas lain — akses "semua siswa" tetap ada, tapi lewat menu Poin & Aturan
 * (bukan menu Wali Kelas) untuk user yang BUKAN wali kelas.
 */
class PoinP3WalikelasScopeTest extends TestCase
{
    use RefreshDatabase;

    private function dualRoleWalikelas(Kelas $kelas): User
    {
        $user = User::create([
            'username' => 'kesiswaan_wali_' . $kelas->uuid,
            'password' => Hash::make('password'),
            'access'   => 'kesiswaan',
        ]);
        $guru = Guru::create([
            'id_login'        => $user->getKey(),
            'nama'            => 'Kesiswaan Sekaligus Wali',
            'nik'             => 'KSW' . $kelas->uuid,
            'face_descriptor' => [array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63))],
        ]);
        Walikelas::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid]);
        RolePermission::firstOrCreate(['role' => 'kesiswaan', 'permission' => 'manage_disiplin']);

        return $user;
    }

    public function test_poin_siswa_index_wali_kelas_kesiswaan_hanya_lihat_kelasnya(): void
    {
        $kelasSaya = Kelas::create(['tingkat' => 7, 'kelas' => 'G']);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'H']);
        $user = $this->dualRoleWalikelas($kelasSaya);

        $siswaSaya = Siswa::create(['nama' => 'Siswa Kelas Saya Poin', 'nis' => 'POIN001', 'jk' => 'L', 'id_kelas' => $kelasSaya->uuid]);
        $siswaLain = Siswa::create(['nama' => 'Siswa Kelas Lain Poin', 'nis' => 'POIN002', 'jk' => 'P', 'id_kelas' => $kelasLain->uuid]);

        $response = $this->actingAs($user)->get(route('poin.siswa.index'))->assertOk();
        $response->assertSee('Siswa Kelas Saya Poin');
        $response->assertDontSee('Siswa Kelas Lain Poin');

        $this->actingAs($user)->get(route('poin.siswa.show', $siswaSaya))->assertOk();
        $this->actingAs($user)->get(route('poin.siswa.show', $siswaLain))->assertForbidden();
    }

    public function test_p3_siswa_index_wali_kelas_kesiswaan_hanya_lihat_kelasnya(): void
    {
        $kelasSaya = Kelas::create(['tingkat' => 8, 'kelas' => 'G']);
        $kelasLain = Kelas::create(['tingkat' => 8, 'kelas' => 'H']);
        $user = $this->dualRoleWalikelas($kelasSaya);

        $siswaSaya = Siswa::create(['nama' => 'Siswa Kelas Saya P3', 'nis' => 'P3001', 'jk' => 'L', 'id_kelas' => $kelasSaya->uuid]);
        $siswaLain = Siswa::create(['nama' => 'Siswa Kelas Lain P3', 'nis' => 'P3002', 'jk' => 'P', 'id_kelas' => $kelasLain->uuid]);

        $response = $this->actingAs($user)->get(route('p3.siswa.index'))->assertOk();
        $response->assertSee('Siswa Kelas Saya P3');
        $response->assertDontSee('Siswa Kelas Lain P3');

        $this->actingAs($user)->get(route('p3.siswa.show', $siswaSaya))->assertOk();
        $this->actingAs($user)->get(route('p3.siswa.show', $siswaLain))->assertForbidden();
    }

    public function test_kesiswaan_murni_tanpa_wali_kelas_tetap_lihat_semua_siswa(): void
    {
        $kelasA = Kelas::create(['tingkat' => 9, 'kelas' => 'A']);
        $kelasB = Kelas::create(['tingkat' => 9, 'kelas' => 'B']);
        $siswaA = Siswa::create(['nama' => 'Siswa A Murni', 'nis' => 'MURNI001', 'jk' => 'L', 'id_kelas' => $kelasA->uuid]);
        $siswaB = Siswa::create(['nama' => 'Siswa B Murni', 'nis' => 'MURNI002', 'jk' => 'P', 'id_kelas' => $kelasB->uuid]);

        $user = User::create([
            'username' => 'kesiswaan_murni',
            'password' => Hash::make('password'),
            'access'   => 'kesiswaan',
        ]);
        RolePermission::firstOrCreate(['role' => 'kesiswaan', 'permission' => 'manage_disiplin']);

        $response = $this->actingAs($user)->get(route('poin.siswa.index'))->assertOk();
        $response->assertSee('Siswa A Murni');
        $response->assertSee('Siswa B Murni');

        $this->actingAs($user)->get(route('poin.siswa.show', $siswaA))->assertOk();
        $this->actingAs($user)->get(route('poin.siswa.show', $siswaB))->assertOk();
    }

    private function waliKelasGuru(Kelas $kelas): User
    {
        $user = User::create([
            'username' => 'wali_guru_' . $kelas->uuid,
            'password' => Hash::make('password'),
            'access'   => 'guru',
        ]);
        $guru = Guru::create([
            'id_login'        => $user->getKey(),
            'nama'            => 'Wali Kelas Guru',
            'nik'             => 'WKG' . $kelas->uuid,
            'face_descriptor' => [array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63))],
        ]);
        Walikelas::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid]);

        return $user;
    }

    /**
     * Fitur baru FL: "semua guru bisa mengajukan poin/P3 utk seluruh siswa" — SENGAJA beda
     * dari "Lihat ringkasan" (test_poin_siswa_index_..._hanya_lihat_kelasnya di atas). Halaman
     * AJUAN (poin.guru.index) TIDAK dibatasi kelas wali, walau guru yg login kebetulan wali kelas.
     */
    public function test_poin_guru_ajuan_wali_kelas_tetap_lihat_semua_siswa(): void
    {
        $kelasSaya = Kelas::create(['tingkat' => 7, 'kelas' => 'I']);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'J']);
        $user = $this->waliKelasGuru($kelasSaya);

        Siswa::create(['nama' => 'Siswa Ajuan Kelas Saya', 'nis' => 'AJ001', 'jk' => 'L', 'id_kelas' => $kelasSaya->uuid]);
        Siswa::create(['nama' => 'Siswa Ajuan Kelas Lain', 'nis' => 'AJ002', 'jk' => 'P', 'id_kelas' => $kelasLain->uuid]);

        $response = $this->actingAs($user)->get(route('poin.guru.index'))->assertOk();
        $response->assertSee('Siswa Ajuan Kelas Saya');
        $response->assertSee('Siswa Ajuan Kelas Lain');
    }

    public function test_p3_guru_ajuan_wali_kelas_tetap_lihat_semua_siswa(): void
    {
        $kelasSaya = Kelas::create(['tingkat' => 8, 'kelas' => 'I']);
        $kelasLain = Kelas::create(['tingkat' => 8, 'kelas' => 'J']);
        $user = $this->waliKelasGuru($kelasSaya);

        Siswa::create(['nama' => 'Siswa Ajuan P3 Kelas Saya', 'nis' => 'AJP001', 'jk' => 'L', 'id_kelas' => $kelasSaya->uuid]);
        Siswa::create(['nama' => 'Siswa Ajuan P3 Kelas Lain', 'nis' => 'AJP002', 'jk' => 'P', 'id_kelas' => $kelasLain->uuid]);

        $response = $this->actingAs($user)->get(route('p3.guru.index'))->assertOk();
        $response->assertSee('Siswa Ajuan P3 Kelas Saya');
        $response->assertSee('Siswa Ajuan P3 Kelas Lain');
    }

    /** Regresi: sekretaris kelas (siswa, bukan guru) TETAP dibatasi ke kelasnya sendiri di ajuan — tak diminta diperluas. */
    public function test_sekretaris_ajuan_poin_dan_p3_tetap_dibatasi_kelasnya(): void
    {
        $kelasSaya = Kelas::create(['tingkat' => 9, 'kelas' => 'I']);
        $kelasLain = Kelas::create(['tingkat' => 9, 'kelas' => 'J']);
        $userSekretaris = User::create(['username' => 'sekretaris_ajuan', 'password' => Hash::make('x'), 'access' => 'siswa']);
        $sekretaris = Siswa::create([
            'id_login' => $userSekretaris->uuid, 'id_kelas' => $kelasSaya->uuid,
            'nama' => 'Sekretaris Ajuan', 'nis' => 'SEKAJ01', 'jk' => 'L', 'face_descriptor' => [0.1],
        ]);
        Sekretaris::create(['id_siswa' => $sekretaris->uuid, 'id_kelas' => $kelasSaya->uuid]);

        Siswa::create(['nama' => 'Siswa Sek Kelas Saya', 'nis' => 'SEKAJ02', 'jk' => 'L', 'id_kelas' => $kelasSaya->uuid]);
        Siswa::create(['nama' => 'Siswa Sek Kelas Lain', 'nis' => 'SEKAJ03', 'jk' => 'P', 'id_kelas' => $kelasLain->uuid]);

        $resPoin = $this->actingAs($userSekretaris)->get(route('poin.guru.index'))->assertOk();
        $resPoin->assertSee('Siswa Sek Kelas Saya');
        $resPoin->assertDontSee('Siswa Sek Kelas Lain');

        $resP3 = $this->actingAs($userSekretaris)->get(route('p3.guru.index'))->assertOk();
        $resP3->assertSee('Siswa Sek Kelas Saya');
        $resP3->assertDontSee('Siswa Sek Kelas Lain');
    }
}
