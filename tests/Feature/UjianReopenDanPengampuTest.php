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

    public function test_set_pengampu_menolak_guru_yang_tak_mengajar_kelas_itu(): void
    {
        $admin = $this->admin();
        [, $guruLuar] = $this->buatGuru('guru_luar_pengampu'); // sengaja TANPA Ngajar kelas ini
        $ujian = $this->buatUjian($admin);
        $uk = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => UjianKelas::generateToken()]);

        $this->actingAs($admin)->post(route('ujian.kelas.pengampu', [$ujian, $uk]), ['id_guru_pengampu' => $guruLuar->uuid])
            ->assertStatus(422);
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

    public function test_batch_pengampu_ditolak_kalau_ada_guru_tak_valid(): void
    {
        $admin = $this->admin();
        $kelasB = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        [, $guruA] = $this->buatGuru('guru_batch_valid');
        [, $guruLuar] = $this->buatGuru('guru_batch_tak_valid'); // TANPA Ngajar kelasB

        Ngajar::create(['id_guru' => $guruA->uuid, 'id_pelajaran' => $this->pelajaran->uuid, 'id_kelas' => $this->kelas->uuid]);

        $ujian = $this->buatUjian($admin);
        $ukA = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $this->kelas->uuid, 'token_masuk' => UjianKelas::generateToken()]);
        $ukB = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $kelasB->uuid, 'token_masuk' => UjianKelas::generateToken()]);

        $this->actingAs($admin)->post(route('ujian.kelas.pengampu.batch', $ujian), [
            'pengampu' => [$ukA->uuid => $guruA->uuid, $ukB->uuid => $guruLuar->uuid],
        ])->assertStatus(422);
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
}
