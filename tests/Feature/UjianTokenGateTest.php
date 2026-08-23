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
 * Fase 3 modul Ujian: gerbang token per-kelas — token salah ditolak, token benar
 * membuat SATU attempt lalu start() berikutnya cuma resume (tak dobel), siswa di
 * luar kelas ter-assign / di luar jendela waktu ditolak lewat UjianPolicy::take().
 */
class UjianTokenGateTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;
    private Ujian $ujian;
    private UjianKelas $ujianKelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        [$guruUser, $guru] = $this->buatGuru('guru_gate');
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $guruUser->uuid,
            'judul' => 'PTS Gerbang', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
        $soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=?', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $soal->uuid, 'teks_opsi' => '5', 'is_benar' => false, 'urutan' => 2]);

        $this->ujianKelas = UjianKelas::create([
            'id_ujian' => $this->ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => 'RAHASIA1',
        ]);
    }

    private function buatGuru(string $username): array
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $user->uuid, 'nama' => ucfirst($username), 'nik' => (string) random_int(1000000000, 9999999999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return [$user, $guru];
    }

    private function buatSiswa(string $username, Kelas $kelas): User
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $kelas->uuid, 'nama' => ucfirst($username), 'nis' => (string) random_int(1000, 9999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return $user;
    }

    public function test_token_salah_ditolak_tanpa_membuat_attempt(): void
    {
        $siswa = $this->buatSiswa('siswa_token_salah', $this->kelas);

        $this->actingAs($siswa)
            ->post(route('ujian.siswa.start', $this->ujian), ['token' => 'SALAH123'])
            ->assertSessionHasErrors('token');

        $this->assertSame(0, UjianAttempt::where('id_siswa', $siswa->uuid)->count());
    }

    public function test_token_benar_membuat_attempt_dan_start_ulang_hanya_resume(): void
    {
        $siswa = $this->buatSiswa('siswa_token_benar', $this->kelas);

        $res = $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'RAHASIA1']);
        $res->assertRedirect();
        $this->assertSame(1, UjianAttempt::where('id_siswa', $siswa->uuid)->count());

        $attempt = UjianAttempt::where('id_siswa', $siswa->uuid)->firstOrFail();
        $res->assertRedirect(route('ujian.siswa.kerjakan', [$this->ujian, $attempt]));

        // Panggil start() lagi (mis. token diketik ulang) -> tetap satu baris (resume).
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'RAHASIA1'])->assertRedirect();
        $this->assertSame(1, UjianAttempt::where('id_siswa', $siswa->uuid)->count());
    }

    public function test_siswa_di_luar_kelas_ter_assign_ditolak(): void
    {
        $kelasLain = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $siswaLuar = $this->buatSiswa('siswa_luar_gate', $kelasLain);

        $this->actingAs($siswaLuar)->get(route('ujian.siswa.gate', $this->ujian))->assertNotFound();
        // start() cek keanggotaan kelas SEBELUM authorize('take'), jadi siswa yg
        // kelasnya tak ter-assign sama sekali dapat 404 (bukan 403) di titik itu.
        $this->actingAs($siswaLuar)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'RAHASIA1'])->assertNotFound();
        $this->assertSame(0, UjianAttempt::where('id_siswa', $siswaLuar->uuid)->count());
    }

    public function test_di_luar_jendela_waktu_ditolak(): void
    {
        $this->ujianKelas->update(['dibuka_sampai' => now()->subDay()]);
        $siswa = $this->buatSiswa('siswa_luar_jendela', $this->kelas);

        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => 'RAHASIA1'])->assertForbidden();
        $this->assertSame(0, UjianAttempt::where('id_siswa', $siswa->uuid)->count());
    }

    /** Bug report FL: ujian yg sudah terbit tapi jam bukanya blm sampai HARUS tetap tampil di daftar (bukan hilang), dgn tanda jelas "Belum Dimulai" + jam bukanya, bukan cuma badge generik. */
    public function test_index_siswa_menampilkan_badge_belum_dimulai_dan_jam_buka(): void
    {
        $this->ujianKelas->update(['dibuka_mulai' => now()->addDay()]);
        $siswa = $this->buatSiswa('siswa_belum_mulai', $this->kelas);

        $res = $this->actingAs($siswa)->get(route('ujian.siswa.index'));
        $res->assertOk();
        $res->assertSee('Belum Dimulai');
        $res->assertSee($this->ujian->judul);
        $res->assertDontSee('Mulai Ujian');
    }

    /** Tombol "Mulai"/"Lanjutkan" TIDAK boleh muncul sebelum jam buka — satu2nya jalan siswa mulai adalah nunggu, bukan tombol yg ujung2nya ditolak 403 stlh diklik. */
    public function test_index_siswa_tak_menampilkan_tombol_mulai_sebelum_jam_buka(): void
    {
        $this->ujianKelas->update(['dibuka_mulai' => now()->addDay()]);
        $siswa = $this->buatSiswa('siswa_tombol_belum', $this->kelas);

        $this->actingAs($siswa)->get(route('ujian.siswa.index'))
            ->assertOk()
            ->assertDontSee(route('ujian.siswa.gate', $this->ujian));
    }

    public function test_halaman_gate_menampilkan_notice_belum_dimulai_alih_alih_form_token(): void
    {
        $this->ujianKelas->update(['dibuka_mulai' => now()->addHours(3)]);
        $siswa = $this->buatSiswa('siswa_gate_belum_mulai', $this->kelas);

        $res = $this->actingAs($siswa)->get(route('ujian.siswa.gate', $this->ujian));
        $res->assertOk();
        $res->assertSee('Ujian Belum Dimulai');
        $res->assertDontSee('Token Masuk');
    }

    public function test_halaman_gate_tampilkan_form_token_normal_setelah_jam_buka_tiba(): void
    {
        $this->ujianKelas->update(['dibuka_mulai' => now()->subMinute()]);
        $siswa = $this->buatSiswa('siswa_gate_sudah_mulai', $this->kelas);

        $res = $this->actingAs($siswa)->get(route('ujian.siswa.gate', $this->ujian));
        $res->assertOk();
        $res->assertSee('Token Masuk');
        $res->assertDontSee('Ujian Belum Dimulai');
    }

    public function test_index_siswa_mengabaikan_attempt_yang_dibatalkan(): void
    {
        // Attempt lama yg sudah di-soft-cancel (mis. reset oleh guru, Fase 5) TIDAK
        // boleh membuat index() menampilkan status "sedang dikerjakan" — siswa harus
        // tetap dianggap belum punya attempt aktif sehingga bisa mulai baru.
        $siswa = $this->buatSiswa('siswa_index_batal', $this->kelas);
        UjianAttempt::create([
            'id_ujian_kelas' => $this->ujianKelas->uuid, 'id_siswa' => $siswa->uuid,
            'urutan_soal' => [], 'mulai_pada' => now(), 'batas_waktu_pada' => now()->addHour(),
            'status' => UjianAttempt::STATUS_DIBATALKAN,
        ]);

        $res = $this->actingAs($siswa)->get(route('ujian.siswa.index'));
        $res->assertOk();
        $res->assertViewHas('attempts', fn ($attempts) => !$attempts->has($this->ujianKelas->uuid));
    }
}
