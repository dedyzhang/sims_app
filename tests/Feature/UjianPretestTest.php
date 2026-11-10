<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianPretest;
use App\Models\UjianPretestPeserta;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UjianPretestTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;
    private Ujian $ujian;
    private User $guruUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);

        $this->guruUser = User::create([
            'username' => 'guru_matematika',
            'password' => Hash::make('secret123'),
            'access' => 'guru',
        ]);
        $guru = Guru::create([
            'id_login' => $this->guruUser->uuid,
            'nama' => 'Guru Matematika',
            'nik' => '1234567890',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);
        Ngajar::create([
            'id_guru' => $guru->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas' => $this->kelas->uuid,
        ]);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid,
            'created_by' => $this->guruUser->uuid,
            'judul' => 'Ujian Pra-Simulasi Aljabar',
            'jenis' => 'harian',
            'target_nilai' => 'formatif',
            'durasi_menit' => 45,
            'status' => 'published',
        ]);

        // Buat 2 soal MCQ
        $soal1 = UjianSoal::create([
            'id_ujian' => $this->ujian->uuid,
            'tipe' => 'mcq',
            'teks_soal' => 'Hasil dari 5 + 7 adalah?',
            'poin' => 10,
            'urutan' => 1,
        ]);
        UjianSoalOpsi::create(['id_soal' => $soal1->uuid, 'teks_opsi' => '12', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal1->uuid, 'teks_opsi' => '10', 'is_benar' => false, 'urutan' => 2]);

        $soal2 = UjianSoal::create([
            'id_ujian' => $this->ujian->uuid,
            'tipe' => 'mcq',
            'teks_soal' => 'Hasil dari 3 x 4 adalah?',
            'poin' => 10,
            'urutan' => 2,
        ]);
        UjianSoalOpsi::create(['id_soal' => $soal2->uuid, 'teks_opsi' => '12', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal2->uuid, 'teks_opsi' => '14', 'is_benar' => false, 'urutan' => 2]);
    }

    public function test_guru_bisa_mengakses_halaman_pretest_hub(): void
    {
        $response = $this->actingAs($this->guruUser)->get(route('ujian.pretest.index', $this->ujian));
        $response->assertOk();
        $response->assertSee('Pre-Test & Uji Coba Publik');
        $response->assertSee('Tautan Langsung Pre-Test');
        $response->assertSee('Akses Ditutup');
        $response->assertSee('Buka Akses');

        // Pastikan record UjianPretest otomatis dibuat dengan is_active false (ditutup default)
        $this->assertDatabaseHas('ujian_pretests', [
            'id_ujian' => $this->ujian->uuid,
            'is_active' => false,
        ]);
    }

    public function test_guru_bisa_toggle_status_dan_regenerate_token(): void
    {
        $this->actingAs($this->guruUser)->get(route('ujian.pretest.index', $this->ujian));
        $pretest = UjianPretest::where('id_ujian', $this->ujian->uuid)->first();
        $this->assertFalse((bool) $pretest->is_active);
        $originalToken = $pretest->token;

        // Toggle buka akses (menjadi aktif)
        $resToggle = $this->actingAs($this->guruUser)->post(route('ujian.pretest.toggle', $this->ujian));
        $resToggle->assertRedirect();
        $this->assertTrue((bool) $pretest->fresh()->is_active);

        // Toggle tutup kembali (menjadi nonaktif)
        $resToggle2 = $this->actingAs($this->guruUser)->post(route('ujian.pretest.toggle', $this->ujian));
        $resToggle2->assertRedirect();
        $this->assertFalse((bool) $pretest->fresh()->is_active);

        // Regenerate token
        $resRegen = $this->actingAs($this->guruUser)->post(route('ujian.pretest.regenerate', $this->ujian));
        $resRegen->assertRedirect();
        $this->assertNotEquals($originalToken, $pretest->fresh()->token);
    }

    public function test_publik_melihat_halaman_tutup_saat_pretest_belum_dibuka(): void
    {
        $this->actingAs($this->guruUser)->get(route('ujian.pretest.index', $this->ujian));
        $pretest = UjianPretest::where('id_ujian', $this->ujian->uuid)->first();

        // Akses link publik saat status masih tertutup
        $response = $this->get(route('pretest.public.show', $pretest->token));
        $response->assertOk();
        $response->assertSee('Akses Pre-Test Ditutup');
    }

    public function test_publik_bisa_mengerjakan_pretest_dan_mendapat_skor(): void
    {
        $pretest = UjianPretest::create([
            'id_ujian' => $this->ujian->uuid,
            'token' => 'PRETEST1',
            'is_active' => true,
            'durasi_menit' => 30,
            'tampilkan_nilai' => true,
            'tampilkan_pembahasan' => true,
        ]);

        // 1. Publik buka halaman welcome tanpa login
        $resWelcome = $this->get(route('pretest.public.show', $pretest->token));
        $resWelcome->assertOk();
        $resWelcome->assertSee('Uji Coba Pre-Test');
        $resWelcome->assertSee($this->ujian->judul);

        // 2. Publik isi nama dan klik mulai
        $resMulai = $this->post(route('pretest.public.mulai', $pretest->token), [
            'nama' => 'Budi Santoso',
            'identitas' => 'SMP Negeri Contoh',
            'nomor_kontak' => '08123456789',
        ]);
        $resMulai->assertRedirect();

        $peserta = UjianPretestPeserta::where('id_pretest', $pretest->uuid)->first();
        $this->assertNotNull($peserta);
        $this->assertEquals('Budi Santoso', $peserta->nama);

        // 3. Masuk halaman pengerjaan
        $resKerjakan = $this->withSession(['pretest_guest_' . $pretest->token => $peserta->guest_token])
            ->get(route('pretest.public.kerjakan', $pretest->token));
        $resKerjakan->assertOk();
        $resKerjakan->assertSee('Budi Santoso');

        // 4. Jawab soal 1 dengan benar (opsi 12)
        $soal1 = $this->ujian->soal()->where('urutan', 1)->first();
        $opsiBenarSoal1 = $soal1->opsi()->where('is_benar', true)->first();

        $resJawab = $this->withSession(['pretest_guest_' . $pretest->token => $peserta->guest_token])
            ->postJson(route('pretest.public.jawab', $pretest->token), [
                'jawaban' => [
                    [
                        'id_soal' => $soal1->uuid,
                        'id_opsi_dipilih' => $opsiBenarSoal1->uuid,
                    ],
                ],
            ]);
        $resJawab->assertOk();

        // 5. Kumpulkan ujian
        $resKumpul = $this->withSession(['pretest_guest_' . $pretest->token => $peserta->guest_token])
            ->post(route('pretest.public.kumpul', $pretest->token));
        $resKumpul->assertRedirect();

        $peserta->refresh();
        $this->assertEquals('submitted', $peserta->status);
        $this->assertEquals(1, $peserta->jumlah_benar);
        $this->assertEquals(1, $peserta->jumlah_salah); // Soal 2 tidak dijawab = salah
        $this->assertGreaterThan(0, $peserta->total_skor);

        // 6. Lihat halaman selesai
        $resSelesai = $this->get(route('pretest.public.selesai', ['token' => $pretest->token, 'p' => $peserta->guest_token]));
        $resSelesai->assertOk();
        $resSelesai->assertSee('Budi Santoso');
        $resSelesai->assertSee('Nilai Akhir Pre-Test');
    }

    public function test_guru_bisa_lihat_detail_dan_cetak_analisis(): void
    {
        $pretest = UjianPretest::create([
            'id_ujian' => $this->ujian->uuid,
            'token' => 'PRETEST2',
            'is_active' => true,
        ]);

        $peserta = UjianPretestPeserta::create([
            'id_pretest' => $pretest->uuid,
            'nama' => 'Siti Aisyah',
            'identitas' => 'Kelas 8A',
            'urutan_soal' => [$this->ujian->soal->first()->uuid],
            'status' => 'submitted',
            'mulai_pada' => now()->subMinutes(20),
            'selesai_pada' => now(),
            'total_skor' => 85.0,
            'jumlah_benar' => 1,
            'jumlah_salah' => 1,
            'guest_token' => 'guest-test-123',
        ]);

        // Cek halaman detail peserta oleh guru
        $resDetail = $this->actingAs($this->guruUser)->get(route('ujian.pretest.peserta.detail', [$this->ujian, $peserta]));
        $resDetail->assertOk();
        $resDetail->assertSee('Siti Aisyah');

        // Cek cetak analisis format Excel real ujian
        $resCetak = $this->actingAs($this->guruUser)->get(route('ujian.pretest.cetak-analisis', $this->ujian));
        $resCetak->assertOk();
        $resCetak->assertSee('ANALISIS HASIL UJI COBA PRE-TEST');
        $resCetak->assertSee('A. Analisis Nilai');
        $resCetak->assertSee('B. Objektif (Jawaban)');
        $resCetak->assertSee('Siti Aisyah');

        // Cek unduh Excel Analisis
        $resExcel = $this->actingAs($this->guruUser)->get(route('ujian.pretest.unduh-analisis', $this->ujian));
        $resExcel->assertOk();
        $this->assertTrue(str_contains((string) $resExcel->headers->get('content-disposition'), '.xlsx'));
    }
}
