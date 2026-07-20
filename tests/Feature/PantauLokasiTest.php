<?php

namespace Tests\Feature;

use App\Models\Absensi;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PantauLokasiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('pantau_lokasi_aktif', '1');
        Setting::set('fitur_absensi_aktif', '1');
        Setting::set('sekolah_lat', '-6.200000');
        Setting::set('sekolah_lng', '106.816666');
        Setting::set('absen_radius', '200');
    }

    private function makeKelas(string $kelas = 'A'): Kelas
    {
        return Kelas::create(['tingkat' => 7, 'kelas' => $kelas]);
    }

    private function makeSiswa(Kelas $kelas, string $suffix): array
    {
        $user = User::create([
            'username' => 'siswa_pantau_'.$suffix,
            'password' => Hash::make('password'),
            'access' => 'siswa',
        ]);
        $siswa = Siswa::create([
            'id_login' => $user->uuid,
            'nama' => 'Siswa '.$suffix,
            'nis' => 'PNT-'.$suffix,
            'id_kelas' => $kelas->uuid,
            'jk' => 'L',
        ]);

        return [$siswa, $user];
    }

    private function makeOrtu(Siswa $siswa, string $suffix): User
    {
        $ortu = User::create([
            'username' => 'ortu_pantau_'.$suffix,
            'password' => Hash::make('password'),
            'access' => 'orangtua',
        ]);
        Orangtua::create([
            'id_login' => $ortu->uuid,
            'id_siswa' => $siswa->uuid,
        ]);

        return $ortu;
    }

    private function makeAdmin(): User
    {
        return User::firstOrCreate(
            ['username' => 'admin_pantau'],
            ['password' => Hash::make('password'), 'access' => 'superadmin']
        );
    }

    private function makeWalikelas(Kelas $kelas): User
    {
        $user = User::create([
            'username' => 'wali_pantau_'.$kelas->kelas,
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $user->uuid,
            'nama' => 'Wali '.$kelas->kelas,
            'nip' => 'WALI-'.$kelas->kelas,
            // Wajib ada agar EnsureFaceRegistered tidak redirect ke /wajah-saya.
            'face_descriptor' => [array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63))],
        ]);
        Walikelas::create([
            'id_guru' => $guru->uuid,
            'id_kelas' => $kelas->uuid,
        ]);

        return $user;
    }

    private function seedAbsenDenganGeo(Siswa $siswa, string $tanggal = '2026-07-20'): Absensi
    {
        return Absensi::create([
            'id_siswa' => $siswa->uuid,
            'id_kelas' => $siswa->id_kelas,
            'tanggal' => $tanggal,
            'jam_masuk' => '07:15:00',
            'status' => 'hadir',
            'keterangan' => 'Absen QR',
            'geo_lat' => -6.200100,
            'geo_lng' => 106.816700,
            'geo_accuracy' => 25,
            'geo_jarak' => 18,
        ]);
    }

    public function test_admin_bisa_melihat_pantau_lokasi(): void
    {
        $kelas = $this->makeKelas('A');
        [$siswa] = $this->makeSiswa($kelas, 'a1');
        $this->seedAbsenDenganGeo($siswa);

        $this->actingAs($this->makeAdmin())
            ->get(route('pantau-lokasi.index', ['tanggal' => '2026-07-20']))
            ->assertOk()
            ->assertSee('Pantau Lokasi')
            ->assertSee('Siswa a1');
    }

    public function test_ortu_hanya_melihat_anak_sendiri(): void
    {
        $kelas = $this->makeKelas('A');
        [$anakA] = $this->makeSiswa($kelas, 'own');
        [$anakB] = $this->makeSiswa($kelas, 'other');
        $this->seedAbsenDenganGeo($anakA);
        $this->seedAbsenDenganGeo($anakB);
        $ortu = $this->makeOrtu($anakA, 'own');

        $this->actingAs($ortu)
            ->get(route('pantau-lokasi.index', ['tanggal' => '2026-07-20']))
            ->assertOk()
            ->assertSee('Siswa own')
            ->assertDontSee('Siswa other');
    }

    public function test_ortu_tidak_bisa_memaksa_filter_siswa_orang_lain(): void
    {
        $kelas = $this->makeKelas('A');
        [$anakA] = $this->makeSiswa($kelas, 'own2');
        [$anakB] = $this->makeSiswa($kelas, 'other2');
        $this->seedAbsenDenganGeo($anakA);
        $this->seedAbsenDenganGeo($anakB);
        $ortu = $this->makeOrtu($anakA, 'own2');

        // Query ?siswa=anakB harus diabaikan → tetap scope anak sendiri.
        $this->actingAs($ortu)
            ->get(route('pantau-lokasi.index', [
                'tanggal' => '2026-07-20',
                'siswa' => $anakB->uuid,
            ]))
            ->assertOk()
            ->assertSee('Siswa own2')
            ->assertDontSee('Siswa other2');
    }

    public function test_wali_kelas_hanya_melihat_kelasnya(): void
    {
        $kelasA = $this->makeKelas('A');
        $kelasB = $this->makeKelas('B');
        [$siswaA] = $this->makeSiswa($kelasA, 'ka');
        [$siswaB] = $this->makeSiswa($kelasB, 'kb');
        $this->seedAbsenDenganGeo($siswaA);
        $this->seedAbsenDenganGeo($siswaB);
        $wali = $this->makeWalikelas($kelasA);

        $this->actingAs($wali)
            ->get(route('pantau-lokasi.index', ['tanggal' => '2026-07-20']))
            ->assertOk()
            ->assertSee('Siswa ka')
            ->assertDontSee('Siswa kb');
    }

    public function test_fitur_nonaktif_mengembalikan_403(): void
    {
        Setting::set('pantau_lokasi_aktif', '0');
        $kelas = $this->makeKelas('A');
        [$siswa] = $this->makeSiswa($kelas, 'off');
        $ortu = $this->makeOrtu($siswa, 'off');

        $this->actingAs($ortu)
            ->get(route('pantau-lokasi.index'))
            ->assertForbidden();

        $this->actingAs($this->makeAdmin())
            ->get(route('pantau-lokasi.index'))
            ->assertForbidden();
    }

    public function test_guru_bukan_wali_tidak_bisa_akses(): void
    {
        $user = User::create([
            'username' => 'guru_biasa_pantau',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        Guru::create([
            'id_login' => $user->uuid,
            'nama' => 'Guru Biasa',
            'nip' => 'GURU-BIASA',
            'face_descriptor' => [array_map(fn ($i) => $i % 2 === 0 ? 1.0 : -1.0, range(0, 63))],
        ]);

        $this->actingAs($user)
            ->get(route('pantau-lokasi.index'))
            ->assertForbidden();
    }

    public function test_titik_di_luar_area_sekolah_tidak_ditampilkan(): void
    {
        $kelas = $this->makeKelas('A');
        [$dalam] = $this->makeSiswa($kelas, 'in');
        [$luar] = $this->makeSiswa($kelas, 'out');
        $this->seedAbsenDenganGeo($dalam);

        // ~1.1 km ke utara — di luar radius 200 + toleransi 50
        Absensi::create([
            'id_siswa' => $luar->uuid,
            'id_kelas' => $luar->id_kelas,
            'tanggal' => '2026-07-20',
            'jam_masuk' => '07:20:00',
            'status' => 'hadir',
            'keterangan' => 'Absen QR',
            'geo_lat' => -6.190000,
            'geo_lng' => 106.816666,
            'geo_accuracy' => 20,
            'geo_jarak' => 1100,
        ]);

        // 2 absen ber-GPS, tapi hanya 1 di dalam area → badge "1 titik".
        $this->actingAs($this->makeAdmin())
            ->get(route('pantau-lokasi.index', ['tanggal' => '2026-07-20']))
            ->assertOk()
            ->assertSee('1 titik pada', false)
            ->assertDontSee('2 titik pada', false);
    }
}
