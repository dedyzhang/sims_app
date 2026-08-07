<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Materi;
use App\Models\Ngajar;
use App\Models\NilaiPas;
use App\Models\NilaiPts;
use App\Models\NilaiSumatif;
use App\Models\Pelajaran;
use App\Models\RaporKonfirmasi;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 4 modul Ujian: transfer nilai OTOMATIS (tanpa klik manual) begitu attempt
 * selesai dinilai — jalur PTS/PAS (via Ngajar+Semester aktif) & jalur Sumatif
 * (via Materi->id_ngajar+id_semester sendiri), keduanya taat kunci RaporKonfirmasi.
 */
class UjianNilaiTransferTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;
    private Guru $guru;
    private User $guruUser;
    private Ngajar $ngajar;
    private Semester $semester;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);

        $this->guruUser = User::create(['username' => 'guru_transfer', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $this->guru = Guru::create(['id_login' => $this->guruUser->uuid, 'nama' => 'Guru Transfer', 'nik' => '6666666666', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $this->ngajar = Ngajar::create(['id_guru' => $this->guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
    }

    private function buatSiswaDenganUjian(Ujian $ujian, UjianKelas $ujianKelas, UjianSoal $soal, UjianSoalOpsi $opsiBenar, string $username): array
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $siswa = Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $ujianKelas->id_kelas, 'nama' => ucfirst($username), 'nis' => (string) random_int(1000, 9999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($user)->post(route('ujian.siswa.start', $ujian), ['token' => $ujianKelas->token_masuk])->assertRedirect();
        $attempt = UjianAttempt::where('id_siswa', $user->uuid)->firstOrFail();
        $this->actingAs($user)->postJson(route('ujian.siswa.jawab', [$ujian, $attempt]), ['id_soal' => $soal->uuid, 'id_opsi_dipilih' => $opsiBenar->uuid])->assertOk();
        $this->actingAs($user)->post(route('ujian.siswa.submit', [$ujian, $attempt]))->assertRedirect();

        return [$user, $siswa, $attempt->fresh()];
    }

    private function buatUjianPts(): array
    {
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => 'PTS Transfer', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
        $soal = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10, 'urutan' => 1]);
        $benar = UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '5', 'is_benar' => false, 'urutan' => 2]);
        $ujianKelas = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'TRANSFERPTS']);

        return [$ujian, $ujianKelas, $soal, $benar];
    }

    public function test_jalur_pts_masuk_otomatis_ke_nilai_pts(): void
    {
        [$ujian, $ujianKelas, $soal, $benar] = $this->buatUjianPts();
        [, $siswa, $attempt] = $this->buatSiswaDenganUjian($ujian, $ujianKelas, $soal, $benar, 'siswa_transfer_pts');

        $this->assertSame(UjianAttempt::STATUS_DINILAI, $attempt->status);
        $this->assertSame('berhasil', $attempt->status_transfer_nilai);

        $this->assertDatabaseHas('nilai_pts', [
            'id_ngajar' => $this->ngajar->uuid, 'id_siswa' => $siswa->uuid,
            'id_semester' => $this->semester->id, 'nilai' => 100,
        ]);
    }

    public function test_jalur_pas_masuk_otomatis_ke_nilai_pas(): void
    {
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => 'PAS Transfer', 'jenis' => 'pas', 'target_nilai' => 'pas',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
        $soal = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '?', 'poin' => 10, 'urutan' => 1]);
        $benar = UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);
        $ujianKelas = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'TRANSFERPAS']);

        [, $siswa] = $this->buatSiswaDenganUjian($ujian, $ujianKelas, $soal, $benar, 'siswa_transfer_pas');

        $this->assertDatabaseHas('nilai_pas', [
            'id_ngajar' => $this->ngajar->uuid, 'id_siswa' => $siswa->uuid,
            'id_semester' => $this->semester->id, 'nilai' => 100,
        ]);
        $this->assertDatabaseMissing('nilai_pts', ['id_siswa' => $siswa->uuid]);
    }

    public function test_jalur_sumatif_masuk_otomatis_ke_nilai_sumatif(): void
    {
        $materi = Materi::create(['id_ngajar' => $this->ngajar->uuid, 'nama' => 'Bab 1', 'id_semester' => $this->semester->id, 'urutan' => 1]);
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'id_materi' => $materi->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => 'Harian Transfer', 'jenis' => 'harian', 'target_nilai' => 'sumatif',
            'durasi_menit' => 40, 'status' => 'published',
        ]);
        $soal = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '?', 'poin' => 10, 'urutan' => 1]);
        $benar = UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);
        $ujianKelas = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'TRANSFERSUM']);

        [, $siswa] = $this->buatSiswaDenganUjian($ujian, $ujianKelas, $soal, $benar, 'siswa_transfer_sum');

        $this->assertDatabaseHas('nilai_sumatif', ['id_materi' => $materi->uuid, 'id_siswa' => $siswa->uuid, 'nilai' => 100]);
    }

    public function test_rapor_terkunci_membatalkan_transfer_dan_skor_tetap_aman(): void
    {
        RaporKonfirmasi::create(['id_ngajar' => $this->ngajar->uuid, 'id_semester' => $this->semester->id]);
        [$ujian, $ujianKelas, $soal, $benar] = $this->buatUjianPts();
        [, $siswa, $attempt] = $this->buatSiswaDenganUjian($ujian, $ujianKelas, $soal, $benar, 'siswa_transfer_terkunci');

        $this->assertSame(UjianAttempt::STATUS_DINILAI, $attempt->status, 'Attempt tetap difinalisasi walau transfer gagal.');
        $this->assertSame('gagal_terkunci', $attempt->status_transfer_nilai);
        $this->assertSame('100.00', $attempt->total_skor, 'Skor tak boleh hilang walau transfer gagal.');
        $this->assertDatabaseMissing('nilai_pts', ['id_siswa' => $siswa->uuid]);
    }

    public function test_transfer_ulang_manual_setelah_unlock_berhasil(): void
    {
        $konfirmasi = RaporKonfirmasi::create(['id_ngajar' => $this->ngajar->uuid, 'id_semester' => $this->semester->id]);
        [$ujian, $ujianKelas, $soal, $benar] = $this->buatUjianPts();
        [, $siswa, $attempt] = $this->buatSiswaDenganUjian($ujian, $ujianKelas, $soal, $benar, 'siswa_transfer_ulang');
        $this->assertSame('gagal_terkunci', $attempt->status_transfer_nilai);

        $konfirmasi->delete(); // admin membuka kunci rapor

        $this->actingAs($this->guruUser)->post(route('ujian.hasil.transferUlang', [$ujian, $attempt]))->assertRedirect();

        $attempt->refresh();
        $this->assertSame('berhasil', $attempt->status_transfer_nilai);
        $this->assertDatabaseHas('nilai_pts', ['id_ngajar' => $this->ngajar->uuid, 'id_siswa' => $siswa->uuid, 'nilai' => 100]);
    }

    public function test_transfer_dobel_tak_bikin_baris_ganda(): void
    {
        [$ujian, $ujianKelas, $soal, $benar] = $this->buatUjianPts();
        [, $siswa, $attempt] = $this->buatSiswaDenganUjian($ujian, $ujianKelas, $soal, $benar, 'siswa_transfer_dobel');

        $this->actingAs($this->guruUser)->post(route('ujian.hasil.transferUlang', [$ujian, $attempt]))->assertRedirect();
        $this->actingAs($this->guruUser)->post(route('ujian.hasil.transferUlang', [$ujian, $attempt]))->assertRedirect();

        $this->assertSame(1, NilaiPts::where('id_siswa', $siswa->uuid)->count());
    }
}
