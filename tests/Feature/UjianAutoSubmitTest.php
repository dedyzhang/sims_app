<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
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
 * Fase 4 modul Ujian: command `ujian:auto-submit` — jaring pengaman server-side
 * yg memfinalisasi paksa attempt lewat deadline (dijadwalkan tiap menit).
 */
class UjianAutoSubmitTest extends TestCase
{
    use RefreshDatabase;

    private Ujian $ujian;
    private UjianKelas $ujianKelas;
    private UjianSoal $soal;
    private UjianSoalOpsi $opsiBenar;

    protected function setUp(): void
    {
        parent::setUp();
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        $guruUser = User::create(['username' => 'guru_auto', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Auto', 'nik' => '5555555555', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $guruUser->uuid,
            'judul' => 'PTS Auto', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 30, 'status' => 'published',
        ]);
        $this->soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10, 'urutan' => 1]);
        $this->opsiBenar = UjianSoalOpsi::create(['id_soal' => $this->soal->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $this->soal->uuid, 'teks_opsi' => '5', 'is_benar' => false, 'urutan' => 2]);
        $this->ujianKelas = UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $kelas->uuid, 'token_masuk' => 'AUTOTOKEN']);
    }

    private function buatAttempt(string $username, \Carbon\Carbon $batasWaktu, bool $dikunci = false): UjianAttempt
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $this->ujianKelas->id_kelas, 'nama' => ucfirst($username), 'nis' => (string) random_int(1000, 9999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        return UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid, 'id_siswa' => $user->uuid,
            'urutan_soal' => [$this->soal->uuid], 'mulai_pada' => $batasWaktu->copy()->subMinutes(30),
            'batas_waktu_pada' => $batasWaktu, 'status' => UjianAttempt::STATUS_IN_PROGRESS, 'dikunci' => $dikunci,
        ]);
    }

    public function test_attempt_lewat_deadline_difinalisasi_paksa(): void
    {
        $batas = now()->subMinutes(5);
        $attempt = $this->buatAttempt('siswa_auto_lewat', $batas);

        $this->artisan('ujian:auto-submit')->assertSuccessful();

        $attempt->refresh();
        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertTrue($attempt->auto_submit);
        // Bandingkan ke detik (kolom DB tak menyimpan mikrodetik) — yg penting
        // selesai_pada dipatok ke batas_waktu_pada asli, bukan now() saat command jalan.
        $this->assertSame($batas->format('Y-m-d H:i:s'), $attempt->selesai_pada->format('Y-m-d H:i:s'));
    }

    public function test_attempt_belum_lewat_deadline_tak_tersentuh(): void
    {
        $attempt = $this->buatAttempt('siswa_auto_belum', now()->addMinutes(10));

        $this->artisan('ujian:auto-submit')->assertSuccessful();

        $this->assertSame(UjianAttempt::STATUS_IN_PROGRESS, $attempt->fresh()->status);
        $this->assertFalse($attempt->fresh()->auto_submit);
    }

    public function test_attempt_terkunci_yg_lewat_deadline_tetap_difinalisasi(): void
    {
        $attempt = $this->buatAttempt('siswa_auto_terkunci', now()->subMinutes(1), dikunci: true);

        $this->artisan('ujian:auto-submit')->assertSuccessful();

        $attempt->refresh();
        $this->assertNotSame(UjianAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertTrue($attempt->auto_submit);
    }

    public function test_jalankan_command_dua_kali_tak_dobel_skor(): void
    {
        $attempt = $this->buatAttempt('siswa_auto_dobel', now()->subMinutes(5));
        // Jawab benar supaya ada skor nyata utk dipastikan tak berubah kalau di-run ulang.
        \App\Models\UjianJawaban::create([
            'id_attempt' => $attempt->uuid, 'id_soal' => $this->soal->uuid, 'id_opsi_dipilih' => $this->opsiBenar->uuid,
        ]);

        $this->artisan('ujian:auto-submit')->assertSuccessful();
        $skorSetelahPertama = $attempt->fresh()->skor_objektif;

        $this->artisan('ujian:auto-submit')->assertSuccessful();
        $this->assertSame($skorSetelahPertama, $attempt->fresh()->skor_objektif);
    }
}
