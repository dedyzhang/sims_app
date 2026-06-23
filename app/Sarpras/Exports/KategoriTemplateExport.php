<?php

namespace App\Sarpras\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Template kosong untuk import kategori aset.
 *
 * Header HARUS sama persis dengan kunci yang dibaca KategoriImport (WithHeadingRow).
 * Disertai 3 baris contoh (termasuk relasi induk) agar pengguna paham formatnya.
 */
class KategoriTemplateExport implements FromCollection, WithHeadings
{
    public function headings(): array
    {
        return ['kode', 'nama', 'induk', 'deskripsi'];
    }

    public function collection(): Collection
    {
        // Baris contoh (boleh dihapus sebelum import).
        // "Komputer" memakai induk "Elektronik" (rujuk via nama).
        return collect([
            ['ELK', 'Elektronik', '', 'Kategori induk perangkat elektronik'],
            ['ELK-PC', 'Komputer', 'Elektronik', 'Sub-kategori dari Elektronik'],
            ['MBL', 'Mebel', '', 'Meja, kursi, lemari'],
        ]);
    }
}
