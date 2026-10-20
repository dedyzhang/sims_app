<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UjianSoalExportImportTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaranMatematika;
    private Pelajaran $pelajaranIpa;
    private Kelas $kelas;
    private User $guruUser;
    private Guru $guru;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaranMatematika = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->pelajaranIpa = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        $this->guruUser = User::create(['username' => 'guru_ujian', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $this->guru = Guru::create([
            'id_login' => $this->guruUser->uuid,
            'nama' => 'Guru Ujian',
            'nik' => '1234567890',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        Ngajar::create(['id_guru' => $this->guru->uuid, 'id_pelajaran' => $this->pelajaranMatematika->uuid, 'id_kelas' => $this->kelas->uuid]);
        Ngajar::create(['id_guru' => $this->guru->uuid, 'id_pelajaran' => $this->pelajaranIpa->uuid, 'id_kelas' => $this->kelas->uuid]);
    }

    private function buatUjian(Pelajaran $pelajaran, string $judul = 'Ujian Draf'): Ujian
    {
        $ujian = Ujian::create([
            'id_pelajaran'   => $pelajaran->uuid,
            'created_by'     => $this->guruUser->uuid,
            'judul'          => $judul,
            'target_nilai'   => 'harian',
            'durasi_menit'   => 60,
            'status'         => 'draft',
        ]);

        UjianKelas::create([
            'id_ujian'   => $ujian->uuid,
            'id_kelas'   => $this->kelas->uuid,
            'token_masuk'=> 'TOKEN1',
        ]);

        return $ujian;
    }

    public function test_guru_bisa_unduh_template_soal_excel(): void
    {
        $res = $this->actingAs($this->guruUser)->get(route('ujian.soal.template'));
        $res->assertOk();
        $res->assertHeader('content-disposition');
    }

    public function test_guru_bisa_ekspor_soal_ke_excel_dan_json(): void
    {
        $ujian = $this->buatUjian($this->pelajaranMatematika, 'Ujian Matematika Semester 1');

        // Buat soal MCQ
        $soal1 = UjianSoal::create([
            'id_ujian'  => $ujian->uuid,
            'tipe'      => 'mcq',
            'teks_soal' => 'Hasil dari 5 + 5 adalah...',
            'poin'      => 2,
            'urutan'    => 1,
        ]);
        UjianSoalOpsi::create(['id_soal' => $soal1->uuid, 'teks_opsi' => '10', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal1->uuid, 'teks_opsi' => '15', 'is_benar' => false, 'urutan' => 2]);

        // Buat soal Essay
        UjianSoal::create([
            'id_ujian'  => $ujian->uuid,
            'tipe'      => 'essay',
            'teks_soal' => 'Jelaskan rumus luas persegi!',
            'poin'      => 5,
            'urutan'    => 2,
            'meta'      => ['kunci_jawaban' => 'Luas = sisi x sisi'],
        ]);

        // Ekspor Excel
        $resExcel = $this->actingAs($this->guruUser)->get(route('ujian.soal.export', ['ujian' => $ujian, 'format' => 'xlsx']));
        $resExcel->assertOk();

        // Ekspor JSON
        $resJson = $this->actingAs($this->guruUser)->get(route('ujian.soal.export', ['ujian' => $ujian, 'format' => 'json']));
        $resJson->assertOk();
        $resJson->assertJsonPath('total_soal', 2);
        $resJson->assertJsonPath('soal.0.tipe', 'mcq');
        $resJson->assertJsonPath('soal.1.tipe', 'essay');
    }

    public function test_guru_bisa_impor_soal_dari_excel_ke_mapel_lain(): void
    {
        // Buat soal di Ujian Matematika
        $ujianMatematika = $this->buatUjian($this->pelajaranMatematika, 'Ujian Sumber Excel');
        $s = UjianSoal::create([
            'id_ujian'  => $ujianMatematika->uuid,
            'tipe'      => 'mcq',
            'teks_soal' => 'Berapa 10 + 20?',
            'poin'      => 5,
            'urutan'    => 1,
        ]);
        UjianSoalOpsi::create(['id_soal' => $s->uuid, 'teks_opsi' => '30', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $s->uuid, 'teks_opsi' => '40', 'is_benar' => false, 'urutan' => 2]);

        // Simpan ekspor ke file fisik
        \Maatwebsite\Excel\Facades\Excel::store(new \App\Exports\Ujian\UjianSoalExport($ujianMatematika), 'test_export_soal.xlsx', 'local');
        $storedPath = \Illuminate\Support\Facades\Storage::disk('local')->path('test_export_soal.xlsx');

        $file = new UploadedFile($storedPath, 'soal.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        // Ujian tujuan di mapel IPA
        $ujianIpa = $this->buatUjian($this->pelajaranIpa, 'Ujian Target Excel Mapel IPA');

        $this->actingAs($this->guruUser)
            ->post(route('ujian.soal.import', $ujianIpa), [
                'file' => $file,
                'mode' => 'append',
            ])
            ->assertRedirect();

        $this->assertEquals(1, $ujianIpa->soal()->count());
        $soal = $ujianIpa->soal()->first();
        $this->assertEquals('Berapa 10 + 20?', $soal->teks_soal);
        $this->assertEquals(5, $soal->poin);
        $this->assertEquals(2, $soal->opsi()->count());
        $this->assertTrue((bool)$soal->opsi()->first()->is_benar);

        \Illuminate\Support\Facades\Storage::disk('local')->delete('test_export_soal.xlsx');
    }

    public function test_guru_bisa_impor_soal_dari_json_mode_append_dan_replace(): void
    {
        $ujian = $this->buatUjian($this->pelajaranMatematika, 'Ujian Import Test');

        // Tambah 1 soal awal
        $awal = UjianSoal::create([
            'id_ujian'  => $ujian->uuid,
            'tipe'      => 'mcq',
            'teks_soal' => 'Soal awal',
            'poin'      => 1,
            'urutan'    => 1,
        ]);
        UjianSoalOpsi::create(['id_soal' => $awal->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $awal->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);

        $payloadJson = json_encode([
            'soal' => [
                [
                    'tipe'      => 'mcq',
                    'teks_soal' => 'Soal impor 1',
                    'poin'      => 2,
                    'opsi'      => [
                        ['teks' => 'Pilihan 1', 'benar' => true],
                        ['teks' => 'Pilihan 2', 'benar' => false],
                    ],
                ],
                [
                    'tipe'      => 'match',
                    'teks_soal' => 'Jodohkan item',
                    'poin'      => 4,
                    'meta'      => [
                        'pairs' => [
                            ['left' => 'Kiri 1', 'right' => 'Kanan 1'],
                            ['left' => 'Kiri 2', 'right' => 'Kanan 2'],
                        ],
                    ],
                ],
            ],
        ]);

        $fileAppend = UploadedFile::fake()->createWithContent('soal.json', $payloadJson);

        // Test mode append
        $this->actingAs($this->guruUser)
            ->post(route('ujian.soal.import', $ujian), [
                'file' => $fileAppend,
                'mode' => 'append',
            ])
            ->assertRedirect();

        $this->assertEquals(3, $ujian->soal()->count());
        $this->assertDatabaseHas('ujian_soal', ['id_ujian' => $ujian->uuid, 'teks_soal' => 'Soal awal']);
        $this->assertDatabaseHas('ujian_soal', ['id_ujian' => $ujian->uuid, 'teks_soal' => 'Soal impor 1']);

        // Test mode replace
        $payloadReplace = json_encode([
            'soal' => [
                [
                    'tipe'      => 'essay',
                    'teks_soal' => 'Soal baru menggantikan semuanya',
                    'poin'      => 10,
                ],
            ],
        ]);
        $fileReplace = UploadedFile::fake()->createWithContent('soal_baru.json', $payloadReplace);

        $this->actingAs($this->guruUser)
            ->post(route('ujian.soal.import', $ujian), [
                'file' => $fileReplace,
                'mode' => 'replace',
            ])
            ->assertRedirect();

        $this->assertEquals(1, $ujian->soal()->count());
        $this->assertDatabaseMissing('ujian_soal', ['id_ujian' => $ujian->uuid, 'teks_soal' => 'Soal awal']);
        $this->assertDatabaseHas('ujian_soal', ['id_ujian' => $ujian->uuid, 'teks_soal' => 'Soal baru menggantikan semuanya']);
    }

    public function test_guru_bisa_salin_soal_langsung_dari_ujian_lain_lintas_mata_pelajaran(): void
    {
        // Ujian A pada mapel Matematika
        $ujianMatematika = $this->buatUjian($this->pelajaranMatematika, 'Ujian Matematika Sumber');
        $s1 = UjianSoal::create([
            'id_ujian'  => $ujianMatematika->uuid,
            'tipe'      => 'mcq',
            'teks_soal' => 'Pertanyaan Matematika 1',
            'poin'      => 3,
            'urutan'    => 1,
        ]);
        UjianSoalOpsi::create(['id_soal' => $s1->uuid, 'teks_opsi' => 'Benar', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $s1->uuid, 'teks_opsi' => 'Salah', 'is_benar' => false, 'urutan' => 2]);

        // Ujian B pada mapel IPA (target yang berbeda mapel)
        $ujianIpa = $this->buatUjian($this->pelajaranIpa, 'Ujian IPA Target');
        $this->assertEquals(0, $ujianIpa->soal()->count());

        // Salin dari Matematika ke IPA
        $this->actingAs($this->guruUser)
            ->post(route('ujian.soal.salin', $ujianIpa), [
                'id_ujian_sumber' => $ujianMatematika->uuid,
                'mode'            => 'append',
            ])
            ->assertRedirect();

        $this->assertEquals(1, $ujianIpa->soal()->count());
        $soalDisalin = $ujianIpa->soal()->first();
        $this->assertEquals('Pertanyaan Matematika 1', $soalDisalin->teks_soal);
        $this->assertEquals(3, $soalDisalin->poin);
        $this->assertEquals(2, $soalDisalin->opsi()->count());
    }

    public function test_guru_bisa_mengambil_daftar_ujian_sumber_lintas_mapel(): void
    {
        $ujianA = $this->buatUjian($this->pelajaranMatematika, 'Ujian Matematika 1');
        $ujianB = $this->buatUjian($this->pelajaranIpa, 'Ujian IPA 1');

        $res = $this->actingAs($this->guruUser)->getJson(route('ujian.soal.sumber', $ujianA));
        $res->assertOk();
        $res->assertJsonFragment(['uuid' => $ujianB->uuid, 'judul' => 'Ujian IPA 1', 'pelajaran' => 'IPA']);
        $res->assertJsonMissing(['uuid' => $ujianA->uuid]); // Ujian target sendiri tidak muncul di daftar sumber
    }

    public function test_impor_soal_ditolak_jika_ujian_bukan_draft(): void
    {
        $ujian = $this->buatUjian($this->pelajaranMatematika, 'Ujian Terbit');
        $ujian->update(['status' => 'published']);

        $payload = json_encode(['soal' => [['tipe' => 'essay', 'teks_soal' => 'Tes']]]);
        $file = UploadedFile::fake()->createWithContent('soal.json', $payload);

        $this->actingAs($this->guruUser)
            ->post(route('ujian.soal.import', $ujian), [
                'file' => $file,
                'mode' => 'append',
            ])
            ->assertStatus(422);
    }
}
