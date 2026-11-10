<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 2+3 lanjutan modul Ujian: buka-kembali ujian ditutup (closed->draft, soal bisa
 * diedit lagi lewat guard draft yang sudah ada), dan guru pengampu per-kelas (auto kalau
 * cuma 1 guru cocok, pilih manual kalau ambigu, membatasi akses Nilai Esai per kelas).
 */
class UjianReopenDanPengampuTest extends TestCase
{
    use RefreshDatabase;

    private Pelajaran $pelajaran;
    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
    }

    private function buatGuru(string $username): array
    {
        $user = User::create(['username' => $username, 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $user->uuid, 'nama' => ucfirst($username), 'nik' => (string) random_int(1000000000, 9999999999), 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        return [$user, $guru];
    }

    private function admin(): User
    {
        return User::create(['username' => 'admin_reopen', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
    }

    private function buatUjian(User $pembuat): Ujian
    {
        return Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $pembuat->uuid,
            'judul' => 'PTS Ganjil Matematika', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);
    }

    // ===== Reopen (closed -> draft) =====

    public function test_ujian_ditutup_bisa_dibuka_kembali_jadi_draft(): void
    {
        $admin = $this->admin();
        $ujian = $this->buatUjian($admin);
        $ujian->update(['status' => 'closed']);

        $this->actingAs($admin)->post(route('ujian.reopen', $ujian))->assertRedirect();

        $this->assertSame('draft', $ujian->fresh()->status);
    }

    public function test_reopen_ditolak_kalau_ujian_belum_ditutup(): void
    {
        $admin = $this->admin();
        $ujian = $this->buatUjian($admin);
        $ujian->update(['status' => 'published']);

        $this->actingAs($admin)->post(route('ujian.reopen', $ujian))->assertStatus(422);
        $this->assertSame('published', $ujian->fresh()->status);
    }

    public function test_soal_bisa_diedit_lagi_setelah_ujian_dibuka_kembali(): void
    {
        $admin = $this->admin();
        $ujian = $this->buatUjian($admin);
        $soal = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'mcq', 'teks_soal' => 'Soal lama', 'poin' => 1, 'urutan' => 1]);
        $ujian->update(['status' => 'closed']);

        $payload = [
            'tipe' => 'mcq', 'teks_soal' => 'Revisi', 'poin' => 1,
            'opsi' => [['teks' => 'A', 'benar' => true], ['teks' => 'B', 'benar' => false]],
        ];

        // Sebelum dibuka kembali: ditolak (guard draft-only yang sudah ada).
        $this->actingAs($admin)->post(route('ujian.soal.update', [$ujian, $soal]), $payload)
            ->assertStatus(422);

        $this->actingAs($admin)->post(route('ujian.reopen', $ujian));

        // Setelah dibuka kembali (draft): boleh.
        $this->actingAs($admin)->post(route('ujian.soal.update', [$ujian, $soal]), $payload)
            ->assertRedirect();
        $this->assertSame('Revisi', $soal->fresh()->teks_soal);
    }

    // ===== Guru pengampu =====

    public function test_satu_guru_cocok_otomatis_jadi_pengampu_saat_kelas_ditetapkan(): void
    {
        $admin = $this->admin();
        [, $guru] = $this->buatGuru('guru_pengampu_tunggal');
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $ujian = $this->buatUjian($admin);

        $this->actingAs($admin)->post(route('ujian.kelas.sync', $ujian), ['id_kelas' => [$this->kelas->uuid]])->assertRedirect();

        $uk = UjianKelas::where('id_ujian', $ujian->uuid)->where('id_kelas', $this->kelas->uuid)->first();
        $this->assertSame($guru->uuid, $uk->id_guru_pengampu);
    }

    public function test_dua_guru_cocok_pengampu_kosong_sampai_dipilih_manual(): void
    {
        $admin = $this->admin();
        [, $guruA] = $this->buatGuru('guru_pengampu_a');
        [, $guruB] = $this->buatGuru('guru_pengampu_b');
        Ngajar::create(['id_guru' => $guruA->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        Ngajar::create(['id_guru' => $guruB->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        $ujian = $this->buatUjian($admin);

        $this->actingAs($admin)->post(route('ujian.kelas.sync', $ujian), ['id_kelas' => [$this->kelas->uuid]]);

        $uk = UjianKelas::where('id_ujian', $ujian->uuid)->where('id_kelas', $this->kelas->uuid)->first();
        $this->assertNull($uk->id_guru_pengampu);

        $this->actingAs($admin)->post(route('ujian.kelas.pengampu', [$ujian, $uk]), ['id_guru_pengampu' => $guruB->uuid])
            ->assertRedirect();
        $this->assertSame($guruB->uuid, $uk->fresh()->id_guru_pengampu);
    }

    public function test_set_pengampu_bisa_memilih_guru_di_luar_data_ngajar(): void
    {
        $admin = $this->admin();
        [, $guruLuar] = $this->buatGuru('guru_luar_pengampu'); // sengaja TANPA Ngajar kelas ini
        $ujian = $this->buatUjian($admin);
        $uk = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => UjianKelas::generateToken()]);

        $this->actingAs($admin)->post(route('ujian.kelas.pengampu', [$ujian, $uk]), ['id_guru_pengampu' => $guruLuar->uuid])
            ->assertRedirect();
        $this->assertSame($guruLuar->uuid, $uk->fresh()->id_guru_pengampu);
        $this->assertTrue($uk->fresh()->pengampuLuarNgajar());
        $this->assertFalse($uk->fresh()->pengampuSesuaiNgajar());
    }

    public function test_set_pengampu_menolak_uuid_guru_yang_tidak_ada_di_database(): void
    {
        $admin = $this->admin();
        $ujian = $this->buatUjian($admin);
        $uk = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => UjianKelas::generateToken()]);

        $this->actingAs($admin)->post(route('ujian.kelas.pengampu', [$ujian, $uk]), ['id_guru_pengampu' => '00000000-0000-0000-0000-000000000000'])
            ->assertStatus(302)
            ->assertSessionHasErrors('id_guru_pengampu');
        $this->assertNull($uk->fresh()->id_guru_pengampu);
    }

    public function test_batch_pengampu_simpan_beberapa_kelas_sekaligus_dgn_satu_request(): void
    {
        $admin = $this->admin();
        $kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        [, $guruA] = $this->buatGuru('guru_batch_a');
        [, $guruB] = $this->buatGuru('guru_batch_b');
        Ngajar::create(['id_guru' => $guruA->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        Ngajar::create(['id_guru' => $guruB->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $kelasB->uuid]);

        $ujian = $this->buatUjian($admin);
        $ukA = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => UjianKelas::generateToken()]);
        $ukB = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $kelasB->uuid, 'token_masuk' => UjianKelas::generateToken()]);

        $this->actingAs($admin)->post(route('ujian.kelas.pengampu.batch', $ujian), [
            'pengampu' => [$ukA->uuid => $guruA->uuid, $ukB->uuid => $guruB->uuid],
        ])->assertRedirect();

        $this->assertSame($guruA->uuid, $ukA->fresh()->id_guru_pengampu);
        $this->assertSame($guruB->uuid, $ukB->fresh()->id_guru_pengampu);
    }

    public function test_batch_pengampu_melewati_kelas_yang_dibiarkan_kosong(): void
    {
        $admin = $this->admin();
        [, $guruA] = $this->buatGuru('guru_batch_kosong_a');
        Ngajar::create(['id_guru' => $guruA->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $ujian = $this->buatUjian($admin);
        $uk = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => UjianKelas::generateToken(), 'id_guru_pengampu' => $guruA->uuid]);

        $this->actingAs($admin)->post(route('ujian.kelas.pengampu.batch', $ujian), [
            'pengampu' => [$uk->uuid => ''],
        ])->assertRedirect();

        $this->assertSame($guruA->uuid, $uk->fresh()->id_guru_pengampu);
    }

    public function test_batch_pengampu_bisa_simpan_guru_di_luar_data_ngajar(): void
    {
        $admin = $this->admin();
        $kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        [, $guruA] = $this->buatGuru('guru_batch_valid');
        [, $guruLuar] = $this->buatGuru('guru_batch_luar'); // TANPA Ngajar kelasB

        Ngajar::create(['id_guru' => $guruA->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $ujian = $this->buatUjian($admin);
        $ukA = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => UjianKelas::generateToken()]);
        $ukB = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $kelasB->uuid, 'token_masuk' => UjianKelas::generateToken()]);

        $this->actingAs($admin)->post(route('ujian.kelas.pengampu.batch', $ujian), [
            'pengampu' => [$ukA->uuid => $guruA->uuid, $ukB->uuid => $guruLuar->uuid],
        ])->assertRedirect();

        $this->assertSame($guruA->uuid, $ukA->fresh()->id_guru_pengampu);
        $this->assertSame($guruLuar->uuid, $ukB->fresh()->id_guru_pengampu);
        $this->assertTrue($ukB->fresh()->pengampuLuarNgajar());
    }

    public function test_guru_bukan_pengampu_tak_bisa_nilai_esai_kelas_itu(): void
    {
        $admin = $this->admin();
        [$userA, $guruA] = $this->buatGuru('guru_esai_a');
        [$userB, $guruB] = $this->buatGuru('guru_esai_b');
        Ngajar::create(['id_guru' => $guruA->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);
        Ngajar::create(['id_guru' => $guruB->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $ujian = $this->buatUjian($admin);
        $uk = UjianKelas::create([
            'id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid,
            'token_masuk' => UjianKelas::generateToken(), 'id_guru_pengampu' => $guruA->uuid,
        ]);
        $soal = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Jelaskan', 'poin' => 10, 'urutan' => 1]);
        $siswaUser = User::create(['username' => 'siswa_esai_pengampu', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $uk->uuid, 'id_siswa' => $siswaUser->uuid,
            'urutan_soal' => [$soal->uuid], 'status' => UjianAttempt::STATUS_SUBMITTED,
            'butuh_penilaian_manual' => true, 'mulai_pada' => now(), 'selesai_pada' => now(),
        ]);
        UjianJawaban::create(['id_attempt' => $attempt->uuid, 'id_soal' => $soal->uuid, 'jawaban_esai' => 'Jawaban siswa']);

        // guruB mengajar mapel+kelas yg sama tapi BUKAN pengampu ujian_kelas ini -> ditolak.
        $this->actingAs($userB)->get(route('ujian.grading.show', [$ujian, $attempt]))->assertForbidden();

        // guruA adalah pengampu -> boleh.
        $this->actingAs($userA)->get(route('ujian.grading.show', [$ujian, $attempt]))->assertOk();

        // Index grading guruB juga tak menampilkan attempt kelas ini.
        $this->actingAs($userB)->get(route('ujian.grading.index', $ujian))
            ->assertOk()
            ->assertViewHas('attempts', fn ($attempts) => $attempts->isEmpty());
    }

    public function test_admin_tetap_bisa_nilai_esai_semua_kelas_walau_ada_pengampu(): void
    {
        $admin = $this->admin();
        [, $guruA] = $this->buatGuru('guru_esai_admin_a');
        Ngajar::create(['id_guru' => $guruA->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $ujian = $this->buatUjian($admin);
        $uk = UjianKelas::create([
            'id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid,
            'token_masuk' => UjianKelas::generateToken(), 'id_guru_pengampu' => $guruA->uuid,
        ]);
        $soal = UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Jelaskan', 'poin' => 10, 'urutan' => 1]);
        $siswaUser = User::create(['username' => 'siswa_esai_admin', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $uk->uuid, 'id_siswa' => $siswaUser->uuid,
            'urutan_soal' => [$soal->uuid], 'status' => UjianAttempt::STATUS_SUBMITTED,
            'butuh_penilaian_manual' => true, 'mulai_pada' => now(), 'selesai_pada' => now(),
        ]);
        UjianJawaban::create(['id_attempt' => $attempt->uuid, 'id_soal' => $soal->uuid, 'jawaban_esai' => 'Jawaban siswa']);

        $this->actingAs($admin)->get(route('ujian.grading.show', [$ujian, $attempt]))->assertOk();
        $this->actingAs($admin)->get(route('ujian.grading.index', $ujian))
            ->assertOk()
            ->assertViewHas('attempts', fn ($attempts) => $attempts->count() === 1);
    }

    public function test_pengampu_di_luar_data_ngajar_tidak_bisa_transfer_nilai(): void
    {
        $admin = $this->admin();
        [$userLuar, $guruLuar] = $this->buatGuru('guru_luar_transfer');
        [$userResmi, $guruResmi] = $this->buatGuru('guru_resmi_transfer');

        // Guru resmi terdaftar di Ngajar
        Ngajar::create([
            'id_guru' => $guruResmi->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas' => $this->kelas->uuid,
        ]);

        $ujian = $this->buatUjian($admin);
        // Tapi pengampu ujian diisi guru luar (pengganti)
        $uk = UjianKelas::create([
            'id_ujian' => $ujian->uuid,
            'id_kelas' => $this->kelas->uuid,
            'token_masuk' => UjianKelas::generateToken(),
            'id_guru_pengampu' => $guruLuar->uuid,
        ]);

        $siswaUser = User::create(['username' => 'siswa_transfer_test', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $attempt = UjianAttempt::create([
            'id_ujian_kelas' => $uk->uuid,
            'id_siswa' => $siswaUser->uuid,
            'urutan_soal' => [],
            'status' => UjianAttempt::STATUS_DINILAI,
            'total_skor' => 85,
            'mulai_pada' => now(),
            'selesai_pada' => now(),
        ]);

        // 1. Guru luar buka halaman hasil: bisa lihat, tapi transfer terkunci
        $resHasil = $this->actingAs($userLuar)->get(route('ujian.hasil.index', $ujian));
        $resHasil->assertOk();
        $resHasil->assertSee('Transfer Nilai Terkunci (Di Luar Data Ngajar)');

        // 2. Guru luar coba transferSemua: ditolak 403 Forbidden
        $this->actingAs($userLuar)->post(route('ujian.hasil.transferSemua', $ujian))
            ->assertForbidden();

        // 3. Guru luar coba transferUlang: ditolak 403 Forbidden
        $this->actingAs($userLuar)->post(route('ujian.hasil.transferUlang', [$ujian, $attempt]))
            ->assertForbidden();

        // 4. Guru resmi buka halaman hasil: bisa transfer
        $resResmi = $this->actingAs($userResmi)->get(route('ujian.hasil.index', $ujian));
        $resResmi->assertOk();
        $resResmi->assertSee('Transfer Semua Nilai');

        // 5. Admin bisa transferSemua
        $this->actingAs($admin)->post(route('ujian.hasil.transferSemua', $ujian))
            ->assertRedirect();
    }

    public function test_tampilan_show_menampilkan_badge_popup_pengampu_luar(): void
    {
        $admin = $this->admin();
        [, $guruLuar] = $this->buatGuru('guru_popup_luar');

        $ujian = $this->buatUjian($admin);
        UjianKelas::create([
            'id_ujian' => $ujian->uuid,
            'id_kelas' => $this->kelas->uuid,
            'token_masuk' => UjianKelas::generateToken(),
            'id_guru_pengampu' => $guruLuar->uuid,
        ]);

        $res = $this->actingAs($admin)->get(route('ujian.show', $ujian));
        $res->assertOk();
        $res->assertSee('Di Luar Data Ngajar');
        $res->assertSee('openBadgeInfo');
        $res->assertSee('Pengampu dapat memantau ujian &amp; menilai esai', false);
    }

    public function test_guru_mapel_paralel_di_tingkat_yang_sama_muncul_di_pilihan_pengampu(): void
    {
        $admin = $this->admin();

        // Buat 2 kelas tingkat 9: 9A dan 9C
        $kelas9A = \App\Models\Kelas::create(['tingkat' => '9', 'kelas' => 'A']);
        $kelas9C = \App\Models\Kelas::create(['tingkat' => '9', 'kelas' => 'C']);

        // Buat 2 guru: Bu Eka (ajar 9A) dan Bu Sanny (ajar 9C)
        [$userEka, $guruEka] = $this->buatGuru('eka_kurniati');
        [$userSanny, $guruSanny] = $this->buatGuru('sanny_simamora');

        Ngajar::create([
            'id_guru' => $guruEka->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas' => $kelas9A->uuid,
        ]);
        Ngajar::create([
            'id_guru' => $guruSanny->uuid,
            'id_pelajaran' => $this->pelajaran->uuid,
            'id_kelas' => $kelas9C->uuid,
        ]);

        $ujian = $this->buatUjian($admin);
        $uk9A = UjianKelas::create([
            'id_ujian' => $ujian->uuid,
            'id_kelas' => $kelas9A->uuid,
            'token_masuk' => UjianKelas::generateToken(),
            'id_guru_pengampu' => $guruEka->uuid,
        ]);
        $uk9C = UjianKelas::create([
            'id_ujian' => $ujian->uuid,
            'id_kelas' => $kelas9C->uuid,
            'token_masuk' => UjianKelas::generateToken(),
            'id_guru_pengampu' => $guruSanny->uuid,
        ]);

        // Buka halaman ujian.show
        $res = $this->actingAs($admin)->get(route('ujian.show', $ujian));
        $res->assertOk();

        $opsi = $res->viewData('guruPengampuOpsi');
        $ngajar9A = $opsi[$uk9A->uuid]['ngajar']->pluck('uuid')->all();
        $ngajar9C = $opsi[$uk9C->uuid]['ngajar']->pluck('uuid')->all();

        // Kedua guru (Bu Eka dan Bu Sanny) HARUS muncul di Guru Mapel Resmi untuk 9A dan 9C
        $this->assertContains($guruEka->uuid, $ngajar9A, 'Bu Eka harus muncul di pilihan pengampu 9A');
        $this->assertContains($guruSanny->uuid, $ngajar9A, 'Bu Sanny harus muncul di pilihan pengampu 9A');

        $this->assertContains($guruEka->uuid, $ngajar9C, 'Bu Eka harus muncul di pilihan pengampu 9C');
        $this->assertContains($guruSanny->uuid, $ngajar9C, 'Bu Sanny harus muncul di pilihan pengampu 9C');

        // Bu Sanny ditugaskan jadi pengampu 9A
        $this->actingAs($admin)->post(route('ujian.kelas.pengampu.batch', $ujian), [
            'pengampu' => [
                $uk9A->uuid => $guruSanny->uuid,
            ],
        ])->assertRedirect();

        $uk9A->refresh();
        $this->assertSame($guruSanny->uuid, $uk9A->id_guru_pengampu);

        // Bu Sanny adalah guru mapel paralel tingkat 9, sehingga BUKAN pengampu luar data ngajar
        $this->assertTrue($uk9A->pengampuSesuaiNgajar());
        $this->assertFalse($uk9A->pengampuLuarNgajar());

        // Halaman ujian.show tidak menampilkan badge 'Di Luar Data Ngajar' untuk 9A
        $resAfter = $this->actingAs($admin)->get(route('ujian.show', $ujian));
        $resAfter->assertDontSee('Di Luar Data Ngajar');

        // Bu Sanny berwenang mentransfer nilai untuk kelas 9A
        $resHasil = $this->actingAs($userSanny)->get(route('ujian.hasil.index', $ujian) . '?kelas=' . $kelas9A->uuid);
        $resHasil->assertOk();
        $resHasil->assertSee('Transfer Semua Nilai');
        $resHasil->assertDontSee('Transfer Nilai Terkunci');
    }
}
