<?php

namespace App\Exports\Keuangan\Rkas;

use App\Exports\Keuangan\Rkas\Concerns\EscapesSpreadsheetText;
use App\Models\RkasPlan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class RkasWorksheetSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    use EscapesSpreadsheetText;

    public function __construct(private RkasPlan $plan) {}

    public function title(): string
    {
        return 'Kertas Kerja ARKAS';
    }

    public function collection(): Collection
    {
        return $this->plan->items;
    }

    public function headings(): array
    {
        return ['No', 'Kode Kegiatan', 'SNP', 'Komponen', 'Penjelasan Implementasi', 'Uraian Belanja', 'Bulan', 'Jumlah', 'Satuan', 'Harga Satuan (Rp)', 'Total (Rp)', 'Kode Rekening Belanja'];
    }

    public function map($item): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $this->safeText($item->kode_kegiatan),
            $this->safeText($item->reference?->snp ?? ''),
            $this->safeText($item->komponen ?? ''),
            $this->safeText($item->penjelasan_implementasi ?? ''),
            $this->safeText($item->uraian_belanja),
            $item->bulan_dianggarkan,
            $item->jumlah,
            $this->safeText($item->satuan),
            $item->harga_satuan,
            $item->total,
            $this->safeText($item->kode_rekening_belanja ?? ''),
        ];
    }
}
