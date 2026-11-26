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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Perbaikan beban puncak login & masuk token (ujian massal ±1.800 siswa):
 * - cache jadwal/token TIDAK lagi dibuang tiap siswa membuka gerbang/menekan "Mulai" terlalu
 *   cepat, tapi token/jadwal yg baru diubah tetap langsung berlaku (cek DB saat cache menolak);
 * - "Generate ulang token" membuang cache token (dulu token lama dipakai s/d 6 jam);
 * - cache soal tersanitasi & peta tipe ikut dibuang di SETIAP jalur tulis soal (event model);
 * - limiter login hanya menghitung kredensial salah;
 * - respons "server sibuk" membawa Retry-After.
 */
class UjianBebanPuncakTest extends TestCase
{
    use RefreshDatabase;

    private Kelas $kelas;
    private Ujian $ujian;
    private UjianKelas $ujianKelas;
    private UjianSoal $soal;

    protected function setUp(): void
    {
        parent::setUp();
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);

        $guruUser = User::create(['username' => 'guru_puncak', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Puncak', 'nik' => (string) random_int(1000000000, 9999999999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $guruUser->uuid,
            'judul' => 'PTS Beban Puncak', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
        $this->soal = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => '2+2=? <img src=x onerror=alert(1)>', 'poin' => 10, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $this->soal->uuid, 'teks_opsi' => '4', 'is_benar' => true, 'urutan' => 1]);
        UjianSoalOpsi::create(['id_soal' => $this->soal->uuid, 'teks_opsi' => '5', 'is_benar' => false, 'urutan' => 2]);

        $this->ujianKelas = UjianKelas::create([
            'id_ujian' => $this->ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => '1111',
        ]);
    }

    private function buatSiswa(string $username): User
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $this->kelas->uuid, 'nama' => ucfirst($username), 'nis' => (string) random_int(1000, 9999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        return $user;
    }

    private function kunciCacheKelas(): string
    {
        return 'ujian_' . $this->ujian->uuid . '_kelas_all';
    }

    public function test_token_baru_langsung_diterima_walau_cache_masih_memegang_token_lama(): void
    {
        $siswa = $this->buatSiswa('siswa_token_baru');
        $this->ujian->getCachedKelas(); // cache hangat berisi token lama

        // Jalur tulis yg TIDAK memicu event model (mass update) — persis spt generate ulang token.
        UjianKelas::where('id_ujian', $this->ujian->uuid)->update(['token_masuk' => '2222']);

        $this->actingAs($siswa)
            ->post(route('ujian.siswa.start', $this->ujian), ['token' => '2222'])
            ->assertRedirect();
        $this->assertSame(1, UjianAttempt::where('id_siswa', $siswa->uuid)->count());

        // Cache basi sudah terdeteksi & dibuang — token LAMA kini ditolak.
        $siswaLain = $this->buatSiswa('siswa_token_lama');
        $this->actingAs($siswaLain)
            ->post(route('ujian.siswa.start', $this->ujian), ['token' => '1111'])
            ->assertSessionHasErrors('token');
        $this->assertSame(0, UjianAttempt::where('id_siswa', $siswaLain->uuid)->count());
    }

    public function test_admin_generate_ulang_semua_token_membuang_cache_token(): void
    {
        $admin = User::create(['username' => 'admin_puncak', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $this->ujian->getCachedKelas();
        $this->assertTrue(Cache::has($this->kunciCacheKelas()));

        $this->actingAs($admin)->post(route('ujian.token.reset', $this->ujian))->assertRedirect();

        $this->assertFalse(Cache::has($this->kunciCacheKelas()), 'Cache token lama harus dibuang setelah generate ulang.');
        $this->assertNotSame('1111', $this->ujian->getCachedKelas()->first()->token_masuk);
    }

    public function test_membuka_gerbang_sebelum_jam_mulai_tidak_membuang_cache_bersama(): void
    {
        $this->ujianKelas->update(['dibuka_mulai' => now()->addHour()]);
        $siswa = $this->buatSiswa('siswa_menunggu');
        $this->ujian->getCachedKelas();

        $this->actingAs($siswa)->get(route('ujian.siswa.gate', $this->ujian))
            ->assertOk()
            ->assertSee('Ujian Belum Dimulai')
            ->assertSee('data-buka-ms', false)
            ->assertDontSee('Token Masuk');

        $this->assertTrue(Cache::has($this->kunciCacheKelas()), 'Membuka gerbang sebelum jam mulai tak boleh menghapus cache milik semua siswa.');
    }

    public function test_menekan_mulai_terlalu_cepat_tidak_membuang_cache_yg_tidak_basi(): void
    {
        $this->ujianKelas->update(['dibuka_mulai' => now()->addHour()]);
        $siswa = $this->buatSiswa('siswa_terlalu_cepat');
        $this->ujian->getCachedKelas();

        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => '1111'])->assertForbidden();

        $this->assertTrue(Cache::has($this->kunciCacheKelas()));
        $this->assertSame(0, UjianAttempt::where('id_siswa', $siswa->uuid)->count());
    }

    public function test_jadwal_dimajukan_lewat_mass_update_tetap_bisa_dimulai(): void
    {
        $this->ujianKelas->update(['dibuka_mulai' => now()->addHour()]);
        $siswa = $this->buatSiswa('siswa_jadwal_maju');
        $this->ujian->getCachedKelas(); // cache: belum dimulai

        $this->ujian->kelas()->update(['dibuka_mulai' => now()->subMinute()]);

        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => '1111'])->assertRedirect();
        $this->assertSame(1, UjianAttempt::where('id_siswa', $siswa->uuid)->count());
    }

    public function test_menambah_soal_membuang_cache_soal_tampil_dan_peta_tipe(): void
    {
        $this->assertCount(1, $this->ujian->getCachedSoalTampil());
        $this->assertCount(1, $this->ujian->getCachedTipeSoal());

        // Jalur "Sisipkan dari Bank Soal" dulu lupa membuang cache — kini event model yg mengurus.
        $soalBaru = UjianSoal::create(['id_ujian' => $this->ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Jelaskan.', 'poin' => 5, 'urutan' => 2]);

        $this->assertArrayHasKey($soalBaru->uuid, $this->ujian->getCachedSoalTampil());
        $this->assertSame('essay', $this->ujian->getCachedTipeSoal()[$soalBaru->uuid] ?? null);

        // Mengubah opsi juga membuang cache isi soal.
        UjianSoalOpsi::where('id_soal', $this->soal->uuid)->where('urutan', 2)->first()->update(['teks_opsi' => 'Lima']);
        $this->assertContains('Lima', $this->ujian->getCachedSoalTampil()[$this->soal->uuid]['opsi']);
    }

    public function test_halaman_kerjakan_tersanitasi_tanpa_kunci_jawaban(): void
    {
        $siswa = $this->buatSiswa('siswa_kerjakan');
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => '1111'])->assertRedirect();
        $attempt = UjianAttempt::where('id_siswa', $siswa->uuid)->firstOrFail();

        $res = $this->actingAs($siswa)->get(route('ujian.siswa.kerjakan', [$this->ujian, $attempt]));
        $res->assertOk();
        $res->assertDontSee('onerror', false);
        $res->assertDontSee('is_benar', false);

        // Panggilan kedua memakai cache yg sama — hasil identik.
        $res2 = $this->actingAs($siswa)->get(route('ujian.siswa.kerjakan', [$this->ujian, $attempt]));
        $this->assertSame($res->viewData('soalTampil')->all(), $res2->viewData('soalTampil')->all());
    }

    public function test_sinkron_batch_memakai_peta_tipe_dan_abaikan_soal_asing(): void
    {
        $siswa = $this->buatSiswa('siswa_sinkron');
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => '1111']);
        $attempt = UjianAttempt::where('id_siswa', $siswa->uuid)->firstOrFail();
        $opsiBenar = UjianSoalOpsi::where('id_soal', $this->soal->uuid)->where('is_benar', true)->value('uuid');

        $this->actingAs($siswa)->postJson(route('ujian.siswa.sinkronBatch', [$this->ujian, $attempt]), [
            'jawaban' => [
                ['id_soal' => $this->soal->uuid, 'id_opsi_dipilih' => $opsiBenar],
                ['id_soal' => (string) \Illuminate\Support\Str::uuid(), 'id_opsi_dipilih' => $opsiBenar],
            ],
        ])->assertOk()->assertJson(['ok' => true, 'count' => 1]);

        $this->assertDatabaseHas('ujian_jawaban', ['id_attempt' => $attempt->uuid, 'id_soal' => $this->soal->uuid, 'id_opsi_dipilih' => $opsiBenar]);
    }

    public function test_login_sukses_berulang_tidak_menghabiskan_jatah_percobaan(): void
    {
        User::create(['username' => 'rajin_login', 'password' => 'rahasia123', 'access' => 'admin']);

        // Dulu SETIAP POST /login (termasuk yg sukses) dihitung — login ke-6 dlm semenit diblok.
        for ($i = 1; $i <= 6; $i++) {
            $this->post('/login', ['credential' => 'rajin_login', 'password' => 'rahasia123'])->assertRedirect(route('dashboard'));
            $this->assertAuthenticated();
            $this->post(route('logout'));
            $this->assertGuest();
        }
    }

    public function test_session_profil_siswa_ringkas_tanpa_data_wajah(): void
    {
        // Dulu seluruh baris siswa (termasuk face_descriptor ±90 KB) ikut disimpan di session
        // & ditulis ulang ke disk di SETIAP request siswa selama ujian.
        $siswa = $this->buatSiswa('siswa_sesi_ringkas');

        $this->post('/login', ['credential' => 'siswa_sesi_ringkas', 'password' => 'rahasia123'])->assertRedirect();

        $profil = session('profil_siswa_' . $siswa->uuid);
        $this->assertInstanceOf(Siswa::class, $profil);
        $this->assertSame($this->kelas->uuid, $profil->id_kelas);
        $this->assertArrayNotHasKey('face_descriptor', $profil->getAttributes());
        $this->assertArrayNotHasKey('face_descriptor_if', $profil->getAttributes());
        // ±2,3 KB (sebagian besar overhead serialisasi Eloquent) vs ±184 KB sebelumnya.
        $this->assertLessThan(4096, strlen(serialize($profil)), 'Profil siswa di session harus tetap kecil.');

        // Profil ringkas tetap cukup utk alur token.
        $this->post(route('ujian.siswa.start', $this->ujian), ['token' => '1111'])->assertRedirect();
        $this->assertSame(1, UjianAttempt::where('id_siswa', $siswa->uuid)->count());
    }

    public function test_kumpulkan_ulang_setelah_sudah_terkumpul_dianggap_sukses_bukan_error(): void
    {
        // Skenario 408: submit pertama SUDAH diproses server, tapi respons hilang di jalan —
        // siswa menekan "Kumpulkan" lagi. Dulu dapat 422 → tampil "Pengumpulan Tertunda" terus.
        $siswa = $this->buatSiswa('siswa_kumpul_ulang');
        $this->actingAs($siswa)->post(route('ujian.siswa.start', $this->ujian), ['token' => '1111']);
        $attempt = UjianAttempt::where('id_siswa', $siswa->uuid)->firstOrFail();
        $hasil = route('ujian.siswa.hasil', [$this->ujian, $attempt]);

        $this->actingAs($siswa)->postJson(route('ujian.siswa.submit', [$this->ujian, $attempt]))
            ->assertOk()->assertJson(['redirect' => $hasil]);
        // 'submitted' (Mode Ujian aktif, penilaian ditunda) atau 'dinilai' (langsung dinilai).
        $this->assertContains($attempt->fresh()->status, [UjianAttempt::STATUS_SUBMITTED, UjianAttempt::STATUS_DINILAI]);

        $this->actingAs($siswa)->postJson(route('ujian.siswa.submit', [$this->ujian, $attempt]))
            ->assertOk()->assertJson(['redirect' => $hasil]);
        $this->actingAs($siswa)->post(route('ujian.siswa.submit', [$this->ujian, $attempt]))
            ->assertRedirect($hasil);

        // Tetap tak boleh dipakai siswa LAIN utk attempt yg bukan miliknya.
        $siswaLain = $this->buatSiswa('siswa_bukan_pemilik');
        $this->actingAs($siswaLain)->postJson(route('ujian.siswa.submit', [$this->ujian, $attempt]))->assertForbidden();
    }

    public function test_respons_server_sibuk_membawa_retry_after(): void
    {
        Route::middleware('web')->get('/_uji/db-sibuk', function () {
            throw new QueryException('mysql', 'select 1', [], new \PDOException('SQLSTATE[HY000] [2002] Operation not permitted'));
        });

        $res = $this->getJson('/_uji/db-sibuk');
        $res->assertStatus(503);
        $retryAfter = (int) $res->headers->get('Retry-After');
        $this->assertGreaterThanOrEqual(3, $retryAfter);
        $this->assertLessThanOrEqual(8, $retryAfter);
    }
}
