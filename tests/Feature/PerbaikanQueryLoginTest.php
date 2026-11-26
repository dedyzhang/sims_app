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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Perbaikan sisi query utk 1.800 siswa login serentak (26-11-2026):
 * 1. index siswa.id_login & gurus.id_login (dulu scan seluruh tabel di tiap halaman);
 * 2. nama di header utk siswa cukup ambil kolom `nama` (dulu `select *` guru lalu siswa —
 *    baris siswa membawa data wajah ±92 KB);
 * 4. kirim token dikunci singkat per siswa supaya attempt tak mungkin tercipta dobel.
 * (3 = lepas koneksi DB saat cek password: tak aktif di dalam transaksi test, dicakup AuthTest;
 *  5 = setting tak ada tak di-query ulang: dicakup tests/Unit/SettingMemoTest.)
 */
class PerbaikanQueryLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_id_login_ada_di_tabel_siswa_dan_gurus(): void
    {
        $this->assertTrue(Schema::hasIndex('siswa', ['id_login']), 'siswa.id_login harus ber-index');
        $this->assertTrue(Schema::hasIndex('gurus', ['id_login']), 'gurus.id_login harus ber-index');
    }

    public function test_nama_header_siswa_cuma_ambil_kolom_nama_tanpa_query_guru(): void
    {
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $user = User::create(['username' => 'siswa_nama_header', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $user->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Budi Santoso', 'nis' => '9911', 'jk' => 'L', 'face_descriptor' => array_fill(0, 128, 0.123456789)]);

        $user = User::find($user->uuid);
        $query = [];
        DB::listen(function ($e) use (&$query) { $query[] = $e->sql; });

        $this->assertSame('Budi Santoso', $user->displayName());
        $this->assertSame('B', $user->initial()); // memakai memo, tak query lagi

        $this->assertCount(1, $query, 'Cukup 1 query: ' . implode(' | ', $query));
        $this->assertStringNotContainsString('gurus', $query[0]);
        $this->assertStringNotContainsString('select *', $query[0]);
    }

    public function test_nama_header_guru_tetap_sama_seperti_sebelumnya(): void
    {
        $user = User::create(['username' => 'guru_nama_header', 'password' => Hash::make('x'), 'access' => 'guru']);
        Guru::create(['id_login' => $user->uuid, 'nama' => 'Bu Sari', 'nik' => '1234567890', 'jk' => 'P', 'face_descriptor' => [0.1]]);

        $this->assertSame('Bu Sari', User::find($user->uuid)->displayName());
    }

    public function test_kirim_token_menunggu_lalu_minta_coba_lagi_bila_request_sebelumnya_masih_diproses(): void
    {
        $pelajaran = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'B']);
        $guruUser = User::create(['username' => 'guru_kunci', 'password' => Hash::make('x'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru', 'nik' => '5554443332', 'jk' => 'L', 'face_descriptor' => [0.1]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);
        $ujian = Ujian::create(['id_pelajaran' => $pelajaran->uuid, 'created_by' => $guruUser->uuid, 'judul' => 'PTS Kunci', 'jenis' => 'pts', 'target_nilai' => 'pts', 'durasi_menit' => 60, 'status' => 'published']);
        UjianSoal::create(['id_ujian' => $ujian->uuid, 'tipe' => 'essay', 'teks_soal' => 'Jelaskan.', 'poin' => 5, 'urutan' => 1]);
        $uk = UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $kelas->uuid, 'token_masuk' => '7777']);
        $siswa = User::create(['username' => 'siswa_kunci', 'password' => Hash::make('x'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswa->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Kunci', 'nis' => '7711', 'jk' => 'L', 'face_descriptor' => [0.1]]);

        // Seolah request token pertama dari siswa yg sama masih berjalan (memegang kunci).
        $kunci = Cache::lock('ujian_mulai_' . $uk->uuid . '_' . $siswa->uuid, 15);
        $this->assertTrue($kunci->get());

        $this->actingAs($siswa)->postJson(route('ujian.siswa.start', $ujian), ['token' => '7777'])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '3');
        $this->assertSame(0, UjianAttempt::where('id_siswa', $siswa->uuid)->count(), 'Tak boleh membuat attempt saat kunci dipegang request lain');

        $kunci->release();

        $this->actingAs($siswa)->postJson(route('ujian.siswa.start', $ujian), ['token' => '7777'])->assertOk();
        $this->actingAs($siswa)->postJson(route('ujian.siswa.start', $ujian), ['token' => '7777'])->assertOk();
        $this->assertSame(1, UjianAttempt::where('id_siswa', $siswa->uuid)->count(), 'Tetap satu attempt');
    }
}
