<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Bug nyata (dilaporkan user via screenshot produksi): PelajaranController::destroy() dulu
 * langsung hapus mata pelajaran TANPA cek apakah masih dipakai penugasan guru (Ngajar) atau
 * jadwal — meninggalkan baris Ngajar/Jadwal dgn id_pelajaran yg menunjuk ke baris yg sudah tak
 * ada. Halaman Ruang Kelas lalu crash "Missing required parameter for [Route: classroom.subject]"
 * krn route() dipanggil dgn model relasi null. Dua lapis perbaikan: (1) destroy() sekarang
 * menolak hapus mapel yg masih dipakai, (2) ClassroomController::kelas() menyaring baris yg
 * SUDAH terlanjur nyangkut (data lama) supaya tak crash sementara admin belum sempat rapikan.
 */
class PelajaranOrphanCrashTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Test School']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);
    }

    private function admin(): User
    {
        return User::create([
            'username' => 'admin_pelajaran_orphan',
            'password' => Hash::make('x'),
            'access' => 'superadmin',
        ]);
    }

    public function test_hapus_pelajaran_ditolak_jika_masih_dipakai_ngajar(): void
    {
        $admin = $this->admin();
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'Matematika', 'kkm' => 75]);
        $guru = Guru::create(['nama' => 'Guru Ngajar', 'nik' => 'ORPHAN-1', 'jk' => 'L', 'face_descriptor' => [0.1]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $kelas->uuid]);

        $this->actingAs($admin)->deleteJson("/pelajaran/{$pelajaran->uuid}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('pelajarans', ['uuid' => $pelajaran->uuid]);
    }

    public function test_hapus_pelajaran_ditolak_jika_masih_dipakai_jadwal(): void
    {
        $admin = $this->admin();
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'B']);
        $pelajaran = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
        $guru = Guru::create(['nama' => 'Guru Jadwal', 'nik' => 'ORPHAN-2', 'jk' => 'L', 'face_descriptor' => [0.1]]);
        Jadwal::create([
            'id_kelas' => $kelas->uuid, 'hari' => 1, 'jam_ke' => 1,
            'jam_mulai' => '07:00', 'jam_selesai' => '07:40',
            'id_pelajaran' => $pelajaran->uuid, 'id_guru' => $guru->uuid,
        ]);

        $this->actingAs($admin)->deleteJson("/pelajaran/{$pelajaran->uuid}")
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseHas('pelajarans', ['uuid' => $pelajaran->uuid]);
    }

    public function test_hapus_pelajaran_tetap_boleh_jika_tak_dipakai(): void
    {
        $admin = $this->admin();
        $pelajaran = Pelajaran::create(['nama' => 'Seni Budaya', 'kkm' => 75]);

        $this->actingAs($admin)->deleteJson("/pelajaran/{$pelajaran->uuid}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('pelajarans', ['uuid' => $pelajaran->uuid]);
    }

    public function test_ruang_kelas_tidak_crash_walau_ada_ngajar_dgn_pelajaran_terhapus(): void
    {
        $admin = $this->admin();
        $kelas = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        Semester::create(['semester' => 1, 'tahun' => '2025/2026', 'aktif' => true]);

        // Simulasikan data lama yg SUDAH terlanjur orphan (spt sblm guard destroy() ada) —
        // hapus mentah lewat query builder supaya event model & guard tak ikut jalan, mirip
        // kondisi row lama peninggalan sebelum perbaikan ini.
        $pelajaranHilang = Pelajaran::create(['nama' => 'Mapel Akan Dihapus', 'kkm' => 75]);
        $guru = Guru::create(['nama' => 'Guru Orphan', 'nik' => 'ORPHAN-3', 'jk' => 'L', 'face_descriptor' => [0.1]]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaranHilang->uuid, 'id_kelas' => $kelas->uuid]);

        $pelajaranMasihAda = Pelajaran::create(['nama' => 'Mapel Sehat', 'kkm' => 75, 'urutan' => 1]);
        Ngajar::create(['id_guru' => $guru->uuid, 'id_pelajaran' => $pelajaranMasihAda->uuid, 'id_kelas' => $kelas->uuid]);

        \Illuminate\Support\Facades\DB::table('pelajarans')->where('uuid', $pelajaranHilang->uuid)->delete();

        $response = $this->actingAs($admin)->get(route('classroom.kelas', $kelas));

        $response->assertOk();
        $response->assertSee('Mapel Sehat');
        $response->assertDontSee('Mapel Akan Dihapus');
    }
}
