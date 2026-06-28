<?php

namespace Database\Seeders;

use App\Sarpras\Models\Aset;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\KategoriAset;
use App\Sarpras\Models\Teknisi;
use Illuminate\Database\Seeder;

/*
|==========================================================================
| SarprasSeeder — IDEMPOTEN (firstOrCreate). Aman dijalankan ulang.
|==========================================================================
| Versi terintegrasi SIMS:
| - TIDAK menyeed role/permission (otorisasi memakai Gate berbasis
|   users.access — lihat App\Sarpras\SarprasServiceProvider).
| - TIDAK membuat sekolah/user (SIMS single-tenant; user dikelola modul lain).
| - Hanya menambah DATA CONTOH master (kategori, denah, ruangan, aset,
|   teknisi). Kolom school_id terisi otomatis oleh SarprasModel.
|
| Untuk produksi, hapus/komentari pemanggilan seeder ini.
*/
class SarprasSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Kategori contoh.
        $kategoriNama = ['Elektronik', 'Mebel', 'Alat Laboratorium', 'Alat Olahraga', 'Kendaraan'];
        $kategori = [];
        foreach ($kategoriNama as $i => $nama) {
            $kategori[$nama] = KategoriAset::firstOrCreate(
                ['nama' => $nama],
                ['kode' => 'KAT-' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT)]
            );
        }

        // 2) Denah demo + ruangan (koordinat persen 0-100).
        $denah = Denah::firstOrCreate(
            ['nama' => 'Gedung A - Lantai 1'],
            ['gedung' => 'Gedung A', 'lantai' => '1', 'deskripsi' => 'Denah demo lantai 1']
        );

        $ruanganDemo = [
            ['7A', 'Kelas 7A', 20.0, 30.0, 32],
            ['7B', 'Kelas 7B', 45.0, 30.0, 32],
            ['8A', 'Kelas 8A', 70.0, 30.0, 32],
            ['LAB-IPA', 'Lab IPA', 20.0, 70.0, 36],
            ['PERPUS', 'Perpustakaan', 60.0, 70.0, 40],
        ];
        $ruangan = [];
        foreach ($ruanganDemo as [$kode, $nama, $x, $y, $kap]) {
            $ruangan[$kode] = DenahRuangan::firstOrCreate(
                ['denah_id' => $denah->id, 'kode' => $kode],
                [
                    'nama' => $nama,
                    'pos_x' => $x, 'pos_y' => $y, 'kapasitas' => $kap,
                    'deskripsi' => 'Ruangan demo ' . $nama,
                ]
            );
        }

        // 3) Aset contoh.
        $asetDemo = [
            ['AST-0001', 'Proyektor Epson', 'Elektronik', '7A', 'baik', 4500000],
            ['AST-0002', 'Kursi Siswa', 'Mebel', '7A', 'baik', 150000],
            ['AST-0003', 'Mikroskop', 'Alat Laboratorium', 'LAB-IPA', 'rusak_ringan', 2750000],
        ];
        foreach ($asetDemo as [$kode, $nama, $kat, $ruang, $kondisi, $nilai]) {
            Aset::firstOrCreate(
                ['kode' => $kode],
                [
                    'nama' => $nama,
                    'kategori_id' => $kategori[$kat]->id ?? null,
                    'ruangan_id' => $ruangan[$ruang]->id ?? null,
                    'kondisi' => $kondisi,
                    'status' => 'aktif',
                    'tgl_perolehan' => '2024-07-01',
                    'nilai_perolehan' => $nilai,
                    'sumber_dana' => 'BOS',
                ]
            );
        }

        // 4) Teknisi contoh.
        Teknisi::firstOrCreate(
            ['nama' => 'Pak Budi (Internal)'],
            ['tipe' => 'internal', 'spesialisasi' => 'Elektronik & Komputer', 'telepon' => '0812000111']
        );

        // 5) Ruang fasilitas yang dapat di-booking (Booking Ruangan).
        $denahFasilitas = Denah::firstOrCreate(
            ['nama' => 'Fasilitas Sekolah'],
            ['gedung' => 'Fasilitas', 'lantai' => '-', 'deskripsi' => 'Ruang/fasilitas yang dapat dibooking.']
        );

        $ruangFasilitas = [
            ['MM1', 'Ruang Multimedia 1', 40],
            ['RAPAT', 'Ruang Rapat', 25],
            ['PERPUS', 'Ruang Perpustakaan', 60],
            ['KOMP', 'Ruang Komputer', 40],
            ['LAB-BHS', 'Lab Bahasa', 36],
            ['LAB-IPA', 'Lab IPA', 36],
        ];
        foreach ($ruangFasilitas as [$kode, $nama, $kap]) {
            DenahRuangan::firstOrCreate(
                ['denah_id' => $denahFasilitas->id, 'kode' => $kode],
                ['nama' => $nama, 'kapasitas' => $kap, 'deskripsi' => $nama]
            );
        }

        $this->command?->info('SarprasSeeder selesai (data contoh master + ruang fasilitas).');
    }
}
