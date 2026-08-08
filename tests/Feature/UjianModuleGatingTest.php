<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Models\User;
use App\Support\ModulAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 5 modul Ujian: `fitur_ujian_aktif` off -> SEMUA route ujian.* (authoring
 * guru maupun pengerjaan siswa) 403, utk semua peran. Regresi non-crash lintas
 * modul lain sudah dicakup ModulAktifTest::test_mematikan_modul_apapun_....
 */
class UjianModuleGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set(ModulAktif::settingKey('ujian'), '0');
    }

    public function test_modul_ujian_nonaktif_diverifikasi(): void
    {
        $this->assertFalse(ModulAktif::aktif('ujian'));
    }

    public function test_guru_ditolak_akses_semua_route_authoring_saat_modul_off(): void
    {
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $guruUser = User::create(['username' => 'guru_gating', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        $guru = Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Gating', 'nik' => '9999999999', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);

        $this->actingAs($guruUser)->get(route('ujian.index'))->assertForbidden();
        $this->actingAs($guruUser)->get(route('ujian.create'))->assertForbidden();
        $this->actingAs($guruUser)->post(route('ujian.store'), [
            'judul' => 'Nekat', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'id_pelajaran' => $pelajaran->uuid, 'durasi_menit' => 60,
        ])->assertForbidden();
        $this->assertDatabaseMissing('ujians', ['judul' => 'Nekat']);
    }

    public function test_siswa_ditolak_akses_semua_route_pengerjaan_saat_modul_off(): void
    {
        // Siapkan ujian VALID (dibuat lewat model langsung, bukan HTTP, krn modul
        // memang sedang off) supaya kita bisa uji rute siswa terhadapnya.
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $guruUser = User::create(['username' => 'guru_gating2', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $guruUser->uuid, 'nama' => 'Guru Gating 2', 'nik' => '1010101010', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $guruUser->uuid,
            'judul' => 'PTS Gating', 'jenis' => 'pts', 'target_nilai' => 'pts',
            'durasi_menit' => 60, 'status' => 'published',
        ]);
        UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $kelas->uuid, 'token_masuk' => 'GATINGTOK']);

        $siswaUser = User::create(['username' => 'siswa_gating', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswaUser->uuid, 'id_kelas' => $kelas->uuid, 'nama' => 'Siswa Gating', 'nis' => '9001', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->actingAs($siswaUser)->get(route('ujian.siswa.index'))->assertForbidden();
        $this->actingAs($siswaUser)->get(route('ujian.siswa.gate', $ujian))->assertForbidden();
        $this->actingAs($siswaUser)->post(route('ujian.siswa.start', $ujian), ['token' => 'GATINGTOK'])->assertForbidden();

        $this->assertSame(0, \App\Models\UjianAttempt::where('id_siswa', $siswaUser->uuid)->count());
    }

    public function test_admin_ditolak_juga_saat_modul_off(): void
    {
        $admin = User::create(['username' => 'admin_gating', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $this->actingAs($admin)->get(route('ujian.index'))->assertForbidden();
    }

    public function test_sidebar_tidak_menampilkan_menu_ujian_saat_modul_off(): void
    {
        $siswaUser = User::create(['username' => 'siswa_gating_sidebar', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        Siswa::create(['id_login' => $siswaUser->uuid, 'nama' => 'Siswa Sidebar', 'nis' => '9002', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $html = $this->actingAs($siswaUser)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('Ujian Saya', $html);
    }

    public function test_sidebar_menu_ujian_tampil_utk_staf_dual_role_yg_mengajar(): void
    {
        Setting::set(ModulAktif::settingKey('ujian'), '1');

        // Staf kesiswaan yg JUGA punya profil Guru (dual-role, sungguhan mengajar) — menu
        // "Kelola Ujian" harus tampil, sama spt pola "Buku Guru" (auth()->user()?->guru).
        $kesiswaanGuru = User::create(['username' => 'kesiswaan_sidebar', 'password' => Hash::make('rahasia123'), 'access' => 'kesiswaan']);
        Guru::create(['id_login' => $kesiswaanGuru->uuid, 'nama' => 'Kesiswaan Guru', 'nik' => '5050505050', 'jk' => 'P', 'face_descriptor' => [0.1, 0.2]]);

        $html = $this->actingAs($kesiswaanGuru)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString('Kelola Ujian', $html);

        // Staf sapras TANPA profil Guru (murni non-pengajar) — menu tidak boleh muncul.
        $saprasSaja = User::create(['username' => 'sapras_sidebar', 'password' => Hash::make('rahasia123'), 'access' => 'sapras']);
        $html2 = $this->actingAs($saprasSaja)->get('/dashboard')->assertOk()->getContent();
        $this->assertStringNotContainsString('Kelola Ujian', $html2);
    }
}
