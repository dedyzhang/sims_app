<?php

namespace Tests\Feature;

use App\Models\User;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\BookingRuangan;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\JadwalPemeliharaan;
use App\Sarpras\Models\KategoriAset;
use App\Sarpras\Models\LaporanKerusakan;
use App\Sarpras\Models\MutasiAset;
use App\Sarpras\Models\Peminjaman;
use App\Sarpras\Models\Perbaikan;
use App\Sarpras\Models\Pengadaan;
use App\Sarpras\Models\Penghapusan;
use App\Sarpras\Models\Supplier;
use App\Sarpras\Models\Teknisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarprasTableWrappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_tabel_sarpras_render_dengan_data_panjang(): void
    {
        Carbon::setTestNow('2026-07-09 09:00:00');

        $admin = User::create([
            'username' => 'sap_table_wrap',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);

        $long = 'Nama Data Sarpras Sangat Panjang Untuk Menguji Teks Tabel Agar Turun Baris Dan Tidak Keluar Jalur Tampilan';

        $kategori = KategoriAset::create(['kode' => 'KAT-WRAP', 'nama' => 'Kategori ' . $long]);
        $denah = Denah::create(['nama' => 'Denah ' . $long, 'gedung' => 'Gedung ' . $long, 'lantai' => 'Lantai 1']);
        $ruanganA = DenahRuangan::create([
            'denah_id' => $denah->id,
            'kode' => 'R-WRAP-A',
            'nama' => 'Ruangan Asal ' . $long,
            'status' => 'tersedia',
        ]);
        $ruanganB = DenahRuangan::create([
            'denah_id' => $denah->id,
            'kode' => 'R-WRAP-B',
            'nama' => 'Ruangan Tujuan ' . $long,
            'status' => 'maintenance',
        ]);
        $aset = Aset::create([
            'kode' => 'AST-WRAP-001',
            'nama' => 'Aset ' . $long,
            'kategori_id' => $kategori->id,
            'ruangan_id' => $ruanganA->id,
            'kondisi' => 'rusak_ringan',
            'status' => 'aktif',
            'nilai_perolehan' => 1500000,
            'tgl_perolehan' => '2025-01-01',
            'masa_manfaat_tahun' => 5,
        ]);

        $supplier = Supplier::create([
            'nama' => 'Supplier ' . $long,
            'kontak' => 'Kontak ' . $long,
            'telepon' => '08123456789',
            'alamat' => 'Alamat ' . $long,
            'npwp' => '123456789012345',
        ]);
        $teknisi = Teknisi::create([
            'nama' => 'Teknisi ' . $long,
            'tipe' => 'internal',
            'spesialisasi' => 'Spesialisasi ' . $long,
            'telepon' => '08123456789',
        ]);

        LaporanKerusakan::create([
            'kode' => 'KR-WRAP-001',
            'aset_id' => $aset->id,
            'ruangan_id' => $ruanganA->id,
            'pelapor_id' => $admin->uuid,
            'deskripsi' => 'Laporan kerusakan ' . $long,
            'urgensi' => 'tinggi',
            'status' => 'dilaporkan',
        ]);
        $peminjaman = Peminjaman::create([
            'kode' => 'PJM-WRAP-001',
            'peminjam_id' => $admin->uuid,
            'keperluan' => 'Keperluan peminjaman ' . $long,
            'tgl_pinjam' => '2026-07-09',
            'tgl_kembali_rencana' => '2026-07-10',
            'status' => 'diajukan',
        ]);
        $peminjaman->items()->create(['aset_id' => $aset->id, 'qty' => 1]);
        BookingRuangan::create([
            'ruangan_id' => $ruanganA->id,
            'pemohon_id' => $admin->uuid,
            'keperluan' => 'Booking ' . $long,
            'mulai' => Carbon::parse('2026-07-09 10:00'),
            'selesai' => Carbon::parse('2026-07-09 11:00'),
            'status' => 'diajukan',
        ]);
        $pengadaan = Pengadaan::create([
            'kode' => 'PGD-WRAP-001',
            'judul' => 'Pengadaan ' . $long,
            'deskripsi' => 'Deskripsi pengadaan ' . $long,
            'diajukan_oleh' => $admin->uuid,
            'status' => 'diajukan',
            'total_estimasi' => 2500000,
        ]);
        $pengadaan->items()->create([
            'kategori_id' => $kategori->id,
            'supplier_id' => $supplier->id,
            'nama_barang' => 'Barang ' . $long,
            'qty' => 1,
            'satuan' => 'unit',
            'estimasi_harga' => 2500000,
        ]);
        Perbaikan::create([
            'kode' => 'PRB-WRAP-001',
            'aset_id' => $aset->id,
            'teknisi_id' => $teknisi->id,
            'deskripsi' => 'Perbaikan ' . $long,
            'catatan' => 'Catatan ' . $long,
            'status' => 'dikerjakan',
            'biaya' => 350000,
            'tgl_mulai' => '2026-07-09',
        ]);
        JadwalPemeliharaan::create([
            'aset_id' => $aset->id,
            'nama' => 'Jadwal ' . $long,
            'interval_hari' => 30,
            'tgl_berikutnya' => '2026-07-09',
            'aktif' => true,
        ]);
        MutasiAset::create([
            'aset_id' => $aset->id,
            'ruangan_asal_id' => $ruanganA->id,
            'ruangan_tujuan_id' => $ruanganB->id,
            'alasan' => 'Alasan mutasi ' . $long,
            'tgl_mutasi' => '2026-07-09',
            'dilakukan_oleh' => $admin->uuid,
        ]);
        Penghapusan::create([
            'kode' => 'PHP-WRAP-001',
            'aset_id' => $aset->id,
            'alasan' => 'Alasan penghapusan ' . $long,
            'metode' => 'jual',
            'status' => 'diajukan',
            'diajukan_oleh' => $admin->uuid,
        ]);

        foreach ([
            '/sarpras/aset',
            '/sarpras/kerusakan',
            '/sarpras/peminjaman',
            '/sarpras/booking',
            '/sarpras/pengadaan',
            '/sarpras/perbaikan',
            '/sarpras/kategori',
            '/sarpras/supplier',
            '/sarpras/teknisi',
            '/sarpras/mutasi',
            '/sarpras/penghapusan',
            '/sarpras/laporan',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }
}