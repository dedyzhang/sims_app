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

    public function test_dropdown_karakter_guru_bisa_ditambah_dikurangi_dan_diisi_siswa(): void
    {
        $f = $this->fixture();

        $customKarakter = [
            'Sangat ramah dan asyik',
            'Tegas dan disiplin',
            'Sering memberi tugas',
        ];

        // 1. Buat periode dengan opsi karakter kustom
        $payload = [
            'judul' => 'Survei dengan Karakter Guru',
            'mulai' => now()->subHour()->toDateTimeString(),
            'selesai' => now()->addDay()->toDateTimeString(),
            'kelas' => [$f['kelas']->uuid],
            'pertanyaan_mapel' => ['Soal Mapel 1'],
            'dibalik_mapel' => [],
            'pertanyaan_wali' => ['Soal Wali 1'],
            'dibalik_wali' => [],
            'opsi_karakter' => $customKarakter,
            'simpan_default' => 1,
        ];

        $res = $this->actingAs($f['admin'])->post(route('survei.store'), $payload);
        $res->assertRedirect();

        $periode = Periode::latest()->firstOrFail();
        $this->assertSame($customKarakter, $periode->pilihanKarakter());
        $this->assertSame($customKarakter, Pertanyaan::karakter());

        // 2. Cek halaman admin & detail periode menampilkan tab karakter
        $this->actingAs($f['admin'])->get(route('survei.index'))
            ->assertOk()
            ->assertSee('Dropdown Karakter');

        $this->actingAs($f['admin'])->get(route('survei.periode', $periode))
            ->assertOk()
            ->assertSee('Dropdown Karakter')
            ->assertSee('Sangat ramah dan asyik');

        // 3. Mulai sesi
        $this->actingAs($f['admin'])->post(route('survei.sesi.mulai', $periode), [
            'kelas_sesi' => [$f['kelas']->uuid],
        ]);
        $token = $periode->sesiAktif()->token;

        // 4. Siswa membuka form survei
        $this->actingAs($f['siswaUser'])->post(route('survei.token', $periode), ['token' => $token]);
        $formRes = $this->actingAs($f['siswaUser'])->get(route('survei.form', $periode));
        $formRes->assertOk()
            ->assertSee('Menurut kamu, guru ini termasuk guru yang ...')
            ->assertSee('Sangat ramah dan asyik')
            ->assertSee('Tegas dan disiplin');

        // 5. Siswa mengirim jawaban dengan pilihan karakter
        $targets = $periode->targets()->get();
        $targetMapel = $targets->firstWhere('jenis', 'mapel');
        $targetWali = $targets->firstWhere('jenis', 'wali');

        $kirimPayload = [
            'jawaban' => [
                $targetMapel->uuid => [0 => 5],
                $targetWali->uuid => [0 => 5],
            ],
            'kendala' => [
                $targetMapel->uuid => ['tidak_ada'],
                $targetWali->uuid => ['tidak'],
            ],
            'karakter' => [
                $targetMapel->uuid => 'Sangat ramah dan asyik',
                $targetWali->uuid => 'Tegas dan disiplin',
            ],
            'tanda_sesi' => $periode->sesiAktif()->akses(request(), $f['kelas']->uuid),
        ];

        $kirimRes = $this->actingAs($f['siswaUser'])->postJson(route('survei.kirim-batch', $periode), $kirimPayload);
        $kirimRes->assertOk()->assertJson(['tersimpan' => true]);

        // Cek database tersimpan nilai karakter
        $this->assertDatabaseHas('survei_respons', [
            'target_id' => $targetMapel->uuid,
            'karakter' => 'Sangat ramah dan asyik',
        ]);
        $this->assertDatabaseHas('survei_respons', [
            'target_id' => $targetWali->uuid,
            'karakter' => 'Tegas dan disiplin',
        ]);

        // 6. Admin mengupdate opsi karakter pada periode
        $updatedKarakter = [
            'Sangat ramah dan asyik',
            'Humoris dan inspiratif',
        ];
        $updateRes = $this->actingAs($f['admin'])->put(route('survei.pertanyaan.update', $periode), [
            'pertanyaan_mapel' => ['Soal Mapel 1 Baru'],
            'pertanyaan_wali' => ['Soal Wali 1 Baru'],
            'opsi_karakter' => $updatedKarakter,
        ]);
        $updateRes->assertRedirect(route('survei.periode', $periode));
        $this->assertSame($updatedKarakter, $periode->fresh()->pilihanKarakter());
    }
}
