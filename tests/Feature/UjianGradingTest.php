<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use App\Services\UjianGrader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 4 modul Ujian: penilaian objektif otomatis per tipe soal (unit-level, lewat
 * UjianGrader langsung) + alur penilaian esai manual guru (HTTP-level) yang
 * memfinalisasi attempt begitu esai TERAKHIR selesai dinilai.
 */
class UjianGradingTest extends TestCase
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
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        $this->guruUser = User::create(['username' => 'guru_nilai', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $this->guruUser->uuid, 'nama' => 'Guru Nilai', 'nik' => '3333333333', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => 'PTS Nilai', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
    }

    private function buatSiswa(string $username): User
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $this->kelas->uuid, 'nama' => ucfirst($username), 'nis' => (string) random_int(1000, 9999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return $user;
    }

    // ── Unit: scoring per tipe soal langsung lewat UjianGrader ─────────────────

    public function test_scoring_mcq_true_false_benar_salah(): void
    {
        $soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '?', 'poin' => 10, 'urutan' => 1]);
        $benar = UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);
        $soal->load('opsi');

        $grader = new UjianGrader();
        $jawabanBenar = new UjianJawaban(['id_opsi_dipilih' => $benar->uuid]);
        $jawabanSalah = new UjianJawaban(['id_opsi_dipilih' => 'uuid-lain']);

        $this->assertSame(['is_benar' => true, 'skor' => 10], $grader->scoreObjective($soal, $jawabanBenar));
        $this->assertSame(['is_benar' => false, 'skor' => 0], $grader->scoreObjective($soal, $jawabanSalah));
    }

    public function test_scoring_mcq_complex_semua_atau_tidak(): void
    {
        $soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq_complex', 'teks_soal' => '?', 'poin' => 10, 'urutan' => 1]);
        $o1 = UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        $o2 = UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'B', 'is_benar' => true, 'urutan' => 2]);
        $o3 = UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'C', 'is_benar' => false, 'urutan' => 3]);
        $soal->load('opsi');

        $grader = new UjianGrader();

        // Semua benar dipilih, tak ada yg salah -> skor penuh.
        $lengkap = new UjianJawaban(['opsi_dipilih_multi' => [$o1->uuid, $o2->uuid]]);
        $this->assertSame(['is_benar' => true, 'skor' => 10], $grader->scoreObjective($soal, $lengkap));

        // Kurang satu opsi benar -> 0 (bukan proporsional).
        $kurang = new UjianJawaban(['opsi_dipilih_multi' => [$o1->uuid]]);
        $this->assertSame(['is_benar' => false, 'skor' => 0], $grader->scoreObjective($soal, $kurang));

        // Benar semua TAPI ada opsi salah ikut terpilih -> 0.
        $kelebihan = new UjianJawaban(['opsi_dipilih_multi' => [$o1->uuid, $o2->uuid, $o3->uuid]]);
        $this->assertSame(['is_benar' => false, 'skor' => 0], $grader->scoreObjective($soal, $kelebihan));
    }

    public function test_scoring_match_proporsional(): void
    {
        $soal = UjianSoal::create([
            'id_ujian' => $this->ujian->uuid, 'tipe' => 'match', 'teks_soal' => '?', 'poin' => 10, 'urutan' => 1,
            'meta' => ['pairs' => [['left' => 'A', 'right' => '1'], ['left' => 'B', 'right' => '2'], ['left' => 'C', 'right' => '3'], ['left' => 'D', 'right' => '4']]],
        ]);
        $grader = new UjianGrader();

        // 2 dari 4 pasangan benar -> 5 poin (proporsional, bukan semua-atau-tidak).
        $jawaban = new UjianJawaban(['jawaban_pasangan' => ['A' => '1', 'B' => '2', 'C' => '4', 'D' => '3']]);
        $hasil = $grader->scoreObjective($soal, $jawaban);
        $this->assertFalse($hasil['is_benar']);
        $this->assertSame(5.0, $hasil['skor']);
    }

    // ── HTTP: alur submit + penilaian esai ──────────────────────────────────

    public function test_ujian_tanpa_esai_langsung_dinilai_saat_submit(): void
    {
        $soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10, 'urutan' => 1]);
        $benar = UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '5', 'is_benar' => false, 'urutan' => 2]);
        $ujianKelas = UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'TOKENNILAI1']);

        $siswa = $this->buatSiswa('siswa_nilai_tanpa_esai');
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENNILAI1'])->assertRedirect();
        $attempt = UjianAttempt::where('id_siswa', $siswa->uuid)->firstOrFail();

        $this->actingAs($siswa)->postJson(route('ujian.siswa.jawab', [$this->ujian, $attempt]), [
            'id_soal' => $soal->uuid, 'id_opsi_dipilih' => $benar->uuid,
        ])->assertOk();
        $this->actingAs($siswa)->post(route('ujian.siswa.submit', [$this->ujian, $attempt]))->assertRedirect();

        $attempt->refresh();
        $this->assertSame(UjianAttempt::STATUS_DINILAI, $attempt->status);
        $this->assertSame('100.00', $attempt->total_skor);
    }

    public function test_ujian_dengan_esai_baru_dinilai_setelah_esai_terakhir_dinilai_guru(): void
    {
        $mcq = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10, 'urutan' => 1]);
        $benar = UjianSoalOpsi::create(['id_soal' => $mcq->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $mcq->uuid, 'teks_opsi' => '5', 'is_benar' => false, 'urutan' => 2]);
        $essay = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Jelaskan', 'poin' => 10, 'urutan' => 2]);
        UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'TOKENNILAI2']);

        $siswa = $this->buatSiswa('siswa_nilai_esai');
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENNILAI2'])->assertRedirect();
        $attempt = UjianAttempt::where('id_siswa', $siswa->uuid)->firstOrFail();

        $this->actingAs($siswa)->postJson(route('ujian.siswa.jawab', [$this->ujian, $attempt]), ['id_soal' => $mcq->uuid, 'id_opsi_dipilih' => $benar->uuid])->assertOk();
        $this->actingAs($siswa)->postJson(route('ujian.siswa.jawab', [$this->ujian, $attempt]), ['id_soal' => $essay->uuid, 'jawaban_esai' => 'Jawaban esai saya.'])->assertOk();
        $this->actingAs($siswa)->post(route('ujian.siswa.submit', [$this->ujian, $attempt]))->assertRedirect();

        $attempt->refresh();
        $this->assertSame(UjianAttempt::STATUS_SUBMITTED, $attempt->status);
        $this->assertTrue($attempt->butuh_penilaian_manual);
        $this->assertNull($attempt->total_skor);

        // Guru menilai esai -> attempt langsung final (esai ini satu-satunya yg tersisa).
        $this->actingAs($this->guruUser)->post(route('ujian.grading.store', [$this->ujian, $attempt]), [
            'skor'    => [$essay->uuid => 8],
            'catatan' => [$essay->uuid => 'Cukup baik.'],
        ])->assertRedirect();

        $attempt->refresh();
        $this->assertSame(UjianAttempt::STATUS_DINILAI, $attempt->status);
        $this->assertFalse($attempt->butuh_penilaian_manual);
        $this->assertSame('90.00', $attempt->total_skor); // (10 mcq + 8 esai) / 20 * 100

        $jawabanEsai = UjianJawaban::where('id_attempt', $attempt->uuid)->where('id_soal', $essay->uuid)->firstOrFail();
        $this->assertTrue($jawabanEsai->dinilai_manual);
        $this->assertSame($this->guruUser->uuid, $jawabanEsai->dinilai_oleh);
        $this->assertSame('Cukup baik.', $jawabanEsai->catatan_guru);
    }

    public function test_skor_esai_diclamp_ke_maksimal_poin(): void
    {
        $essay = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Jelaskan', 'poin' => 10, 'urutan' => 1]);
        UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'TOKENCLAMP']);

        $siswa = $this->buatSiswa('siswa_clamp');
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENCLAMP'])->assertRedirect();
        $attempt = UjianAttempt::where('id_siswa', $siswa->uuid)->firstOrFail();
        $this->actingAs($siswa)->postJson(route('ujian.siswa.jawab', [$this->ujian, $attempt]), ['id_soal' => $essay->uuid, 'jawaban_esai' => 'x'])->assertOk();
        $this->actingAs($siswa)->post(route('ujian.siswa.submit', [$this->ujian, $attempt]))->assertRedirect();

        $this->actingAs($this->guruUser)->post(route('ujian.grading.store', [$this->ujian, $attempt]), [
            'skor' => [$essay->uuid => 999],
        ])->assertRedirect();

        $jawaban = UjianJawaban::where('id_attempt', $attempt->uuid)->where('id_soal', $essay->uuid)->firstOrFail();
        $this->assertSame('10.00', $jawaban->skor_diperoleh);
    }

    public function test_guru_yg_tak_mengajar_ujian_ini_tak_bisa_menilai(): void
    {
        $essay = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Jelaskan', 'poin' => 10, 'urutan' => 1]);
        UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'TOKENLAIN']);

        $siswa = $this->buatSiswa('siswa_guru_lain');
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENLAIN'])->assertRedirect();
        $attempt = UjianAttempt::where('id_siswa', $siswa->uuid)->firstOrFail();
        $this->actingAs($siswa)->post(route('ujian.siswa.submit', [$this->ujian, $attempt]))->assertRedirect();

        $guruLain = User::create(['username' => 'guru_lain_nilai', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $guruLain->uuid, 'nama' => 'Guru Lain', 'nik' => '4444444444', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($guruLain)->get(route('ujian.grading.index', $this->ujian))->assertForbidden();
        $this->actingAs($guruLain)->post(route('ujian.grading.store', [$this->ujian, $attempt]), ['skor' => [$essay->uuid => 5]])->assertForbidden();
    }
}
