<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Ujian;
use App\Models\UjianPaket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 3: UjianPaket = folder pengelompokan periode ujian formal (mis. "PAS
 * Semester 1 2026/2027") — menaungi ujian anggota (attach/detach ujian
 * standalone yg SUDAH ADA), otorisasi admin/manage_ujian/pembuat paket.
 */
class UjianPaketTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guruA;
    private User $guruB;
    private Pelajaran $pelajaran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['username' => 'admin_paket', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);

        $this->guruA = User::create(['username' => 'guru_paket_a', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $this->guruA->uuid, 'nama' => 'Guru Paket A', 'nik' => '1111111111', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->guruB = User::create(['username' => 'guru_paket_b', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $this->guruB->uuid, 'nama' => 'Guru Paket B', 'nik' => '2222222222', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $this->pelajaran = Pelajaran::create(['nama' => 'IPA', 'kkm' => 75]);
    }

    public function test_guru_bisa_membuat_paket_dan_jadi_pemilik(): void
    {
        $semester = Semester::create(['semester' => 1, 'tahun' => '2026/2027', 'aktif' => true]);

        $res = $this->actingAs($this->guruA)->post(route('ujian.paket.store'), [
            'nama' => 'PAS Semester 1 2026/2027',
            'jenis' => 'pas',
            'id_semester' => $semester->id,
            'tanggal_mulai' => '2026-12-01',
            'tanggal_selesai' => '2026-12-10',
        ]);
        $res->assertRedirect();

        $paket = UjianPaket::firstOrFail();
        $this->assertSame('PAS Semester 1 2026/2027', $paket->nama);
        $this->assertSame($this->guruA->uuid, $paket->created_by);
        $this->assertSame('draft', $paket->status);
    }

    public function test_siswa_tak_bisa_mengakses_halaman_paket(): void
    {
        $siswaUser = User::create(['username' => 'siswa_paket', 'password' => Hash::make('rahasia123'), 'access' => 'siswa']);
        $this->actingAs($siswaUser)->get(route('ujian.paket.index'))->assertForbidden();
    }

    public function test_pembuat_paket_bisa_tambah_dan_lepas_ujian_standalone(): void
    {
        $paket = UjianPaket::create(['nama' => 'PAS', 'jenis' => 'pas', 'created_by' => $this->guruA->uuid]);
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->guruA->uuid,
            'judul' => 'PAS IPA', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ]);

        $this->actingAs($this->guruA)
            ->post(route('ujian.paket.tambahUjian', $paket), ['id_ujian' => $ujian->uuid])
            ->assertRedirect();
        $this->assertSame($paket->uuid, $ujian->fresh()->id_ujian_paket);

        $this->actingAs($this->guruA)
            ->post(route('ujian.paket.lepasUjian', [$paket, $ujian]))
            ->assertRedirect();
        $this->assertNull($ujian->fresh()->id_ujian_paket);
    }

    public function test_guru_lain_tak_bisa_tambah_ujian_ke_paket_orang(): void
    {
        $paket = UjianPaket::create(['nama' => 'PAS', 'jenis' => 'pas', 'created_by' => $this->guruA->uuid]);
        $ujianMilikB = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->guruB->uuid,
            'judul' => 'PAS milik B', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ]);

        $this->actingAs($this->guruB)
            ->post(route('ujian.paket.tambahUjian', $paket), ['id_ujian' => $ujianMilikB->uuid])
            ->assertForbidden();
        $this->assertNull($ujianMilikB->fresh()->id_ujian_paket);
    }

    public function test_guru_lain_tak_bisa_hapus_atau_update_paket_orang(): void
    {
        $paket = UjianPaket::create(['nama' => 'PAS', 'jenis' => 'pas', 'created_by' => $this->guruA->uuid]);

        $this->actingAs($this->guruB)
            ->post(route('ujian.paket.update', $paket), ['nama' => 'Diubah', 'jenis' => 'pas', 'status' => 'draft'])
            ->assertForbidden();
        $this->actingAs($this->guruB)->delete(route('ujian.paket.destroy', $paket))->assertForbidden();
        $this->assertSame('PAS', $paket->fresh()->nama);
    }

    public function test_hapus_paket_tidak_ikut_menghapus_ujian_anggota(): void
    {
        $paket = UjianPaket::create(['nama' => 'PAS', 'jenis' => 'pas', 'created_by' => $this->guruA->uuid]);
        $ujian = Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->guruA->uuid, 'id_ujian_paket' => $paket->uuid,
            'judul' => 'PAS IPA', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ]);

        $this->actingAs($this->guruA)->delete(route('ujian.paket.destroy', $paket))->assertRedirect();

        $this->assertDatabaseMissing('ujian_paket', ['uuid' => $paket->uuid]);
        $this->assertDatabaseHas('ujians', ['uuid' => $ujian->uuid, 'id_ujian_paket' => null]);
    }

    public function test_halaman_index_dan_show_render_tanpa_error(): void
    {
        $semester = Semester::create(['semester' => 1, 'tahun' => '2026/2027', 'aktif' => true]);
        $paket = UjianPaket::create(['nama' => 'PAS', 'jenis' => 'pas', 'id_semester' => $semester->id, 'created_by' => $this->guruA->uuid]);
        Ujian::create([
            'id_pelajaran' => $this->pelajaran->uuid, 'created_by' => $this->guruA->uuid, 'id_ujian_paket' => $paket->uuid,
            'judul' => 'PAS IPA', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 60,
        ]);

        $this->actingAs($this->guruA)->get(route('ujian.paket.index'))->assertOk()->assertSee('PAS');
        $this->actingAs($this->guruA)->get(route('ujian.paket.show', $paket))->assertOk()->assertSee('PAS IPA');
        $this->actingAs($this->guruA)->get(route('ujian.paket.create'))->assertOk();
    }

    public function test_admin_bisa_kelola_paket_milik_siapapun(): void
    {
        $paket = UjianPaket::create(['nama' => 'PAS', 'jenis' => 'pas', 'created_by' => $this->guruA->uuid]);

        $this->actingAs($this->admin)
            ->post(route('ujian.paket.update', $paket), ['nama' => 'PAS (diedit admin)', 'jenis' => 'pas', 'status' => 'berjalan'])
            ->assertRedirect();
        $this->assertSame('PAS (diedit admin)', $paket->fresh()->nama);
    }
}
