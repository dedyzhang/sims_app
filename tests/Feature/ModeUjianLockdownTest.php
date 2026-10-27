<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Models\User;
use App\Support\ModeUjian;
use App\Support\PollingWidget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ModeUjianLockdownTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $guruUser;
    private User $siswaUser;
    private Ujian $ujian;
    private UjianAttempt $attempt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::create([
            'username' => 'admin_ujian',
            'password' => Hash::make('password123'),
            'access'   => 'admin',
        ]);

        $pelajaran = Pelajaran::create(['nama' => 'Bahasa Indonesia', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);

        $this->guruUser = User::create([
            'username' => 'guru_mode',
            'password' => Hash::make('password123'),
            'access'   => 'guru',
        ]);
        $guru = Guru::create([
            'id_login'        => $this->guruUser->uuid,
            'nama'            => 'Guru Mode Ujian',
            'nik'             => '1234567890',
            'jk'              => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);
        Ngajar::create([
            'id_guru'      => $guru->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'id_kelas'     => $kelas->uuid,
        ]);

        $this->siswaUser = User::create([
            'username' => 'siswa_mode',
            'password' => Hash::make('password123'),
            'access'   => 'siswa',
        ]);
        Siswa::create([
            'id_login'        => $this->siswaUser->uuid,
            'id_kelas'        => $kelas->uuid,
            'nama'            => 'Siswa Mode Ujian',
            'nis'             => '7788',
            'jk'              => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid,
            'created_by'   => $this->guruUser->uuid,
            'judul'        => 'Ujian Tengah Semester',
            'jenis'        => 'pts',
            'target_nilai' => 'pts',
            'durasi_menit' => 60,
            'status'       => 'published',
        ]);

        $soal = UjianSoal::create([
            'id_ujian'  => $this->ujian->uuid,
            'tipe'      => 'mcq',
            'teks_soal' => 'Apa itu sinonim?',
            'poin'      => 10,
            'urutan'    => 1,
        ]);
        UjianSoalOpsi::create([
            'id_soal'   => $soal->uuid,
            'teks_opsi' => 'Persamaan kata',
            'is_benar'  => true,
            'urutan'    => 1,
        ]);
        UjianSoalOpsi::create([
            'id_soal'   => $soal->uuid,
            'teks_opsi' => 'Lawan kata',
            'is_benar'  => false,
            'urutan'    => 2,
        ]);

        UjianKelas::create([
            'id_ujian'    => $this->ujian->uuid,
            'id_kelas'    => $kelas->uuid,
            'token_masuk' => 'TOKENMODE1',
        ]);
    }

    protected function tearDown(): void
    {
        ModeUjian::nonaktifkan();
        \Illuminate\Support\Facades\Cache::flush();
        parent::tearDown();
    }

    public function test_toggle_mode_ujian(): void
    {
        $this->assertFalse(ModeUjian::aktif());

        ModeUjian::aktifkan();
        $this->assertTrue(ModeUjian::aktif());

        ModeUjian::nonaktifkan();
        $this->assertFalse(ModeUjian::aktif());

        $toggled = ModeUjian::toggle();
        $this->assertTrue($toggled);
        $this->assertTrue(ModeUjian::aktif());
    }

    public function test_admin_bisa_toggle_mode_ujian_via_http(): void
    {
        $this->actingAs($this->adminUser)
            ->post(route('setting.mode-ujian.toggle'))
            ->assertRedirect();

        $this->assertTrue(ModeUjian::aktif());

        $this->actingAs($this->adminUser)
            ->post(route('setting.mode-ujian.toggle'))
            ->assertRedirect();

        $this->assertFalse(ModeUjian::aktif());
    }

    public function test_seluruh_widget_polling_otomatis_mati_saat_mode_ujian_aktif(): void
    {
        ModeUjian::aktifkan();

        $daftar = PollingWidget::semua();
        $this->assertNotEmpty($daftar);

        foreach (array_keys($daftar) as $kode) {
            $this->assertFalse(PollingWidget::aktif($kode), "Widget $kode seharusnya nonaktif saat Mode Ujian aktif.");
        }
    }

    public function test_siswa_dikunci_ke_ruang_ujian_saat_mode_ujian_aktif(): void
    {
        ModeUjian::aktifkan();

        // Mengakses dashboard umum dialihkan ke ujian saya
        $this->actingAs($this->siswaUser)
            ->get(route('dashboard'))
            ->assertRedirect(route('ujian.siswa.index'));

        // Mengakses JSON endpoint non-ujian mendapat 403 JSON
        $this->actingAs($this->siswaUser)
            ->getJson(route('dashboard.ticker-stats'))
            ->assertStatus(403)
            ->assertJson([
                'redirect' => route('ujian.siswa.index'),
            ]);
    }

    public function test_siswa_tetap_bisa_akses_halaman_ujian_dan_logout(): void
    {
        ModeUjian::aktifkan();

        // Halaman ruang ujian saya bisa dibuka
        $this->actingAs($this->siswaUser)
            ->get(route('ujian.siswa.index'))
            ->assertOk();

        // Mulai ujian bisa dilakukan
        $this->actingAs($this->siswaUser)
            ->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENMODE1'])
            ->assertRedirect();

        $attempt = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail();

        // Halaman kerjakan bisa dibuka
        $this->actingAs($this->siswaUser)
            ->get(route('ujian.siswa.kerjakan', [$this->ujian, $attempt]))
            ->assertOk();

        // Logout tetap diizinkan
        $this->actingAs($this->siswaUser)
            ->post(route('logout'))
            ->assertRedirect();
    }

    public function test_guru_dan_admin_tidak_terkunci_saat_mode_ujian_aktif(): void
    {
        ModeUjian::aktifkan();

        // Admin tetap bisa akses setting
        $this->actingAs($this->adminUser)
            ->get(route('setting.index'))
            ->assertOk();

        // Guru tetap bisa akses daftar ujian
        $this->actingAs($this->guruUser)
            ->get(route('ujian.index'))
            ->assertOk();
    }

    public function test_tamu_belum_login_membuka_ujian_dialihkan_ke_login(): void
    {
        // Bukan 403, melainkan redirect ke login
        $this->get(route('ujian.siswa.index'))
            ->assertRedirect(route('login'));
    }

    public function test_fast_submit_menunda_kalkulasi_grading_saat_mode_ujian_aktif(): void
    {
        ModeUjian::aktifkan();

        $this->actingAs($this->siswaUser)
            ->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENMODE1'])
            ->assertRedirect();

        $attempt = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail();

        // Siswa submit ujian
        $this->actingAs($this->siswaUser)
            ->post(route('ujian.siswa.submit', [$this->ujian, $attempt]))
            ->assertRedirect(route('ujian.siswa.hasil', [$this->ujian, $attempt]));

        $attempt->refresh();
        // Status submitted, belum dinilai (fast-submit)
        $this->assertSame(UjianAttempt::STATUS_SUBMITTED, $attempt->status);
        $this->assertNull($attempt->total_skor);

        // Ketika Guru membuka halaman hasil ujian, kalkulasi massal otomatis berjalan
        $this->actingAs($this->guruUser)
            ->get(route('ujian.hasil.index', $this->ujian))
            ->assertOk();

        $attempt->refresh();
        $this->assertSame(UjianAttempt::STATUS_DINILAI, $attempt->status);
        $this->assertNotNull($attempt->total_skor);
    }

    public function test_arena_latihan_publik_503_saat_mode_ujian_aktif(): void
    {
        ModeUjian::aktifkan();

        $this->get(route('latihan.publik.show', 'ABCD1234'))
            ->assertStatus(503);
    }

    public function test_siswa_reset_password_wajib_ke_ganti_password_dulu_baru_ke_ujian(): void
    {
        ModeUjian::aktifkan();

        // Siswa yang baru direset password-nya (must_change_password = true, username sudah dikustomisasi)
        $this->siswaUser->update([
            'must_change_password' => true,
            'username_customized'  => true,
        ]);

        // 1. Login sebagai siswa saat Mode Ujian aktif
        $response = $this->post(route('login.post'), [
            'credential' => $this->siswaUser->username,
            'password'   => 'password123',
        ]);

        // Tetap wajib diarahkan ke ganti.password
        $response->assertRedirect(route('ganti.password'));

        // 2. Halaman ganti password bisa dibuka tanpa mental bolak-balik (tidak ERR_TOO_MANY_REDIRECTS)
        $this->actingAs($this->siswaUser)
            ->get(route('ganti.password'))
            ->assertOk();

        // 3. Jika mencoba langsung menerobos ke ruang ujian sebelum ganti password, tetap ditahan ke ganti.password
        $this->actingAs($this->siswaUser)
            ->get(route('ujian.siswa.index'))
            ->assertRedirect(route('ganti.password'));

        // 4. Siswa mengganti password-nya
        $gantiResponse = $this->actingAs($this->siswaUser)
            ->post('/ganti-password', [
                'new_password'              => 'passwordBaru123',
                'new_password_confirmation' => 'passwordBaru123',
            ]);

        // Setelah ganti password berhasil di Mode Ujian, langsung diarahkan ke ruang ujian
        $gantiResponse->assertRedirect(route('ujian.siswa.index'));

        $this->siswaUser->refresh();
        $this->assertFalse((bool) $this->siswaUser->must_change_password);

        // 5. Sekarang siswa bisa masuk ke ruang ujian dengan lancar
        $this->actingAs($this->siswaUser)
            ->get(route('ujian.siswa.index'))
            ->assertOk();
    }

    public function test_hanya_admin_yang_bisa_tutup_dan_buka_kembali_ujian(): void
    {
        // Guru tidak bisa menutup ujian (403 Forbidden)
        $this->actingAs($this->guruUser)
            ->post(route('ujian.close', $this->ujian))
            ->assertForbidden();

        // Guru tidak melihat tombol Tutup pada daftar ujian dan detail ujian
        $htmlListGuru = $this->actingAs($this->guruUser)->get(route('ujian.index'))->getContent();
        $this->assertStringNotContainsString(route('ujian.close', $this->ujian), $htmlListGuru);

        $htmlShowGuru = $this->actingAs($this->guruUser)->get(route('ujian.show', $this->ujian))->getContent();
        $this->assertStringNotContainsString(route('ujian.close', $this->ujian), $htmlShowGuru);

        // Admin melihat tombol Tutup dan berhasil menutup ujian
        $htmlListAdmin = $this->actingAs($this->adminUser)->get(route('ujian.index'))->getContent();
        $this->assertStringContainsString(route('ujian.close', $this->ujian), $htmlListAdmin);

        $htmlShowAdmin = $this->actingAs($this->adminUser)->get(route('ujian.show', $this->ujian))->getContent();
        $this->assertStringContainsString(route('ujian.close', $this->ujian), $htmlShowAdmin);

        $this->actingAs($this->adminUser)
            ->post(route('ujian.close', $this->ujian))
            ->assertRedirect();

        $this->ujian->refresh();
        $this->assertSame('closed', $this->ujian->status);

        // Guru tidak bisa membuka kembali ujian yang sudah ditutup
        $this->actingAs($this->guruUser)
            ->post(route('ujian.reopen', $this->ujian))
            ->assertForbidden();

        // Admin berhasil membuka kembali ujian menjadi draf
        $this->actingAs($this->adminUser)
            ->post(route('ujian.reopen', $this->ujian))
            ->assertRedirect();

        $this->ujian->refresh();
        $this->assertSame('draft', $this->ujian->status);
    }

    public function test_cache_settings_dan_langganan_aktif(): void
    {
        \Illuminate\Support\Facades\Cache::flush();

        Setting::set('nama_sekolah', 'Sekolah Tes');
        $this->assertSame('Sekolah Tes', Setting::get('nama_sekolah'));
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has('global_settings_rows'));

        \App\Models\Langganan::create([
            'paket' => 'pro',
            'durasi_bulan' => 12,
            'mulai_pada' => now(),
            'berakhir_pada' => now()->addYear(),
            'status' => 'aktif',
        ]);
        $l1 = \App\Models\Langganan::current();
        $this->assertNotNull($l1);
        $this->assertTrue(\Illuminate\Support\Facades\Cache::has('langganan_current_record'));
    }

    public function test_simpan_jawaban_berhasil_tersimpan_dengan_aman(): void
    {
        $this->actingAs($this->siswaUser)
            ->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENMODE1'])
            ->assertRedirect();

        $attempt = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail();
        $soal = $this->ujian->soal()->firstOrFail();
        $opsi = $soal->opsi()->firstOrFail();

        $res = $this->actingAs($this->siswaUser)->postJson(
            route('ujian.siswa.jawab', [$this->ujian, $attempt]),
            [
                'id_soal' => $soal->uuid,
                'id_opsi_dipilih' => $opsi->uuid,
            ]
        );

        $res->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('ujian_jawaban', [
            'id_attempt' => $attempt->uuid,
            'id_soal' => $soal->uuid,
            'id_opsi_dipilih' => $opsi->uuid,
        ]);
    }

    public function test_sinkron_batch_menyimpan_banyak_jawaban_dalam_satu_panggilan(): void
    {
        // Siapkan soal kedua bertipe essay
        $soalEsai = UjianSoal::create([
            'id_ujian'  => $this->ujian->uuid,
            'tipe'      => 'essay',
            'teks_soal' => 'Jelaskan pengertian pantun!',
            'poin'      => 20,
            'urutan'    => 2,
        ]);

        $this->ujian->clearSoalCache();

        $this->actingAs($this->siswaUser)
            ->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENMODE1'])
            ->assertRedirect();

        $attempt = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail();
        $soalMcq = $this->ujian->soal()->where('tipe', 'mcq')->firstOrFail();
        $opsi = $soalMcq->opsi()->firstOrFail();

        $payload = [
            'jawaban' => [
                [
                    'id_soal' => $soalMcq->uuid,
                    'id_opsi_dipilih' => $opsi->uuid,
                ],
                [
                    'id_soal' => $soalEsai->uuid,
                    'jawaban_esai' => 'Pantun adalah puisi lama Indonesia yang bersajak a-b-a-b.',
                ],
            ],
        ];

        $res = $this->actingAs($this->siswaUser)->postJson(
            route('ujian.siswa.sinkronBatch', [$this->ujian, $attempt]),
            $payload
        );

        $res->assertOk()->assertJson(['ok' => true, 'count' => 2]);

        $this->assertDatabaseHas('ujian_jawaban', [
            'id_attempt' => $attempt->uuid,
            'id_soal' => $soalMcq->uuid,
            'id_opsi_dipilih' => $opsi->uuid,
        ]);

        $this->assertDatabaseHas('ujian_jawaban', [
            'id_attempt' => $attempt->uuid,
            'id_soal' => $soalEsai->uuid,
            'jawaban_esai' => 'Pantun adalah puisi lama Indonesia yang bersajak a-b-a-b.',
        ]);

        // Verifikasi update via upsert: siswa merevisi jawaban esai
        $payloadUpdate = [
            'jawaban' => [
                [
                    'id_soal' => $soalEsai->uuid,
                    'jawaban_esai' => 'Revisi: Pantun adalah karya sastra 4 baris.',
                ],
            ],
        ];

        $resUpdate = $this->actingAs($this->siswaUser)->postJson(
            route('ujian.siswa.sinkronBatch', [$this->ujian, $attempt]),
            $payloadUpdate
        );

        $resUpdate->assertOk()->assertJson(['ok' => true, 'count' => 1]);

        $this->assertDatabaseHas('ujian_jawaban', [
            'id_attempt' => $attempt->uuid,
            'id_soal' => $soalEsai->uuid,
            'jawaban_esai' => 'Revisi: Pantun adalah karya sastra 4 baris.',
        ]);

        // Pastikan tidak ada baris ganda (tetap 2 baris total untuk attempt ini)
        $this->assertSame(2, \App\Models\UjianJawaban::where('id_attempt', $attempt->uuid)->count());
    }

    public function test_sinkron_batch_menolak_siswa_lain_dan_attempt_terkunci(): void
    {
        $this->actingAs($this->siswaUser)
            ->post(route('ujian.siswa.start', $this->ujian), ['token' => 'TOKENMODE1'])
            ->assertRedirect();

        $attempt = UjianAttempt::where('id_siswa', $this->siswaUser->uuid)->firstOrFail();
        $soal = $this->ujian->soal()->firstOrFail();

        // Siswa lain mencoba kirim ke attempt ini
        $siswaLain = User::create([
            'username' => 'siswa_lain',
            'password' => Hash::make('password123'),
            'access'   => 'siswa',
        ]);
        Siswa::create([
            'id_login'        => $siswaLain->uuid,
            'id_kelas'        => $this->siswaUser->siswa->id_kelas,
            'nama'            => 'Siswa Lain',
            'nis'             => '9999',
            'jk'              => 'P',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $this->actingAs($siswaLain)->postJson(
            route('ujian.siswa.sinkronBatch', [$this->ujian, $attempt]),
            ['jawaban' => [['id_soal' => $soal->uuid]]]
        )->assertForbidden();

        // Kunci attempt, lalu coba simpan
        $attempt->update(['dikunci' => true]);

        $this->actingAs($this->siswaUser)->postJson(
            route('ujian.siswa.sinkronBatch', [$this->ujian, $attempt]),
            ['jawaban' => [['id_soal' => $soal->uuid]]]
        )->assertForbidden();
    }

    public function test_profil_siswa_dimemo_di_session_saat_login_dan_akses(): void
    {
        // Test login pre-caching
        $res = $this->post(route('login.post'), [
            'credential' => 'siswa_mode',
            'password'   => 'password123',
        ]);
        $res->assertRedirect();

        $sessionKey = 'profil_siswa_' . $this->siswaUser->uuid;
        $this->assertTrue(session()->has($sessionKey));
        $this->assertSame('Siswa Mode Ujian', session($sessionKey)->nama);

        // Akses halaman /ujian/saya dengan session yang sudah memiliki profil
        // Memastikan tidak throw error dan berhasil merender halaman
        $this->actingAs($this->siswaUser)
            ->withSession([$sessionKey => session($sessionKey)])
            ->get(route('ujian.siswa.index'))
            ->assertOk();
    }
}
