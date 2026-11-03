<?php

namespace Tests\Feature;

use App\Models\Aturan;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Poin;
use App\Models\RolePermission;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PoinPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('jenis_aturan', 'poin');
        Setting::set('wajib_daftar_wajah', '0');
        Semester::create([
            'semester' => 1,
            'tahun'    => '2026/2027',
            'aktif'    => true,
        ]);
    }

    public function test_admin_bisa_mencetak_poin_siswa(): void
    {
        $admin = User::create([
            'username' => 'admin_test',
            'password' => Hash::make('password'),
            'access'   => 'admin',
        ]);
        RolePermission::firstOrCreate(['role' => 'admin', 'permission' => 'manage_disiplin']);

        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        $siswa = Siswa::create([
            'nama'     => 'Budi Santoso',
            'nis'      => '12345',
            'nisn'     => '0012345678',
            'jk'       => 'L',
            'id_kelas' => $kelas->uuid,
        ]);

        $aturan1 = Aturan::create([
            'kode'   => 'PEL01',
            'jenis'  => 'kurang',
            'aturan' => 'Terlambat masuk sekolah',
            'poin'   => 10,
        ]);
        $aturan2 = Aturan::create([
            'kode'   => 'PRES01',
            'jenis'  => 'tambah',
            'aturan' => 'Juara 1 Lomba Sains',
            'poin'   => 15,
        ]);

        Poin::create([
            'tanggal'   => '2026-08-10',
            'id_siswa'  => $siswa->uuid,
            'id_aturan' => $aturan1->uuid,
        ]);
        Poin::create([
            'tanggal'   => '2026-08-15',
            'id_siswa'  => $siswa->uuid,
            'id_aturan' => $aturan2->uuid,
        ]);

        $response = $this->actingAs($admin)->get(route('poin.siswa.print', $siswa));

        $response->assertOk();
        $response->assertSee('REKAPITULASI POIN KEDISIPLINAN SISWA');
        $response->assertSee('Budi Santoso');
        $response->assertSee('12345');
        $response->assertSee('8A');
        $response->assertSee('Terlambat masuk sekolah');
        $response->assertSee('Juara 1 Lomba Sains');
        $response->assertSee('window.print()', false);
        $response->assertSee('Orang Tua / Wali Murid');
        $response->assertSee('Wali Kelas');
        $response->assertSee('Kepala Sekolah');
    }

    public function test_wali_kelas_hanya_bisa_cetak_poin_siswa_di_kelasnya(): void
    {
        $kelasSaya = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);

        $userWali = User::create([
            'username' => 'wali_7a',
            'password' => Hash::make('password'),
            'access'   => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $userWali->getKey(),
            'nama'     => 'Ibu Wali Kelas 7A',
            'nik'      => 'GURU7A001',
        ]);
        Walikelas::create(['id_kelas' => $kelasSaya->uuid, 'id_guru' => $guru->uuid]);

        $siswaSaya = Siswa::create([
            'nama'     => 'Siswa 7A',
            'nis'      => '7A001',
            'jk'       => 'L',
            'id_kelas' => $kelasSaya->uuid,
        ]);
        $siswaLain = Siswa::create([
            'nama'     => 'Siswa 7B',
            'nis'      => '7B001',
            'jk'       => 'P',
            'id_kelas' => $kelasLain->uuid,
        ]);

        // Siswa di kelas sendiri -> boleh cetak
        $this->actingAs($userWali)
            ->get(route('poin.siswa.print', $siswaSaya))
            ->assertOk()
            ->assertSee('Siswa 7A')
            ->assertSee('Ibu Wali Kelas 7A');

        // Siswa di kelas lain -> ditolak 403
        $this->actingAs($userWali)
            ->get(route('poin.siswa.print', $siswaLain))
            ->assertForbidden();
    }

    public function test_orang_tua_dan_siswa_bisa_cetak_milik_sendiri_tetapi_tidak_siswa_lain(): void
    {
        $kelas = Kelas::create(['tingkat' => 9, 'kelas' => 'C']);
        $userSiswa = User::create([
            'username' => 'siswa_budi',
            'password' => Hash::make('password'),
            'access'   => 'siswa',
        ]);
        $siswa = Siswa::create([
            'id_login' => $userSiswa->getKey(),
            'nama'     => 'Budi Siswa',
            'nis'      => 'SIS001',
            'jk'       => 'L',
            'id_kelas' => $kelas->uuid,
        ]);

        $userOrtu = User::create([
            'username' => 'ortu_budi',
            'password' => Hash::make('password'),
            'access'   => 'orangtua',
        ]);
        Orangtua::create([
            'id_login' => $userOrtu->getKey(),
            'id_siswa' => $siswa->uuid,
            'nama'     => 'Ayah Budi',
        ]);

        $siswaLain = Siswa::create([
            'nama'     => 'Siswa Lainnya',
            'nis'      => 'SIS002',
            'jk'       => 'L',
            'id_kelas' => $kelas->uuid,
        ]);

        // Siswa lihat milik sendiri -> OK
        $this->actingAs($userSiswa)
            ->get(route('poin.siswa.print', $siswa))
            ->assertOk()
            ->assertSee('Budi Siswa');

        // Siswa coba cetak milik siswa lain -> 403
        $this->actingAs($userSiswa)
            ->get(route('poin.siswa.print', $siswaLain))
            ->assertForbidden();

        // Ortu lihat milik anaknya -> OK
        $this->actingAs($userOrtu)
            ->get(route('poin.siswa.print', $siswa))
            ->assertOk()
            ->assertSee('Budi Siswa');

        // Ortu coba cetak milik siswa lain -> 403
        $this->actingAs($userOrtu)
            ->get(route('poin.siswa.print', $siswaLain))
            ->assertForbidden();
    }

    public function test_perhitungan_sisa_poin_dan_status_sp_pada_lembar_cetak(): void
    {
        $admin = User::create([
            'username' => 'admin_sp',
            'password' => Hash::make('password'),
            'access'   => 'admin',
        ]);
        RolePermission::firstOrCreate(['role' => 'admin', 'permission' => 'manage_disiplin']);

        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);
        $siswa = Siswa::create([
            'nama'     => 'Siti Peringatan',
            'nis'      => '8801',
            'jk'       => 'P',
            'id_kelas' => $kelas->uuid,
        ]);

        // Pelanggaran besar: 60 poin (sisa 40 -> SP 2)
        $aturan = Aturan::create([
            'kode'   => 'BRT01',
            'jenis'  => 'kurang',
            'aturan' => 'Pelanggaran Berat',
            'poin'   => 60,
        ]);
        Poin::create([
            'tanggal'   => '2026-09-01',
            'id_siswa'  => $siswa->uuid,
            'id_aturan' => $aturan->uuid,
        ]);

        $response = $this->actingAs($admin)->get(route('poin.siswa.print', $siswa));

        $response->assertOk();
        $response->assertSee('Siti Peringatan');
        $response->assertSee('-60');
        $response->assertSee('40');
        $response->assertSee('Surat Peringatan 2 (SP 2)');
    }

    public function test_halaman_show_dan_index_memiliki_tombol_cetak(): void
    {
        $admin = User::create([
            'username' => 'admin_ui',
            'password' => Hash::make('password'),
            'access'   => 'admin',
        ]);
        RolePermission::firstOrCreate(['role' => 'admin', 'permission' => 'manage_disiplin']);

        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'C']);
        $siswa = Siswa::create([
            'nama'     => 'Andi Wijaya',
            'nis'      => 'ANDI01',
            'jk'       => 'L',
            'id_kelas' => $kelas->uuid,
        ]);

        // Index page ada tautan cetak
        $this->actingAs($admin)
            ->get(route('poin.siswa.index'))
            ->assertOk()
            ->assertSee(route('poin.siswa.print', $siswa));

        // Show page ada tombol Cetak Poin
        $this->actingAs($admin)
            ->get(route('poin.siswa.show', $siswa))
            ->assertOk()
            ->assertSee('Cetak Poin')
            ->assertSee(route('poin.siswa.print', $siswa));
    }
}
