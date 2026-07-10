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
use App\Sarpras\Models\Peminjaman;
use App\Sarpras\Models\Perbaikan;
use App\Sarpras\Models\Pengadaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarprasDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sarpras_menampilkan_ringkasan_operasional(): void
    {
        Carbon::setTestNow('2026-07-09 08:00:00');

        $admin = User::create([
            'username' => 'sap_dashboard',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);

        $kategori = KategoriAset::create(['kode' => 'ELK', 'nama' => 'Elektronik']);
        $denah = Denah::create(['nama' => 'Gedung A - Lantai 1']);
        $ruangan = DenahRuangan::create([
            'denah_id' => $denah->id,
            'kode' => 'LAB-1',
            'nama' => 'Lab Komputer',
            'status' => 'tersedia',
            'kapasitas' => 32,
        ]);

        $aset = Aset::create([
            'kode' => 'AST-DASH-001',
            'nama' => 'Proyektor Lab',
            'kategori_id' => $kategori->id,
            'ruangan_id' => $ruangan->id,
            'kondisi' => 'rusak_ringan',
            'status' => 'perbaikan',
            'tgl_perolehan' => '2024-07-01',
            'nilai_perolehan' => 3000000,
            'masa_manfaat_tahun' => 5,
        ]);

        LaporanKerusakan::create([
            'kode' => 'KR-DASH-001',
            'aset_id' => $aset->id,
            'ruangan_id' => $ruangan->id,
            'pelapor_id' => $admin->uuid,
            'deskripsi' => 'Proyektor tidak menyala',
            'urgensi' => 'darurat',
            'status' => 'dilaporkan',
        ]);

        Peminjaman::create([
            'kode' => 'PJM-DASH-001',
            'peminjam_id' => $admin->uuid,
            'keperluan' => 'Kegiatan kelas',
            'tgl_pinjam' => '2026-07-09',
            'tgl_kembali_rencana' => '2026-07-10',
            'status' => 'diajukan',
        ]);

        BookingRuangan::create([
            'ruangan_id' => $ruangan->id,
            'pemohon_id' => $admin->uuid,
            'keperluan' => 'Rapat sarpras',
            'mulai' => Carbon::parse('2026-07-09 10:00'),
            'selesai' => Carbon::parse('2026-07-09 11:00'),
            'status' => 'diajukan',
        ]);

        Pengadaan::create([
            'kode' => 'PGD-DASH-001',
            'judul' => 'Pengadaan LCD',
            'diajukan_oleh' => $admin->uuid,
            'status' => 'diajukan',
            'total_estimasi' => 4500000,
        ]);

        Perbaikan::create([
            'kode' => 'PRB-DASH-001',
            'aset_id' => $aset->id,
            'deskripsi' => 'Servis proyektor',
            'status' => 'dikerjakan',
            'biaya' => 250000,
            'tgl_mulai' => '2026-07-09',
        ]);

        JadwalPemeliharaan::create([
            'aset_id' => $aset->id,
            'nama' => 'Cek proyektor bulanan',
            'interval_hari' => 30,
            'tgl_berikutnya' => '2026-07-09',
            'aktif' => true,
        ]);

        $this->actingAs($admin)
            ->get('/sarpras')
            ->assertOk()
            ->assertSee('Dashboard Sarana &amp; Prasarana', false)
            ->assertSee('Antrean Kerja Sarpras')
            ->assertSee('Aset Perlu Tindakan')
            ->assertSee('Pemeliharaan 14 Hari')
            ->assertSee('Proyektor Lab')
            ->assertSee('Pengadaan LCD')
            ->assertSee('Rapat sarpras');
    }
}