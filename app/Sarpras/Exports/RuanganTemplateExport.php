<?php

namespace App\Sarpras\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template kosong untuk import data ruangan ke sebuah denah.
 *
 * Header HARUS sama persis dengan kunci yang dibaca RuanganImport (WithHeadingRow).
 * Disertai 2 baris contoh agar pengguna paham format isian.
 */
class RuanganTemplateExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return ['kode', 'nama', 'kapasitas', 'warna', 'deskripsi'];
    }

    public function collection(): Collection
    {
        // Baris contoh (boleh dihapus sebelum import).
        return collect([
            ['7A', 'Kelas 7A', '32', '#059669', 'Ruang kelas reguler'],
            ['LAB-IPA', 'Laboratorium IPA', '36', '#2563eb', 'Lab praktikum IPA'],
        ]);
    }
}
