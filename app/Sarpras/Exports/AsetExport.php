<?php

namespace App\Sarpras\Exports;

use App\Sarpras\Models\Aset;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class AsetExport implements FromCollection, WithHeadings, WithMapping
{
    public function collection()
    {
        // Sudah ter-scope tenant via global scope BelongsToSchool.
        return Aset::with(['kategori:id,nama', 'ruangan:id,kode'])->orderBy('kode')->get();
    }

    public function headings(): array
    {
        return ['Kode', 'Nama', 'Kategori', 'Ruangan', 'Kondisi', 'Status', 'Tgl Perolehan', 'Nilai Perolehan (Rp)'];
    }

    public function map($aset): array
    {
        return [
            $aset->kode,
            $aset->nama,
            $aset->kategori?->nama,
            $aset->ruangan?->kode,
            $aset->kondisi,
            $aset->status,
            optional($aset->tgl_perolehan)->format('Y-m-d'),
            (int) $aset->nilai_perolehan,
        ];
    }
}
