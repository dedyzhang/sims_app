<?php

namespace App\Sarpras\Exports;

use App\Sarpras\Models\Aset;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class KibExport implements FromArray, WithTitle
{
    public function __construct(private readonly Aset $aset) {}

    public function array(): array
    {
        $a = $this->aset->load(['kategori:id,nama', 'ruangan:id,kode,nama']);

        return [
            ['KARTU INVENTARIS BARANG (KIB)'],
            [],
            ['Kode Barang', $a->kode],
            ['Nama Barang', $a->nama],
            ['Kategori', $a->kategori?->nama],
            ['Merk / Spesifikasi', trim(($a->merk ?? '') . ' ' . json_encode($a->spesifikasi ?? []))],
            ['Tahun Perolehan', optional($a->tgl_perolehan)->format('Y')],
            ['Nilai Perolehan (Rp)', (int) $a->nilai_perolehan],
            ['Kondisi', $a->kondisi],
            ['Lokasi / Ruang', $a->ruangan?->kode . ' ' . $a->ruangan?->nama],
            ['Sumber Dana', $a->sumber_dana],
            ['Masa Manfaat (tahun)', $a->masa_manfaat_tahun],
        ];
    }

    public function title(): string
    {
        return 'KIB';
    }
}
