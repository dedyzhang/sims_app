<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\User;
use App\Models\Walikelas;
use App\Survei\Periode;
use App\Survei\Pertanyaan;
use App\Survei\Respons;
use App\Survei\Sesi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SurveiDynamicSoalDanHapusPeriodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
    }

    private function fixture(): array
    {
        Setting::set('wajib_daftar_wajah', '0');
        $admin = User::create(['username' => 'test_admin', 'password' => Hash::make('password'), 'access' => 'admin']);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $guruUser = User::create(['username' => 'test_guru', 'password' => Hash::make('password'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Pengetes']);
        $mapel = Pelajaran::create(['nama' => 'Matematika', 'kode' => 'MTK']);
        Ngajar::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid, 'id_pelajaran' => $mapel->uuid]);
        Jadwal::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid, 'id_pelajaran' => $mapel->uuid, 'hari' => 1]);
        Walikelas::create(['id_kelas' => $kelas->uuid, 'id_guru' => $guru->uuid]);
        $siswaUser = User::create(['username' => 'test_siswa', 'password' => Hash::make('password'), 'access' => 'siswa']);
        $siswa = Siswa::create(['id_login' => $siswaUser->uuid, 'nama' => 'Siswa Pengetes', 'nis' => '12345', 'id_kelas' => $kelas->uuid, 'jk' => 'L']);

        return compact('admin', 'kelas', 'guruUser', 'guru', 'mapel', 'siswaUser', 'siswa');
    }

    public function test_admin_bisa_membuat_periode_dengan_soal_dinamis_dan_tanpa_seb_kios(): void
    {
        $f = $this->fixture();

        $customMapel = [
            'Guru mengajar dengan sangat menyenangkan dan jelas.',
            'Guru memberikan umpan balik tugas secara cepat.',
        ];
        $customWali = [
            'Wali kelas sangat peduli dengan kondisi siswa di kelas.',
        ];

        $payload = [
            'judul' => 'Survei Evaluasi Belajar Kustom',
            'mulai' => now()->subHour()->toDateTimeString(),
            'selesai' => now()->addDay()->toDateTimeString(),
            'kelas' => [$f['kelas']->uuid],
            'pertanyaan_mapel' => $customMapel,
            'dibalik_mapel' => [1],
            'pertanyaan_wali' => $customWali,
            'dibalik_wali' => [],
            'simpan_default' => 1,
        ];

        $res = $this->actingAs($f['admin'])->post(route('survei.store'), $payload);
        $res->assertRedirect();

        $periode = Periode::latest()->firstOrFail();
        $this->assertSame('Survei Evaluasi Belajar Kustom', $periode->judul);
        $this->assertFalse($periode->wajib_seb);
        $this->assertFalse($periode->wajib_kios);
        $this->assertSame($customMapel, $periode->butir('mapel'));
        $this->assertSame($customWali, $periode->butir('wali'));
        $this->assertSame([1], $periode->butirNegatif('mapel'));

        // Cek template default juga tersimpan
        $this->assertSame($customMapel, Pertanyaan::untuk('mapel'));
    }

    public function test_sesi_bisa_langsung_dimulai_dan_menghasilkan_token_tanpa_kios(): void
    {
        $f = $this->fixture();

        $periode = Periode::create([
            'judul' => 'Survei Tanpa Kios',
            'mulai' => now()->subHour(),
            'selesai' => now()->addDay(),
            'dibuat_oleh' => $f['admin']->getKey(),
            'wajib_token' => true,
            'wajib_seb' => false,
            'wajib_kios' => false,
            'pertanyaan' => [
                'mapel' => ['Soal 1'],
                'wali' => ['Soal Wali 1'],
            ],
        ]);

        $periode->targets()->create([
            'kelas_id' => $f['kelas']->uuid,
            'guru_id' => $f['guru']->uuid,
            'pelajaran_id' => $f['mapel']->uuid,
            'jenis' => 'mapel',
            'kelas_nama' => '7 A',
            'guru_nama' => 'Guru Pengetes',
            'pelajaran_nama' => 'Matematika',
        ]);

        $res = $this->actingAs($f['admin'])->post(route('survei.sesi.mulai', $periode), [
            'kelas_sesi' => [$f['kelas']->uuid],
        ]);

        $res->assertRedirect(route('survei.periode', $periode));
        $this->assertNotNull($periode->sesiAktif());
        $this->assertNotEmpty($periode->sesiAktif()->token);
    }

    public function test_admin_bisa_mengupdate_soal_pada_periode_yang_ada(): void
    {
        $f = $this->fixture();

        $periode = Periode::create([
            'judul' => 'Survei Existing',
            'mulai' => now()->subHour(),
            'selesai' => now()->addDay(),
            'dibuat_oleh' => $f['admin']->getKey(),
            'wajib_token' => true,
            'wajib_seb' => false,
            'wajib_kios' => false,
            'pertanyaan' => [
                'mapel' => ['Soal Lama'],
                'wali' => ['Soal Wali Lama'],
            ],
        ]);

        $updatedMapel = ['Soal Baru 1', 'Soal Baru 2'];
        $updatedWali = ['Soal Wali Baru 1'];

        $res = $this->actingAs($f['admin'])->put(route('survei.pertanyaan.update', $periode), [
            'pertanyaan_mapel' => $updatedMapel,
            'pertanyaan_wali' => $updatedWali,
            'dibalik_mapel' => [0],
        ]);

        $res->assertRedirect(route('survei.periode', $periode));
        $periode->refresh();
        $this->assertSame($updatedMapel, $periode->butir('mapel'));
        $this->assertSame($updatedWali, $periode->butir('wali'));
    }

    public function test_hapus_periode_menghapus_semua_target_sesi_dan_respons_siswa(): void
    {
        $f = $this->fixture();

        $periode = Periode::create([
            'judul' => 'Survei Akan Dihapus',
            'mulai' => now()->subHour(),
            'selesai' => now()->addDay(),
            'dibuat_oleh' => $f['admin']->getKey(),
            'wajib_token' => true,
            'wajib_seb' => false,
            'wajib_kios' => false,
            'pertanyaan' => [
                'mapel' => ['Soal 1'],
                'wali' => ['Soal Wali 1'],
            ],
        ]);

        $target = $periode->targets()->create([
            'kelas_id' => $f['kelas']->uuid,
            'guru_id' => $f['guru']->uuid,
            'pelajaran_id' => $f['mapel']->uuid,
            'jenis' => 'mapel',
            'kelas_nama' => '7 A',
            'guru_nama' => 'Guru Pengetes',
            'pelajaran_nama' => 'Matematika',
        ]);

        $sesi = $periode->sesi()->create([
            'kelas_ids' => [$f['kelas']->uuid],
            'token' => '123456',
            'berlaku_sampai' => now()->addDay(),
        ]);

        $respons = Respons::create([
            'target_id' => $target->uuid,
            'siswa_id' => $f['siswa']->uuid,
            'jawaban' => [0 => 5],
            'kendala' => ['pilihan' => ['tidak_ada'], 'lainnya' => null],
            'komentar' => [],
        ]);

        $this->assertDatabaseHas('survei_periodes', ['uuid' => $periode->uuid]);
        $this->assertDatabaseHas('survei_targets', ['uuid' => $target->uuid]);
        $this->assertDatabaseHas('survei_sesis', ['uuid' => $sesi->uuid]);
        $this->assertDatabaseHas('survei_respons', ['uuid' => $respons->uuid]);

        // Hapus periode
        $res = $this->actingAs($f['admin'])->delete(route('survei.destroy', $periode));
        $res->assertRedirect(route('survei.index'));

        $this->assertDatabaseMissing('survei_periodes', ['uuid' => $periode->uuid]);
        $this->assertDatabaseMissing('survei_targets', ['uuid' => $target->uuid]);
        $this->assertDatabaseMissing('survei_sesis', ['uuid' => $sesi->uuid]);
        $this->assertDatabaseMissing('survei_respons', ['uuid' => $respons->uuid]);
    }
}
