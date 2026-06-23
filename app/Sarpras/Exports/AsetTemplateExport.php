<?php

namespace App\Sarpras\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template kosong untuk import katalog aset.
 *
 * Header HARUS sama persis dengan kunci yang dibaca AsetImport (WithHeadingRow).
 * Disertai 2 baris contoh agar pengguna paham format isian.
 */
class AsetTemplateExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return [
            'kode', 'nama', 'kategori', 'ruangan', 'merk',
            'kondisi', 'status', 'tgl_perolehan', 'nilai_perolehan', 'sumber_dana',
        ];
    }

    public function collection(): Collection
    {
        // Baris contoh (boleh dihapus sebelum import).
        return collect([
            ['AST-001', 'Laptop Asus X441', 'Elektronik', 'LAB-IPA', 'Asus', 'baik', 'aktif', '2025-01-15', '6500000', 'BOS'],
            ['AST-002', 'Kursi Lipat', 'Mebel', '7A', '', 'rusak_ringan', 'aktif', '2024-08-01', '150000', 'Komite'],
        ]);
    }
}
