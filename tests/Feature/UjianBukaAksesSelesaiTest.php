<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\NilaiPts;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Ronde perbaikan Ujian, poin #1: "Buka Kembali Akses" dari halaman Hasil kini membuka
 * kembali attempt yg SAMA (bukan soft-cancel + attempt baru spt UjianMonitorController::
 * resetAttempt()) — jawaban siswa yg sudah tersimpan TETAP ADA, siswa lanjut dari titik
 * terakhir. DEMI KEAMANAN, siswa TETAP wajib masukkan token yg benar lagi lewat gate()
 * sebelum bisa melanjutkan (wajib_token_ulang) — reopening tak boleh bisa "diam-diam"
 * dipakai tanpa sepengetahuan pengawas/guru yg memegang token.
 */
class UjianBukaAksesSelesaiTest extends TestCase
{
    use RefreshDatabase;

    private Ujian $ujian;
    private UjianKelas $ujianKelas;
    private User $guruUser;
    private User $siswaUser;
    private UjianAttempt $attempt;
    private Ngajar $ngajar;
    private UjianSoal $soal;
    private UjianSoalOpsi $opsiBenar;

    protected function setUp(): void
    {
        parent::setUp();
        $pelajaran = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);

        $this->guruUser = User::create(['username' => 'guru_buka_akses', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $this->guruUser->uuid, 'nama' => 'Guru Buka Akses', 'nik' => '6666666666', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $this->ngajar = Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => 'PTS Buka Akses', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
        $this->soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '?', 'poin' => 10, 'urutan' => 1]);
        $this->opsiBenar = UjianSoalOpsi::create(['id_soal' => $this->soal->uuid, 'teks_opsi' => 'A', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $this->soal->uuid, 'teks_opsi' => 'B', 'is_benar' => false, 'urutan' => 2]);
        $this->ujianKelas = UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $kelas->uuid, 'token_masuk' => 'BUKAAKSES1']);

        $this->siswaUser = User::create(['username' => 'siswa_buka_akses', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $this->siswaUser->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Buka Akses', 'nis' => '8001', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'BUKAAKSES1'])->assertRedirect();
        $this->attempt = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail();

        $this->actingAs($this->siswaUser)->postJson(route('ujian.siswa.jawab', [$this->ujian, $this->attempt]), [
            'id_soal' => $this->soal->uuid, 'id_opsi_dipilih' => $this->opsiBenar->uuid,
        ])->assertOk();
        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.submit', [$this->ujian, $this->attempt]))->assertRedirect();
        $this->attempt->refresh();
    }

    public function test_buka_akses_membuka_kembali_attempt_yang_sama_dan_jawaban_tetap_ada(): void
    {
        $this->assertSame(UjianAttempt::STATUS_DINILAI, $this->attempt->status);

        $this->actingAs($this->guruUser)->post(route('ujian.hasil.bukaAkses', [$this->ujian, $this->attempt]))->assertRedirect();

        $this->attempt->refresh();
        $this->assertSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->status);
        $this->assertFalse($this->attempt->dikunci);
        $this->assertNull($this->attempt->selesai_pada);
        $this->assertTrue($this->attempt->batas_waktu_pada->isFuture());

        // Attempt yg SAMA (uuid tak berubah) & masih SATU-SATUNYA milik siswa ini.
        $this->assertSame(1, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->count());

        // Jawaban lama TETAP ADA & nilainya tak berubah.
        $jawaban = UjianJawaban::where('id_attempt', $this->attempt->uuid)->where('id_soal', $this->soal->uuid)->firstOrFail();
        $this->assertSame($this->opsiBenar->uuid, $jawaban->id_opsi_dipilih);
    }

    public function test_gate_minta_token_lagi_setelah_dibuka_dan_bukan_langsung_ke_kerjakan(): void
    {
        $this->actingAs($this->guruUser)->post(route('ujian.hasil.bukaAkses', [$this->ujian, $this->attempt]))->assertRedirect();
        $this->assertTrue($this->attempt->fresh()->wajib_token_ulang);

        // gate() TIDAK langsung redirect ke kerjakan — tampilkan form token lagi (demi keamanan).
        $res = $this->actingAs($this->siswaUser)->get(route('ujian.siswa.gate', $this->ujian));
        $res->assertOk();
        $res->assertViewIs('ujian.siswa.gate');
    }

    public function test_token_salah_ditolak_saat_melanjutkan_attempt_yang_dibuka_kembali(): void
    {
        $this->actingAs($this->guruUser)->post(route('ujian.hasil.bukaAkses', [$this->ujian, $this->attempt]))->assertRedirect();

        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENSALAH'])
            ->assertSessionHasErrors('token');

        // Attempt tak berubah statusnya & wajib_token_ulang TETAP true — belum berhasil melanjutkan.
        $this->assertTrue($this->attempt->fresh()->wajib_token_ulang);
        $this->assertSame(1, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->count());
    }

    public function test_token_benar_melanjutkan_attempt_yang_sama_dan_lepas_wajib_token_ulang(): void
    {
        $this->actingAs($this->guruUser)->post(route('ujian.hasil.bukaAkses', [$this->ujian, $this->attempt]))->assertRedirect();

        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'BUKAAKSES1'])
            ->assertRedirect(route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]));

        $this->attempt->refresh();
        $this->assertFalse($this->attempt->wajib_token_ulang);
        // Attempt yg SAMA (uuid tak berubah, bukan attempt baru) & jawaban lama tetap ada.
        $this->assertSame(1, UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->count());
        $jawaban = UjianJawaban::where('id_attempt', $this->attempt->uuid)->where('id_soal', $this->soal->uuid)->firstOrFail();
        $this->assertSame($this->opsiBenar->uuid, $jawaban->id_opsi_dipilih);

        // Setelah wajib_token_ulang lepas, gate() langsung ke kerjakan lagi spt attempt in_progress biasa.
        $this->actingAs($this->siswaUser)->get(route('ujian.siswa.gate', $this->ujian))
            ->assertRedirect(route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]));

        $res = $this->actingAs($this->siswaUser)->get(route('ujian.siswa.kerjakan', [$this->ujian, $this->attempt]));
        $res->assertOk();
        // Jawaban sebelumnya (opsiBenar) ikut ter-embed di payload soalTampil/jawabanTersimpan halaman ini.
        $res->assertSee($this->opsiBenar->uuid);
    }

    public function test_buka_akses_yang_sudah_tertransfer_ikut_menghapus_nilai_pts(): void
    {
        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $siswa = Siswa::where('id_login', $this->siswaUser->uuid)->firstOrFail();
        $nilaiPts = NilaiPts::create(['id_ngajar' => $this->ngajar->uuid, 'id_siswa' => $siswa->uuid, 'id_semester' => $semester->id, 'nilai' => 90]);
        $this->attempt->update(['total_skor' => 90, 'status_transfer_nilai' => 'berhasil']);

        $this->actingAs($this->guruUser)->post(route('ujian.hasil.bukaAkses', [$this->ujian, $this->attempt]))->assertRedirect();

        $this->attempt->refresh();
        $this->assertSame(UjianAttempt::STATUS_IN_PROGRESS, $this->attempt->status);
        $this->assertSame('belum', $this->attempt->status_transfer_nilai);
        $this->assertDatabaseMissing('nilai_pts', ['uuid' => $nilaiPts->uuid]);
    }

    public function test_buka_akses_yang_belum_pernah_tertransfer_tak_menyentuh_nilai(): void
    {
        $semester = Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);
        $siswa = Siswa::where('id_login', $this->siswaUser->uuid)->firstOrFail();
        $nilaiPtsTakTerkait = NilaiPts::create(['id_ngajar' => $this->ngajar->uuid, 'id_siswa' => $siswa->uuid, 'id_semester' => $semester->id, 'nilai' => 70]);

        $this->actingAs($this->guruUser)->post(route('ujian.hasil.bukaAkses', [$this->ujian, $this->attempt]))->assertRedirect();

        $this->assertDatabaseHas('nilai_pts', ['uuid' => $nilaiPtsTakTerkait->uuid]);
    }

    public function test_tak_bisa_buka_akses_attempt_yang_masih_in_progress(): void
    {
        $siswaLain = User::create(['username' => 'siswa_masih_jalan', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswaLain->uuid, 'id_kelas' => $this->ujianKelas->id_kelas, 'nama' => 'Siswa Masih Jalan', 'nis' => '8002', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $this->actingAs($siswaLain)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'BUKAAKSES1'])->assertRedirect();
        $attemptJalan = UjianAttempt::where('id_siswa', $siswaLain->uuid)->firstOrFail();

        $this->actingAs($this->guruUser)->post(route('ujian.hasil.bukaAkses', [$this->ujian, $attemptJalan]))->assertStatus(422);
    }

    public function test_tak_bisa_buka_akses_dua_kali(): void
    {
        $this->actingAs($this->guruUser)->post(route('ujian.hasil.bukaAkses', [$this->ujian, $this->attempt]))->assertRedirect();
        $this->actingAs($this->guruUser)->post(route('ujian.hasil.bukaAkses', [$this->ujian, $this->attempt]))->assertStatus(422);
    }

    public function test_guru_lain_tak_bisa_buka_akses(): void
    {
        $guruLain = User::create(['username' => 'guru_buka_akses_lain', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $guruLain->uuid, 'nama' => 'Guru Lain', 'nik' => '5555555555', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($guruLain)->post(route('ujian.hasil.bukaAkses', [$this->ujian, $this->attempt]))->assertForbidden();
        $this->assertSame(UjianAttempt::STATUS_DINILAI, $this->attempt->fresh()->status);
    }
}
