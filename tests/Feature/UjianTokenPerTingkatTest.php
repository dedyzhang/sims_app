<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Token masuk PTS/PAS/UAS dibagi rata per TINGKAT (bukan per kelas) — tak ada kolom
 * baru, diturunkan murni dari kelas.tingkat setiap kali kelas ditambahkan/token
 * di-regenerate. Ujian target_nilai=sumatif (selalu 1 kelas) tak terdampak.
 */
class UjianTokenPerTingkatTest extends TestCase
{
    use RefreshDatabase;

    private User $guruUser;
    private User $adminUser;
    private Ujian $ujian;
    private Kelas $k7a;
    private Kelas $k7b;
    private Kelas $k8a;

    protected function setUp(): void
    {
        parent::setUp();
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $this->k7a = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->k7b = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $this->k8a = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);

        $this->guruUser = User::create(['username' => 'guru_tingkat', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $this->guruUser->uuid, 'nama' => 'Guru Tingkat', 'nik' => '2020202020', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        // Ngajar utk KETIGA kelas — syncKelas() kini memvalidasi kelas yg dipilih harus benar2
        // diajar mapel ini (lihat UjianController::kelasPilihan()), jadi test lintas-kelas butuh
        // Ngajar nyata utk tiap kelas yg mau ditetapkan, bukan cuma k7a.
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $this->k7a->uuid]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $this->k7b->uuid]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $this->k8a->uuid]);

        // Penetapan kelas (syncKelas) kini eksklusif admin/pengelola — guru pengampu cuma
        // menyusun soal, tak lagi bisa atur kelas ujiannya sendiri. Token regenerate per-
        // tingkat (kelas.token) TETAP boleh dipakai guru, tak berubah.
        $this->adminUser = User::create(['username' => 'admin_tingkat', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);

        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => 'PTS Tingkat', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 90,
        ]);
    }

    public function test_kelas_satu_tingkat_berbagi_token_yang_sama(): void
    {
        $this->actingAs($this->adminUser)->post(route('ujian.kelas.sync', $this->ujian), [
            'id_kelas' => [$this->k7a->uuid, $this->k7b->uuid],
        ])->assertRedirect();

        $tokenA = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7a->uuid)->value('token_masuk');
        $tokenB = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7b->uuid)->value('token_masuk');

        $this->assertNotEmpty($tokenA);
        $this->assertSame($tokenA, $tokenB, 'Kelas 7A dan 7B (sama-sama tingkat 7) harus dapat token identik.');
    }

    public function test_kelas_tingkat_berbeda_dapat_token_berbeda(): void
    {
        $this->actingAs($this->adminUser)->post(route('ujian.kelas.sync', $this->ujian), [
            'id_kelas' => [$this->k7a->uuid, $this->k8a->uuid],
        ])->assertRedirect();

        $token7 = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7a->uuid)->value('token_masuk');
        $token8 = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k8a->uuid)->value('token_masuk');

        $this->assertNotSame($token7, $token8);
    }

    public function test_kelas_baru_ditambahkan_belakangan_ikut_token_tingkat_yang_sudah_ada(): void
    {
        $this->actingAs($this->adminUser)->post(route('ujian.kelas.sync', $this->ujian), ['id_kelas' => [$this->k7a->uuid]])->assertRedirect();
        $tokenAwal = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7a->uuid)->value('token_masuk');

        // Sync ulang, kali ini tambah 7B (tanpa menghapus 7A).
        $this->actingAs($this->adminUser)->post(route('ujian.kelas.sync', $this->ujian), [
            'id_kelas' => [$this->k7a->uuid, $this->k7b->uuid],
        ])->assertRedirect();

        $tokenB = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7b->uuid)->value('token_masuk');
        $this->assertSame($tokenAwal, $tokenB, 'Kelas baru di tingkat yg sama harus ikut token yg SUDAH ada, bukan bikin baru.');
    }

    public function test_regenerate_token_memperbarui_seluruh_kelas_di_tingkat_yang_sama(): void
    {
        $this->actingAs($this->adminUser)->post(route('ujian.kelas.sync', $this->ujian), [
            'id_kelas' => [$this->k7a->uuid, $this->k7b->uuid, $this->k8a->uuid],
        ])->assertRedirect();

        $tokenLama7 = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7a->uuid)->value('token_masuk');
        $tokenLama8 = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k8a->uuid)->value('token_masuk');
        $ujianKelas7a = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7a->uuid)->firstOrFail();

        $this->actingAs($this->guruUser)->post(route('ujian.kelas.token', [$this->ujian, $ujianKelas7a]))->assertRedirect();

        $tokenBaru7a = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7a->uuid)->value('token_masuk');
        $tokenBaru7b = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7b->uuid)->value('token_masuk');
        $tokenSetelah8 = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k8a->uuid)->value('token_masuk');

        $this->assertNotSame($tokenLama7, $tokenBaru7a);
        $this->assertSame($tokenBaru7a, $tokenBaru7b, 'Regenerate dari 7A harus ikut mengganti token 7B (sama tingkat).');
        $this->assertSame($tokenLama8, $tokenSetelah8, 'Kelas tingkat 8 TIDAK boleh ikut berubah saat regenerate tingkat 7.');
    }

    /**
     * Reset token "sekaligus semua kelas" dipakai dari kartu ringkas /ujian (tanpa perlu
     * buka detail dulu) — TETAP boleh dipakai guru pengampu (beda dari syncKelas() yg
     * eksklusif admin), krn ini operasional harian (token bocor, dsb), bukan keputusan
     * struktural soal kelas mana yg ikut ujian.
     */
    public function test_reset_semua_token_boleh_dipakai_guru_dan_memperbarui_tiap_tingkat_independen(): void
    {
        $this->actingAs($this->adminUser)->post(route('ujian.kelas.sync', $this->ujian), [
            'id_kelas' => [$this->k7a->uuid, $this->k7b->uuid, $this->k8a->uuid],
        ])->assertRedirect();

        $tokenLama7 = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7a->uuid)->value('token_masuk');
        $tokenLama8 = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k8a->uuid)->value('token_masuk');
        $this->ujian->update(['status' => 'published']);

        $this->actingAs($this->guruUser)->post(route('ujian.token.reset', $this->ujian))->assertRedirect();

        $tokenBaru7a = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7a->uuid)->value('token_masuk');
        $tokenBaru7b = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k7b->uuid)->value('token_masuk');
        $tokenBaru8 = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->k8a->uuid)->value('token_masuk');

        $this->assertNotSame($tokenLama7, $tokenBaru7a);
        $this->assertNotSame($tokenLama8, $tokenBaru8);
        $this->assertSame($tokenBaru7a, $tokenBaru7b, 'Tingkat 7 tetap berbagi satu token.');
        $this->assertNotSame($tokenBaru7a, $tokenBaru8, 'Tingkat 7 dan 8 tetap dapat token independen.');
    }

    public function test_reset_semua_token_ditolak_tanpa_kelas_atau_saat_ditutup(): void
    {
        $this->actingAs($this->guruUser)->post(route('ujian.token.reset', $this->ujian))->assertStatus(422);

        $this->actingAs($this->adminUser)->post(route('ujian.kelas.sync', $this->ujian), ['id_kelas' => [$this->k7a->uuid]])->assertRedirect();
        $this->ujian->update(['status' => 'closed']);
        $this->actingAs($this->guruUser)->post(route('ujian.token.reset', $this->ujian))->assertStatus(422);
    }

    public function test_ujian_sumatif_tetap_satu_kelas_satu_token_tanpa_terdampak(): void
    {
        $materi = \App\Models\Materi::create([
            'id_ngajar' => Ngajar::where('id_guru', $this->guruUser->guru->uuid)->first()->uuid,
            'nama' => 'Bab 1', 'urutan' => 1,
        ]);
        $ujianHarian = Ujian::create([
            'id_pelajaran' => $this->ujian->id_pelajaran, 'id_materi' => $materi->uuid, 'created_by' => $this->guruUser->uuid,
            'judul' => 'Harian Tingkat', 'jenis' => 'harian', 'target_nilai' => 'sumatif', 'durasi_menit' => 40,
        ]);

        $this->actingAs($this->adminUser)->post(route('ujian.kelas.sync', $ujianHarian), ['id_kelas' => [$this->k7a->uuid]])->assertRedirect();
        $this->assertSame(1, UjianKelas::where('id_ujian', $ujianHarian->uuid)->count());
        $this->assertNotEmpty(UjianKelas::where('id_ujian', $ujianHarian->uuid)->value('token_masuk'));
    }
}
