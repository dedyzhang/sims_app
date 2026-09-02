<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
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
 * Fase 5 modul Ujian: Pemantauan/Hasil roster-driven — SEMUA siswa kelas ter-assign
 * tampil (bukan cuma yg sudah attempt), filter ?kelas=, dan drill-down per siswa
 * (/ujian/{ujian}/hasil/siswa/{siswa}) yg tetap resolve walau siswa belum mengerjakan.
 */
class UjianRosterHasilTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelasA;
    private Kelas $kelasB;
    private Ujian $ujian;
    private UjianKelas $ujianKelasA;
    private UjianKelas $ujianKelasB;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelasA = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $this->admin = User::create(['username' => 'admin_roster', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->admin->uuid,
            'judul' => 'PTS Roster', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
        $soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '5', 'is_benar' => false, 'urutan' => 2]);

        $this->ujianKelasA = UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $this->kelasA->uuid, 'token_masuk' => 'TOKENA01']);
        $this->ujianKelasB = UjianKelas::create(['id_ujian' => $this->ujian->uuid, 'id_kelas' => $this->kelasB->uuid, 'token_masuk' => 'TOKENB01']);
    }

    private function buatSiswa(string $username, Kelas $kelas): User
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $kelas->uuid, 'nama' => ucfirst($username), 'nis' => (string) random_int(1000, 9999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return $user;
    }

    public function test_hasil_menampilkan_siswa_yang_belum_mulai(): void
    {
        $belumMulai = $this->buatSiswa('siswa_belum_mulai', $this->kelasA);
        $sudahMulai = $this->buatSiswa('siswa_sudah_mulai', $this->kelasA);
        UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelasA->uuid, 'id_siswa' => $sudahMulai->uuid,
            'urutan_soal' => [], 'urutan_opsi' => [], 'status' => UjianAttempt::STATUS_IN_PROGRESS,
        ]);

        $res = $this->actingAs($this->admin)->get(route('ujian.hasil.index', $this->ujian));
        $res->assertOk();
        $res->assertSee('Belum Mulai');
        $res->assertSeeInOrder(['Siswa_belum_mulai', 'Siswa_sudah_mulai']);
    }

    public function test_filter_kelas_mempersempit_roster(): void
    {
        $this->buatSiswa('siswa_kelas_a', $this->kelasA);
        $this->buatSiswa('siswa_kelas_b', $this->kelasB);

        $res = $this->actingAs($this->admin)->get(route('ujian.hasil.index', $this->ujian) . '?kelas=' . $this->kelasA->uuid);
        $res->assertOk();
        $res->assertSee('Siswa_kelas_a');
        $res->assertDontSee('Siswa_kelas_b');
    }

    public function test_monitor_poll_mengembalikan_siswa_belum_mulai_dan_kelas_opsi(): void
    {
        $this->buatSiswa('siswa_poll_belum_mulai', $this->kelasA);

        $res = $this->actingAs($this->admin)->getJson(route('ujian.monitor.poll', $this->ujian));
        $res->assertOk();
        $data = $res->json();
        $this->assertNotEmpty($data['attempts']);
        $this->assertSame('belum_mulai', collect($data['attempts'])->firstWhere('nama', 'Siswa_poll_belum_mulai')['status']);
        $this->assertCount(2, $data['kelasOpsi']);
    }

    public function test_hasil_detail_menampilkan_rincian_jawaban_siswa_yang_sudah_mengerjakan(): void
    {
        $siswa = $this->buatSiswa('siswa_detail_jawab', $this->kelasA);
        UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelasA->uuid, 'id_siswa' => $siswa->uuid,
            'urutan_soal' => [], 'urutan_opsi' => [], 'status' => UjianAttempt::STATUS_DINILAI,
            'total_skor' => 10, 'skor_objektif' => 10,
        ]);

        $res = $this->actingAs($this->admin)->get(route('ujian.hasil.detail', [$this->ujian, $siswa]));
        $res->assertOk();
        $res->assertSee('2+2=?', false);
    }

    public function test_hasil_detail_menampilkan_state_kosong_utk_siswa_yang_belum_mengerjakan(): void
    {
        $siswa = $this->buatSiswa('siswa_detail_belum', $this->kelasA);

        $res = $this->actingAs($this->admin)->get(route('ujian.hasil.detail', [$this->ujian, $siswa]));
        $res->assertOk();
        $res->assertSee('belum mengerjakan');
    }

    /** Bug report FL: skor jangan kosong ("—") selagi esai belum dinilai — tampilkan skor objektif sementara, bukan nunggu status 'dinilai' penuh. */
    public function test_hasil_index_menampilkan_skor_sementara_selagi_esai_belum_dinilai(): void
    {
        $siswa = $this->buatSiswa('siswa_skor_sementara', $this->kelasA);
        UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelasA->uuid, 'id_siswa' => $siswa->uuid,
            'urutan_soal' => [], 'urutan_opsi' => [], 'status' => UjianAttempt::STATUS_SUBMITTED,
            'skor_objektif' => 10, 'butuh_penilaian_manual' => true,
        ]);

        $res = $this->actingAs($this->admin)->get(route('ujian.hasil.index', $this->ujian));
        $res->assertOk();
        // 1 soal poin 10, mode default rata_rata -> 10/10*100 = 100.0
        $res->assertSee('100.0');
        $res->assertSee('(sementara)');
    }

    public function test_hasil_detail_menampilkan_skor_sementara_selagi_esai_belum_dinilai(): void
    {
        $siswa = $this->buatSiswa('siswa_detail_sementara', $this->kelasA);
        UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelasA->uuid, 'id_siswa' => $siswa->uuid,
            'urutan_soal' => [], 'urutan_opsi' => [], 'status' => UjianAttempt::STATUS_SUBMITTED,
            'skor_objektif' => 10, 'butuh_penilaian_manual' => true,
        ]);

        $res = $this->actingAs($this->admin)->get(route('ujian.hasil.detail', [$this->ujian, $siswa]));
        $res->assertOk();
        $res->assertSee('Skor Sementara');
        $res->assertSee('100.0');
        $res->assertSee('esai belum dinilai');
    }

    public function test_guru_bukan_pemilik_ujian_ditolak_akses_hasil_detail(): void
    {
        $guruLainUser = User::create(['username' => 'guru_lain_roster', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $guruLainUser->uuid, 'nama' => 'Guru Lain', 'nik' => (string) random_int(1000000000, 9999999999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        $siswa = $this->buatSiswa('siswa_detail_ditolak', $this->kelasA);

        $this->actingAs($guruLainUser)
            ->get(route('ujian.hasil.detail', [$this->ujian, $siswa]))
            ->assertForbidden();
    }
}
