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
 * Fase 3 modul Ujian: keluar layar penuh / ganti tab -> attempt terkunci + tercatat
 * di ujian_pelanggaran, dan selama terkunci endpoint jawab/kumpul wajib 403.
 * Reset-kunci oleh guru (UjianMonitorController) baru dibangun Fase 5 — belum diuji di sini.
 */
class UjianLockoutTest extends TestCase
{
    use RefreshDatabase;

    private Ujian $ujian;
    private UjianAttempt $attempt;
    private User $siswaUser;
    private UjianSoal $soal;

    protected function setUp(): void
    {
        parent::setUp();
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        $guruUser = User::create(['username' => 'guru_lock', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Lock', 'nik' => '2222222222', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $guruUser->uuid,
            'judul' => 'PTS Kunci', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
        $this->soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $this->soal->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $this->soal->uuid, 'teks_opsi' => '5', 'is_benar' => false, 'urutan' => 2]);

        $ujianKelas = UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $kelas->uuid, 'token_masuk' => 'TOKENLOCK']);

        $this->siswaUser = User::create(['username' => 'siswa_lock', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $this->siswaUser->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Lock', 'nis' => '5001', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($this->siswaUser)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENLOCK'])->assertRedirect();
        $this->attempt = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail();
    }

    public function test_keluar_fullscreen_mengunci_attempt_dan_mencatat_pelanggaran(): void
    {
        $this->actingAs($this->siswaUser)
            ->postJson(route('ujian.siswa.keluar', [$this->ujian, $this->attempt]), ['tipe' => 'keluar_fullscreen'])
            ->assertOk()->assertJson(['ok' => true]);

        $this->attempt->refresh();
        $this->assertTrue($this->attempt->dikunci);
        $this->assertDatabaseHas('ujian_pelanggaran', [
            'id_attempt' => $this->attempt->uuid, 'id_siswa' => $this->siswaUser->uuid, 'tipe' => 'keluar_fullscreen',
        ]);
    }

    public function test_ganti_tab_juga_mengunci_dengan_tipe_berbeda(): void
    {
        $this->actingAs($this->siswaUser)
            ->postJson(route('ujian.siswa.keluar', [$this->ujian, $this->attempt]), ['tipe' => 'ganti_tab'])
            ->assertOk();

        $this->assertDatabaseHas('ujian_pelanggaran', ['id_attempt' => $this->attempt->uuid, 'tipe' => 'ganti_tab']);
        $this->assertTrue($this->attempt->fresh()->dikunci);
    }

    public function test_saat_terkunci_jawab_dan_kumpul_ditolak(): void
    {
        $this->attempt->update(['dikunci' => true]);

        $this->actingAs($this->siswaUser)->postJson(route('ujian.siswa.jawab', [$this->ujian, $this->attempt]), [
            'id_soal' => $this->soal->uuid, 'id_opsi_dipilih' => $this->soal->opsi()->first()->uuid,
        ])->assertForbidden();

        $this->actingAs($this->siswaUser)->postJson(route('ujian.siswa.submit', [$this->ujian, $this->attempt]))->assertForbidden();

        $this->assertDatabaseMissing('ujian_jawaban', ['id_attempt' => $this->attempt->uuid, 'id_soal' => $this->soal->uuid]);
    }

    public function test_lapor_keluar_dua_kali_tidak_dobel_catat_pelanggaran(): void
    {
        $this->actingAs($this->siswaUser)->postJson(route('ujian.siswa.keluar', [$this->ujian, $this->attempt]), ['tipe' => 'keluar_fullscreen'])->assertOk();
        $this->actingAs($this->siswaUser)->postJson(route('ujian.siswa.keluar', [$this->ujian, $this->attempt]), ['tipe' => 'keluar_fullscreen'])->assertOk();

        $this->assertSame(1, \App\Models\UjianPelanggaran::where('id_attempt', $this->attempt->uuid)->count());
    }

    public function test_siswa_lain_tidak_bisa_lapor_keluar_utk_attempt_orang(): void
    {
        $siswaLain = User::create(['username' => 'siswa_lock_lain', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswaLain->uuid, 'id_kelas' => $this->attempt->ujianKelas->id_kelas, 'nama' => 'Siswa Lain', 'nis' => '5002', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($siswaLain)
            ->postJson(route('ujian.siswa.keluar', [$this->ujian, $this->attempt]), ['tipe' => 'keluar_fullscreen'])
            ->assertForbidden();

        $this->assertFalse($this->attempt->fresh()->dikunci);
    }
}
