<?php

namespace Tests\Feature;

use App\Models\User;
use App\Sarpras\Models\KategoriAset;
use App\Sarpras\Models\Pengadaan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SarprasPengadaanViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_pengadaan_render_dengan_teks_panjang(): void
    {
        $admin = User::create([
            'username' => 'sap_pengadaan_view',
            'password' => Hash::make('password'),
            'access' => 'superadmin',
        ]);

        $kategori = KategoriAset::create(['kode' => 'ELK', 'nama' => 'Elektronik Pembelajaran']);
        $judul = 'Pengadaan Barang Proyektor Interaktif Ruang Multimedia Dengan Nama Sangat Panjang Untuk Menguji Tampilan';

        $pengadaan = Pengadaan::create([
            'kode' => 'PGD-VIEW-001',
            'judul' => $judul,
            'deskripsi' => 'Deskripsi pengadaan yang panjang tetap harus turun baris dan tidak keluar dari area tampilan.',
            'diajukan_oleh' => $admin->uuid,
            'status' => 'diajukan',
            'total_estimasi' => 12500000,
        ]);

        $pengadaan->items()->create([
            'kategori_id' => $kategori->id,
            'nama_barang' => 'Paket Proyektor Interaktif Ultra Wide Dengan Bracket dan Kabel HDMI Panjang',
            'qty' => 2,
            'satuan' => 'unit',
            'estimasi_harga' => 6250000,
        ]);

        $this->actingAs($admin)
            ->get('/sarpras/pengadaan')
            ->assertOk()
            ->assertSee('Daftar Pengadaan Barang')
            ->assertSee($judul);

        $this->actingAs($admin)
            ->get('/sarpras/pengadaan-baru')
            ->assertOk()
            ->assertSee('Pengajuan Pengadaan Barang');

        $this->actingAs($admin)
            ->get('/sarpras/pengadaan/' . $pengadaan->id)
            ->assertOk()
            ->assertSee('Detail Pengadaan Barang')
            ->assertSee($judul)
            ->assertSee('Paket Proyektor Interaktif Ultra Wide');
    }
}