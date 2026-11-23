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
            'komentar' => [
                $targetMapel->uuid => ['Bagus', 'Perlu tingkatkan contoh'],
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

    public function test_analisis_menampilkan_satu_guru_per_halaman_dan_filter_per_tingkat(): void
    {
        $f = $this->fixture();

        $kelas7 = Kelas::create(['nama' => '7 A', 'tingkat' => 7, 'kelas' => 'A']);
        $kelas8 = Kelas::create(['nama' => '8 A', 'tingkat' => 8, 'kelas' => 'A']);

        $guruA = Guru::create(['nama' => 'Guru Alpha']);
        $guruB = Guru::create(['nama' => 'Guru Beta']);

        $periode = Periode::create([
            'judul' => 'Survei Tingkat Test',
            'mulai' => now()->subHour(),
            'selesai' => now()->addDay(),
            'dibuat_oleh' => $f['admin']->getKey(),
            'wajib_token' => false,
            'wajib_seb' => false,
            'wajib_kios' => false,
            'pertanyaan' => [
                'mapel' => array_fill(0, 10, 'Pertanyaan Mapel'),
                'wali' => array_fill(0, 6, 'Pertanyaan Wali'),
            ],
        ]);

        // Guru Alpha mengajar di 2 tingkat (Tingkat 7 dan Tingkat 8)
        $targetA7 = $periode->targets()->create([
            'kelas_id' => $kelas7->uuid,
            'guru_id' => $guruA->uuid,
            'jenis' => 'mapel',
            'kelas_nama' => 'Kelas 7 A',
            'guru_nama' => $guruA->nama,
            'pelajaran_nama' => 'Matematika',
        ]);

        $targetA8 = $periode->targets()->create([
            'kelas_id' => $kelas8->uuid,
            'guru_id' => $guruA->uuid,
            'jenis' => 'mapel',
            'kelas_nama' => 'Kelas 8 A',
            'guru_nama' => $guruA->nama,
            'pelajaran_nama' => 'Matematika Lanjut',
        ]);

        // Guru Beta mengajar hanya di Tingkat 8
        $targetB = $periode->targets()->create([
            'kelas_id' => $kelas8->uuid,
            'guru_id' => $guruB->uuid,
            'jenis' => 'mapel',
            'kelas_nama' => 'Kelas 8 A',
            'guru_nama' => $guruB->nama,
            'pelajaran_nama' => 'Bahasa Inggris',
        ]);

        $targetA7->respons()->create([
            'school_id' => $periode->school_id,
            'siswa_id' => 'siswa-1',
            'jawaban' => array_fill(0, 10, '5'),
            'kendala' => ['pilihan' => ['tidak_ada'], 'lainnya' => null],
            'komentar' => ['Hebat sekali di kelas 7'],
            'karakter' => 'Sangat sabar dan ramah',
        ]);

        $targetA8->respons()->create([
            'school_id' => $periode->school_id,
            'siswa_id' => 'siswa-2',
            'jawaban' => array_fill(0, 10, '3'),
            'kendala' => ['pilihan' => ['tidak_ada'], 'lainnya' => null],
            'komentar' => ['Perlu latihan lebih di kelas 8'],
            'karakter' => 'Tegas dan disiplin',
        ]);

        $targetB->respons()->create([
            'school_id' => $periode->school_id,
            'siswa_id' => 'siswa-3',
            'jawaban' => array_fill(0, 10, '4'),
            'kendala' => ['pilihan' => ['tidak_ada'], 'lainnya' => null],
            'komentar' => ['Baik dan disiplin'],
            'karakter' => 'Asyik dan menyenangkan',
        ]);

        // 1. Tanpa filter tingkat: Halaman 1 hanya menampilkan Guru Alpha (1 guru per halaman) dengan total 2 kelas
        $resPage1 = $this->actingAs($f['admin'])->get(route('survei.analisis', $periode));
        $resPage1->assertOk()
            ->assertSee('Guru Alpha')
            ->assertDontSee('canvas-indikator-chart-guru-beta')
            ->assertSee('Guru 1 dari 2');
        $this->assertSame(2, $resPage1->viewData('guruAktif')['peran']['mapel']['kelas']);

        // 2. Filter Tingkat 7 pada hasil Guru Alpha: Menampilkan data khusus Tingkat 7 (1 kelas, Skor 5,00)
        $resAlpha7 = $this->actingAs($f['admin'])->get(route('survei.analisis', [$periode, 'page' => 1, 'tingkat' => 7]));
        $resAlpha7->assertOk()
            ->assertSee('Guru Alpha')
            ->assertSee('Skor 5,00 / 5')
            ->assertSee('Hebat sekali di kelas 7')
            ->assertDontSee('Perlu latihan lebih di kelas 8');
        $this->assertSame(1, $resAlpha7->viewData('guruAktif')['peran']['mapel']['kelas']);

        // 3. Filter Tingkat 8 pada hasil Guru Alpha: Menampilkan data khusus Tingkat 8 (1 kelas, Skor 3,00)
        $resAlpha8 = $this->actingAs($f['admin'])->get(route('survei.analisis', [$periode, 'page' => 1, 'tingkat' => 8]));
        $resAlpha8->assertOk()
            ->assertSee('Guru Alpha')
            ->assertSee('Skor 3,00 / 5')
            ->assertSee('Perlu latihan lebih di kelas 8')
            ->assertDontSee('Hebat sekali di kelas 7');
        $this->assertSame(1, $resAlpha8->viewData('guruAktif')['peran']['mapel']['kelas']);

        // 4. Halaman 2 (?page=2) hanya menampilkan Guru Beta (hanya mengajar 1 tingkat)
        $resPage2 = $this->actingAs($f['admin'])->get(route('survei.analisis', [$periode, 'page' => 2]));
        $resPage2->assertOk()
            ->assertSee('Guru Beta')
            ->assertDontSee('canvas-indikator-chart-guru-alpha')
            ->assertSee('Guru 2 dari 2');
    }

    public function test_naskah_soal_survei_bisa_dicetak_dan_diekspor_pdf(): void
    {
        $f = $this->fixture();

        $periode = Periode::create([
            'judul' => 'Survei Cetak Naskah Instrumen',
            'mulai' => now()->subHour(),
            'selesai' => now()->addDay(),
            'dibuat_oleh' => $f['admin']->getKey(),
            'wajib_token' => true,
            'wajib_seb' => false,
            'wajib_kios' => false,
            'pertanyaan' => [
                'mapel' => [
                    'Guru menguasai materi pembelajaran dengan baik.',
                    'Guru memberikan penjelasan yang membingungkan.',
                ],
                'dibalik_mapel' => [1],
                'wali' => [
                    'Wali kelas responsif terhadap kesulitan siswa.',
                ],
                'dibalik_wali' => [],
                'opsi_karakter' => [
                    'Ramah dan menyenangkan',
                    'Tegas dan disiplin',
                ],
            ],
        ]);

        // 1. Admin dapat membuka tampilan pratinjau cetak browser
        $resPrint = $this->actingAs($f['admin'])->get(route('survei.pertanyaan.cetak', $periode));
        $resPrint->assertOk()
            ->assertSee('Survei Cetak Naskah Instrumen')
            ->assertSee('INSTRUMEN SOAL SURVEI PEMBELAJARAN')
            ->assertSee('Guru menguasai materi pembelajaran dengan baik.')
            ->assertSee('Guru memberikan penjelasan yang membingungkan.')
            ->assertSee('skor dibalik')
            ->assertSee('Wali kelas responsif terhadap kesulitan siswa.')
            ->assertSee('Ramah dan menyenangkan')
            ->assertSee('Cetak Soal (Ctrl+P)')
            ->assertSee('Unduh PDF');

        // 2. Admin dapat mengunduh dalam format PDF
        $resPdf = $this->actingAs($f['admin'])->get(route('survei.pertanyaan.cetak', [$periode, 'format' => 'pdf']));
        $resPdf->assertOk();
        $this->assertStringContainsString('application/pdf', $resPdf->headers->get('content-type'));

        // 3. Siswa biasa tidak berhak mencetak naskah soal instrumen (403 Forbidden)
        $resForbidden = $this->actingAs($f['siswaUser'])->get(route('survei.pertanyaan.cetak', $periode));
        $resForbidden->assertForbidden();
    }
}
