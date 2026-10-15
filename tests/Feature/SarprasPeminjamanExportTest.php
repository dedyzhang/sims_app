<?php

namespace Tests\Feature;

use App\Models\Guru;
use App\Models\User;
use App\Sarpras\Exports\PeminjamanBarangExport;
use App\Sarpras\Exports\PeminjamanRuanganExport;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\KategoriAset;
use App\Sarpras\Models\Peminjaman;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class SarprasPeminjamanExportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'username' => 'admin_sarpras_export',
            'password' => Hash::make('secret'),
            'access' => 'superadmin',
        ]);
    }

    private function teacher(): User
    {
        $user = User::create([
            'username' => 'guru_peminjam',
            'password' => Hash::make('secret'),
            'access' => 'guru',
        ]);

        Guru::create([
            'id_login' => $user->uuid,
            'nama' => 'Budi Santoso, S.Pd.',
            'nik' => '1985010101',
            'jk' => 'L',
        ]);

        return $user;
    }

    private function room(): DenahRuangan
    {
        $denah = Denah::create(['nama' => 'Gedung Utama', 'gambar_path' => 'utama.png']);

        return DenahRuangan::create([
            'denah_id' => $denah->id,
            'kode' => 'MM',
            'nama' => 'Ruang Multimedia',
            'gedung' => 'A',
            'lantai' => 2,
            'pos_x' => 10,
            'pos_y' => 10,
            'status' => 'tersedia',
            'kapasitas' => 40,
        ]);
    }

    private function aset(DenahRuangan $room): Aset
    {
        $kategori = KategoriAset::create(['nama' => 'Elektronik']);

        return Aset::create([
            'kategori_id' => $kategori->id,
            'ruangan_id' => $room->id,
            'kode' => 'AST-PROY-01',
            'nama' => 'Proyektor Epson',
            'kondisi' => 'baik',
            'status' => 'aktif',
            'nilai_perolehan' => 5000000,
        ]);
    }

    public function test_user_can_export_peminjaman_ruangan_excel(): void
    {
        Excel::fake();

        $admin = $this->admin();
        $room = $this->room();
        $guru = $this->teacher();

        Peminjaman::create([
            'kode' => 'BKG-2026-001',
            'peminjam_id' => $guru->uuid,
            'ruangan_id' => $room->id,
            'keperluan' => 'Olimpiade Matematika',
            'mulai' => Carbon::parse('2026-09-23 11:00'),
            'selesai' => Carbon::parse('2026-09-23 13:00'),
            'tgl_pinjam' => '2026-09-23',
            'tgl_kembali_rencana' => '2026-09-23',
            'status' => 'dikembalikan',
            'disetujui_oleh' => $admin->uuid,
            'disetujui_pada' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('sarpras.peminjaman.export.ruangan'));
        $response->assertOk();

        $expectedName = 'arsip-peminjaman-ruangan-' . now()->format('Ymd') . '.xlsx';
        Excel::assertDownloaded($expectedName, function (PeminjamanRuanganExport $export) {
            $data = $export->collection();
            $this->assertCount(1, $data);
            $mapped = $export->map($data->first());
            $this->assertSame('BKG-2026-001', $mapped[1]);
            $this->assertStringContainsString('MM - Ruang Multimedia', $mapped[2]);
            $this->assertSame('Budi Santoso, S.Pd.', $mapped[3]);
            $this->assertSame('Olimpiade Matematika', $mapped[5]);
            $this->assertSame('Selesai', $mapped[9]);

            return true;
        });
    }

    public function test_user_can_export_peminjaman_barang_excel(): void
    {
        Excel::fake();

        $admin = $this->admin();
        $room = $this->room();
        $guru = $this->teacher();
        $aset = $this->aset($room);

        $peminjaman = Peminjaman::create([
            'kode' => 'PJM-2026-001',
            'peminjam_id' => $guru->uuid,
            'keperluan' => 'Presentasi Pembelajaran',
            'mulai' => Carbon::parse('2026-09-24 08:00'),
            'selesai' => Carbon::parse('2026-09-24 10:00'),
            'tgl_pinjam' => '2026-09-24',
            'tgl_kembali_rencana' => '2026-09-24',
            'status' => 'dipinjam',
            'disetujui_oleh' => $admin->uuid,
            'disetujui_pada' => now(),
        ]);

        $peminjaman->items()->create([
            'aset_id' => $aset->id,
            'qty' => 1,
            'kondisi_pinjam' => 'baik',
        ]);

        $response = $this->actingAs($admin)->get(route('sarpras.peminjaman.export.barang'));
        $response->assertOk();

        $expectedName = 'arsip-peminjaman-barang-' . now()->format('Ymd') . '.xlsx';
        Excel::assertDownloaded($expectedName, function (PeminjamanBarangExport $export) {
            $data = $export->collection();
            $this->assertCount(1, $data);
            $mapped = $export->map($data->first());
            $this->assertSame('PJM-2026-001', $mapped[1]);
            $this->assertSame('Budi Santoso, S.Pd.', $mapped[2]);
            $this->assertStringContainsString('Proyektor Epson', $mapped[4]);
            $this->assertSame(1, $mapped[5]);
            $this->assertSame('Presentasi Pembelajaran', $mapped[6]);
            $this->assertSame('Dipinjam', $mapped[10]);

            return true;
        });
    }

    public function test_laporan_export_peminjaman_ruangan_and_barang(): void
    {
        Excel::fake();

        $admin = $this->admin();

        $respRuangan = $this->actingAs($admin)->get(route('sarpras.laporan.peminjaman-ruangan.excel'));
        $respRuangan->assertOk();
        Excel::assertDownloaded('arsip-peminjaman-ruangan-' . now()->format('Ymd') . '.xlsx');

        $respBarang = $this->actingAs($admin)->get(route('sarpras.laporan.peminjaman-barang.excel'));
        $respBarang->assertOk();
        Excel::assertDownloaded('arsip-peminjaman-barang-' . now()->format('Ymd') . '.xlsx');
    }

    public function test_peminjaman_index_view_renders_export_buttons(): void
    {
        $admin = $this->admin();

        // Cek tab ruangan
        $responseRuangan = $this->actingAs($admin)->get(route('sarpras.peminjaman.index', ['tab' => 'ruangan']));
        $responseRuangan->assertOk();
        $responseRuangan->assertSee(route('sarpras.peminjaman.export.ruangan'));
        $responseRuangan->assertSee('Ekspor Excel');

        // Cek tab barang
        $responseBarang = $this->actingAs($admin)->get(route('sarpras.peminjaman.index', ['tab' => 'barang']));
        $responseBarang->assertOk();
        $responseBarang->assertSee(route('sarpras.peminjaman.export.barang'));
        $responseBarang->assertSee('Ekspor Excel');
    }
}
