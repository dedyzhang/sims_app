<?php

namespace App\Sarpras\Exports;

use App\Sarpras\Models\MutasiAset;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MutasiExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private ?string $dari = null, private ?string $sampai = null)
    {
    }

    public function collection()
    {
        return MutasiAset::with(['aset:id,kode,nama', 'ruanganAsal:id,kode', 'ruanganTujuan:id,kode'])
            ->when($this->dari, fn ($q, $v) => $q->whereDate('tgl_mutasi', '>=', $v))
            ->when($this->sampai, fn ($q, $v) => $q->whereDate('tgl_mutasi', '<=', $v))
            ->orderBy('tgl_mutasi')->get();
    }

    public function headings(): array
    {
        return ['Tanggal', 'Kode Aset', 'Nama Aset', 'Ruangan Asal', 'Ruangan Tujuan', 'Alasan'];
    }

    public function map($m): array
    {
        return [
            optional($m->tgl_mutasi)->format('Y-m-d'),
            $m->aset?->kode,
            $m->aset?->nama,
            $m->ruanganAsal?->kode,
            $m->ruanganTujuan?->kode,
            $m->alasan,
        ];
    }
}
