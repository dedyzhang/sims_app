<?php

namespace App\Sarpras\Exports;

use App\Sarpras\Models\DenahRuangan;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class KirExport implements FromArray, WithTitle
{
    public function __construct(private readonly DenahRuangan $ruangan) {}

    public function array(): array
    {
        $ruangan = $this->ruangan->load(['aset.kategori:id,nama', 'denah:id,nama,gedung,lantai']);
        $rows = [
            ['KARTU INVENTARIS RUANGAN (KIR)'],
            [],
            ['Kode Ruang', $ruangan->kode],
            ['Nama Ruang', $ruangan->nama],
            ['Gedung / Lantai', trim(($ruangan->denah?->gedung ?? '') . ' ' . ($ruangan->denah?->lantai ?? ''))],
            ['Kapasitas', $ruangan->kapasitas],
            [],
            ['No', 'Kode', 'Nama Barang', 'Kategori', 'Kondisi', 'Nilai (Rp)'],
        ];

        $no = 1;
        foreach ($ruangan->aset as $aset) {
            $rows[] = [
                $no++,
                $aset->kode,
                $aset->nama,
                $aset->kategori?->nama,
                $aset->kondisi,
                (int) $aset->nilai_perolehan,
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'KIR';
    }
}
