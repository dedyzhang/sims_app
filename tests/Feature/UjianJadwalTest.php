<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Ujian;
use App\Models\UjianJadwal;
use App\Models\UjianKelas;
use App\Models\UjianPaket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fase 5: UjianJadwal mengisi ulang kolom yg SUDAH ADA (ujian_kelas.dibuka_mulai/
 * dibuka_sampai) lewat UjianJadwalSync — gating jam siswa (UjianPolicy::take())
 * tak berubah sedikit pun, cuma kolomnya akhirnya keisi.
 */
class UjianJadwalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $guruLain;
    private UjianPaket $paket;
    private Ujian $ujian;
    private Kelas $kelas7a;
    private Kelas $kelas8a;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::create(['username' => 'admin_jadwal', 'password' => Hash::make('rahasia123'), 'access' => 'admin']);
        $this->guruLain = User::create(['username' => 'guru_jadwal_lain', 'password' => Hash::make('rahasia123'), 'access' => 'guru']);
        Guru::create(['id_login' => $this->guruLain->uuid, 'nama' => 'Guru Jadwal Lain', 'nik' => '5555555555', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);

        $pelajaran = Pelajaran::create(['nama' => 'Bahasa Indonesia', 'kkm' => 75]);
        $this->kelas7a = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $this->kelas8a = Kelas::create(['tingkat' => 8, 'kelas' => 'A']);
        $guruAjar = Guru::create(['id_login' => $this->admin->uuid, 'nama' => 'Admin Merangkap Guru', 'nik' => '6666666666', 'jk' => 'L', 'face_descriptor' => [0.1, 0.2]]);
        Ngajar::create(['id_guru' => $guruAjar->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $this->kelas7a->uuid]);
        Ngajar::create(['id_guru' => $guruAjar->uuid, 'id_pelajaran' => $pelajaran->uuid, 'id_kelas' => $this->kelas8a->uuid]);

        $this->paket = UjianPaket::create([
            'nama' => 'PAS', 'jenis' => 'pas', 'created_by' => $this->admin->uuid,
            'tanggal_mulai' => '2026-12-01', 'tanggal_selesai' => '2026-12-10',
        ]);
        $this->ujian = Ujian::create([
            'id_pelajaran' => $pelajaran->uuid, 'created_by' => $this->admin->uuid, 'id_ujian_paket' => $this->paket->uuid,
            'judul' => 'PAS Bahasa Indonesia', 'jenis' => 'pas', 'target_nilai' => 'pas', 'durasi_menit' => 90,
        ]);
        $this->actingAs($this->admin)->post(route('ujian.kelas.sync', $this->ujian), ['id_kelas' => [$this->kelas7a->uuid]]);
    }

    public function test_simpan_jadwal_mengisi_dibuka_mulai_dan_sampai_pada_ujian_kelas(): void
    {
        $this->actingAs($this->admin)->post(route('ujian.paket.jadwal.store', $this->paket), [
            'id_ujian' => $this->ujian->uuid, 'tanggal' => '2026-12-03',
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ])->assertRedirect();

        $uk = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->kelas7a->uuid)->firstOrFail();
        $this->assertSame('2026-12-03 08:00:00', $uk->dibuka_mulai->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-03 10:00:00', $uk->dibuka_sampai->format('Y-m-d H:i:s'));
    }

    public function test_kelas_ditambahkan_setelah_jadwal_ada_ikut_dapat_window_yang_sama(): void
    {
        $this->actingAs($this->admin)->post(route('ujian.paket.jadwal.store', $this->paket), [
            'id_ujian' => $this->ujian->uuid, 'tanggal' => '2026-12-03',
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);

        // Kelas 8A ditambahkan BELAKANGAN, setelah jadwal sudah ada.
        $this->actingAs($this->admin)->post(route('ujian.kelas.sync', $this->ujian), [
            'id_kelas' => [$this->kelas7a->uuid, $this->kelas8a->uuid],
        ])->assertRedirect();

        $uk8a = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->kelas8a->uuid)->firstOrFail();
        $this->assertNotNull($uk8a->dibuka_mulai, 'Kelas baru harus ikut kebagian window jadwal yg sudah ada, bukan dibiarkan null.');
        $this->assertSame('2026-12-03 08:00:00', $uk8a->dibuka_mulai->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-03 10:00:00', $uk8a->dibuka_sampai->format('Y-m-d H:i:s'));
    }

    public function test_window_efektif_dari_dua_baris_jadwal_adalah_gabungan_min_max(): void
    {
        $this->actingAs($this->admin)->post(route('ujian.paket.jadwal.store', $this->paket), [
            'id_ujian' => $this->ujian->uuid, 'tanggal' => '2026-12-03',
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00', 'sesi_label' => 'Sesi 1',
        ]);
        $this->actingAs($this->admin)->post(route('ujian.paket.jadwal.store', $this->paket), [
            'id_ujian' => $this->ujian->uuid, 'tanggal' => '2026-12-05',
            'jam_mulai' => '13:00', 'jam_selesai' => '15:00', 'sesi_label' => 'Sesi 2 (susulan)',
        ]);

        $uk = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->kelas7a->uuid)->firstOrFail();
        $this->assertSame('2026-12-03 08:00:00', $uk->dibuka_mulai->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-05 15:00:00', $uk->dibuka_sampai->format('Y-m-d H:i:s'));
    }

    public function test_update_jadwal_menyesuaikan_ulang_window(): void
    {
        $this->actingAs($this->admin)->post(route('ujian.paket.jadwal.store', $this->paket), [
            'id_ujian' => $this->ujian->uuid, 'tanggal' => '2026-12-03',
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);
        $jadwal = UjianJadwal::firstOrFail();

        $this->actingAs($this->admin)->post(route('ujian.paket.jadwal.update', [$this->paket, $jadwal]), [
            'tanggal' => '2026-12-04', 'jam_mulai' => '09:00', 'jam_selesai' => '11:00',
        ])->assertRedirect();

        $uk = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->kelas7a->uuid)->firstOrFail();
        $this->assertSame('2026-12-04 09:00:00', $uk->dibuka_mulai->format('Y-m-d H:i:s'));
        $this->assertSame('2026-12-04 11:00:00', $uk->dibuka_sampai->format('Y-m-d H:i:s'));
    }

    /** Sesuai Keputusan Desain #6: window yg sudah terisi TIDAK dibersihkan hanya krn jadwalnya dihapus — no-op saat kosong. */
    public function test_hapus_jadwal_terakhir_tidak_membersihkan_window_yang_sudah_terisi(): void
    {
        $this->actingAs($this->admin)->post(route('ujian.paket.jadwal.store', $this->paket), [
            'id_ujian' => $this->ujian->uuid, 'tanggal' => '2026-12-03',
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ]);
        $jadwal = UjianJadwal::firstOrFail();

        $this->actingAs($this->admin)->delete(route('ujian.paket.jadwal.destroy', [$this->paket, $jadwal]))->assertRedirect();

        $this->assertDatabaseMissing('ujian_jadwal', ['uuid' => $jadwal->uuid]);
        $uk = UjianKelas::where('id_ujian', $this->ujian->uuid)->where('id_kelas', $this->kelas7a->uuid)->firstOrFail();
        $this->assertNotNull($uk->dibuka_mulai, 'Window lama dibiarkan apa adanya, bukan ikut dikosongkan.');
    }

    /** Guru harus bisa jadwalkan ujian di luar rentang tanggal_mulai/tanggal_selesai paket (mis. hari ini/lampau) — paket cuma label periode, bukan pagar tanggal jadwal. */
    public function test_tanggal_jadwal_boleh_di_luar_rentang_paket(): void
    {
        $this->actingAs($this->admin)->post(route('ujian.paket.jadwal.store', $this->paket), [
            'id_ujian' => $this->ujian->uuid, 'tanggal' => '2027-01-15',
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('ujian_jadwal', [
            'id_ujian_paket' => $this->paket->uuid, 'tanggal' => '2027-01-15 00:00:00',
        ]);
    }

    public function test_guru_bukan_pengelola_paket_tak_bisa_tambah_jadwal(): void
    {
        $this->actingAs($this->guruLain)->post(route('ujian.paket.jadwal.store', $this->paket), [
            'id_ujian' => $this->ujian->uuid, 'tanggal' => '2026-12-03',
            'jam_mulai' => '08:00', 'jam_selesai' => '10:00',
        ])->assertForbidden();
    }
}
