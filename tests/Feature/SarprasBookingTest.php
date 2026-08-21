<?php

namespace Tests\Feature;

use App\Models\User;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\KategoriAset;
use App\Sarpras\Models\Peminjaman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarprasBookingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['username' => 'sap_booking', 'password' => Hash::make('x'), 'access' => 'superadmin']);
    }

    private function room(): DenahRuangan
    {
        $denah = Denah::create(['nama' => 'Lantai 1', 'gambar_path' => 'x.png']);

        return DenahRuangan::create([
            'denah_id' => $denah->id, 'kode' => '7A', 'nama' => 'Kelas 7A',
            'pos_x' => 10, 'pos_y' => 10, 'status' => 'tersedia', 'kapasitas' => 32,
            'fasilitas' => ['Proyektor', 'AC'],
        ]);
    }

    public function test_peminjaman_ruangan_langsung_dipinjam_tanpa_persetujuan(): void
    {
        $room = $this->room();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/sarpras/peminjaman-ruangan', [
            'ruangan_id' => $room->id,
            'keperluan' => 'Rapat Komite',
            'tanggal' => '2026-07-01',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
        ])->assertRedirect();

        $this->assertDatabaseHas('sarpras_peminjaman', [
            'ruangan_id' => $room->id, 'keperluan' => 'Rapat Komite', 'status' => 'dipinjam',
            'disetujui_oleh' => $admin->uuid,
        ]);
    }

    public function test_ruangan_bentrok_ditolak(): void
    {
        $room = $this->room();
        $admin = $this->admin();

        Peminjaman::create([
            'kode' => 'PJM-TEST-1',
            'peminjam_id' => $admin->uuid,
            'ruangan_id' => $room->id,
            'keperluan' => 'Awal',
            'mulai' => Carbon::parse('2026-07-01 09:00'),
            'selesai' => Carbon::parse('2026-07-01 11:00'),
            'tgl_pinjam' => '2026-07-01',
            'tgl_kembali_rencana' => '2026-07-01',
            'status' => 'dipinjam',
        ]);

        $this->actingAs($admin)->post('/sarpras/peminjaman-ruangan', [
            'ruangan_id' => $room->id,
            'keperluan' => 'Bentrok',
            'tanggal' => '2026-07-01',
            'jam_mulai' => '08:00',
            'jam_selesai' => '10:00',
        ])->assertSessionHas('gagal', 'Ruangan sudah digunakan atau dipinjam pada rentang waktu tersebut.');

        $this->assertDatabaseMissing('sarpras_peminjaman', ['keperluan' => 'Bentrok']);
    }

    public function test_peminjaman_barang_langsung_dipinjam_dan_aset_ditandai_dipinjam(): void
    {
        $admin = $this->admin();
        $kategori = \App\Sarpras\Models\KategoriAset::create(['kode' => 'KAT-PJM', 'nama' => 'Elektronik']);
        $aset = \App\Sarpras\Models\Aset::create([
            'kode' => 'AST-PJM-001',
            'nama' => 'Proyektor',
            'kategori_id' => $kategori->id,
            'kondisi' => 'baik',
            'status' => 'aktif',
            'nilai_perolehan' => 1000000,
            'tgl_perolehan' => '2026-01-01',
            'masa_manfaat_tahun' => 5,
        ]);

        $this->actingAs($admin)->post('/sarpras/peminjaman', [
            'keperluan' => 'Presentasi kelas',
            'mulai' => '2026-07-04 08:00',
            'selesai' => '2026-07-04 10:00',
            'aset_id' => [$aset->id],
            'qty' => [$aset->id => 1],
        ])->assertRedirect();

        $this->assertDatabaseHas('sarpras_peminjaman', [
            'keperluan' => 'Presentasi kelas',
            'status' => 'dipinjam',
            'disetujui_oleh' => $admin->uuid,
        ]);
        $this->assertSame('dipinjam', $aset->fresh()->status);
    }

    public function test_setujui_dan_tolak_peminjaman_ruangan(): void
    {
        $room = $this->room();
        $admin = $this->admin();

        $p = Peminjaman::create([
            'kode' => 'PJM-T-1',
            'peminjam_id' => $admin->uuid,
            'ruangan_id' => $room->id,
            'keperluan' => 'Acara',
            'mulai' => Carbon::parse('2026-07-02 08:00'),
            'selesai' => Carbon::parse('2026-07-02 10:00'),
            'tgl_pinjam' => '2026-07-02',
            'tgl_kembali_rencana' => '2026-07-02',
            'status' => 'diajukan',
        ]);

        $this->actingAs($admin)->post('/sarpras/peminjaman/' . $p->id . '/setujui')->assertRedirect();
        $this->assertSame('dipinjam', $p->fresh()->status);

        $p2 = Peminjaman::create([
            'kode' => 'PJM-T-2',
            'peminjam_id' => $admin->uuid,
            'ruangan_id' => $room->id,
            'keperluan' => 'Acara 2',
            'mulai' => Carbon::parse('2026-07-03 08:00'),
            'selesai' => Carbon::parse('2026-07-03 10:00'),
            'tgl_pinjam' => '2026-07-03',
            'tgl_kembali_rencana' => '2026-07-03',
            'status' => 'diajukan',
        ]);
        $this->actingAs($admin)->post('/sarpras/peminjaman/' . $p2->id . '/tolak', [
            'alasan_tolak' => 'Bentrok jadwal',
        ])->assertRedirect();
        $this->assertSame('ditolak', $p2->fresh()->status);
    }

    public function test_booking_route_redirect_ke_peminjaman_ruangan(): void
    {
        $this->actingAs($this->admin())
            ->get('/sarpras/booking')
            ->assertRedirect(route('sarpras.peminjaman.index', ['tab' => 'ruangan']));
    }

    public function test_tab_ruangan_menampilkan_ringkasan_inventaris_kelas(): void
    {
        $room = $this->room();
        $admin = $this->admin();
        $kategori = KategoriAset::create(['kode' => 'KLS', 'nama' => 'Perlengkapan Kelas']);

        Aset::create([
            'kode' => 'INV-7A-001',
            'nama' => 'Proyektor Kelas',
            'kategori_id' => $kategori->id,
            'ruangan_id' => $room->id,
            'kondisi' => 'baik',
            'status' => 'aktif',
            'nilai_perolehan' => 2500000,
            'tgl_perolehan' => '2026-01-01',
            'masa_manfaat_tahun' => 5,
        ]);

        Aset::create([
            'kode' => 'INV-7A-002',
            'nama' => 'Kursi Siswa',
            'kategori_id' => $kategori->id,
            'ruangan_id' => $room->id,
            'kondisi' => 'rusak_ringan',
            'status' => 'aktif',
            'nilai_perolehan' => 150000,
            'tgl_perolehan' => '2026-01-01',
            'masa_manfaat_tahun' => 5,
        ]);

        $this->actingAs($admin)
            ->get('/sarpras/peminjaman?tab=ruangan')
            ->assertOk()
            ->assertSee('Inventaris kelas')
            ->assertSee('2 unit perlengkapan')
            ->assertSee('1 baik')
            ->assertSee('1 cek')
            ->assertSee('Proyektor Kelas')
            ->assertSee('Kursi Siswa')
            ->assertSee('Detail inventaris');
    }

    public function test_tab_barang_menyediakan_akses_kelola_inventaris_untuk_pengelola(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/sarpras/peminjaman?tab=barang')
            ->assertOk()
            ->assertSee('Data Inventaris Sarpras')
            ->assertSee('Buka Inventaris')
            ->assertSee('Tambah Manual')
            ->assertSee('Impor Excel')
            ->assertSee('Unduh template Excel')
            ->assertSee('Proses Impor')
            ->assertSee('name="after_import"', false)
            ->assertSee('value="peminjaman_barang"', false);
    }

    public function test_tab_barang_tidak_menampilkan_aksi_kelola_inventaris_untuk_guru(): void
    {
        $guru = User::create([
            'username' => 'guru_barang_inventaris',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $this->actingAs($guru)
            ->get('/sarpras/peminjaman?tab=barang')
            ->assertOk()
            ->assertSee('Data Inventaris Sarpras')
            ->assertSee('Buka Inventaris')
            ->assertDontSee('Tambah Manual')
            ->assertDontSee('Impor Excel')
            ->assertDontSee('Proses Impor');
    }
}
