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
 * Fase 3 modul Ujian: randomisasi urutan_soal/urutan_opsi dibuat SEKALI di start()
 * dan dipersist di ujian_attempts — beda siswa dapat urutan beda, tapi siswa yg
 * sama tak pernah diacak ulang walau reload berkali-kali.
 */
class UjianRandomizationTest extends TestCase
{
    use RefreshDatabase;

    private Kelas $kelas;
    private Ujian $ujian;

    protected function setUp(): void
    {
        parent::setUp();
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        $guruUser = User::create(['username' => 'guru_acak', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Acak', 'nik' => '1111111111', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $guruUser->uuid,
            'judul' => 'PTS Acak', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'published', 'acak_soal' => true, 'acak_opsi' => true,
        ]);

        // 8 soal supaya peluang dua siswa kebetulan dapat urutan identik sangat kecil (1/8!).
        for ($i = 1; $i <= 8; $i++) {
            UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'essay', 'teks_soal' => "Soal $i", 'poin' => 10, 'urutan' => $i]);
        }

        UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'TOKENACAK']);
    }

    private function buatSiswa(string $username): User
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $this->kelas->uuid, 'nama' => ucfirst($username), 'nis' => (string) random_int(1000, 9999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return $user;
    }

    public function test_dua_siswa_dapat_urutan_soal_berbeda(): void
    {
        $siswaA = $this->buatSiswa('siswa_acak_a');
        $siswaB = $this->buatSiswa('siswa_acak_b');

        $this->actingAs($siswaA)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENACAK'])->assertRedirect();
        $this->actingAs($siswaB)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENACAK'])->assertRedirect();

        $urutanA = UjianAttempt::where('id_siswa', $siswaA->uuid)->firstOrFail()->urutan_soal;
        $urutanB = UjianAttempt::where('id_siswa', $siswaB->uuid)->firstOrFail()->urutan_soal;

        $this->assertNotSame($urutanA, $urutanB, 'Dua siswa seharusnya dapat urutan soal yang berbeda.');
        $this->assertEqualsCanonicalizing($urutanA, $urutanB, 'Tapi tetap harus berisi himpunan soal yang sama.');
    }

    public function test_urutan_soal_tidak_berubah_lagi_setelah_reload_berkali_kali(): void
    {
        $siswa = $this->buatSiswa('siswa_acak_reload');
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENACAK'])->assertRedirect();

        $attempt = UjianAttempt::where('id_siswa', $siswa->uuid)->firstOrFail();
        $urutanAwal = $attempt->urutan_soal;

        // Render kerjakan beberapa kali -> urutan tersimpan di DB tak boleh berubah.
        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($siswa)->get(route('ujian.siswa.kerjakan', [$this->ujian, $attempt]))->assertOk();
        }

        $this->assertSame($urutanAwal, $attempt->fresh()->urutan_soal);
    }
}
