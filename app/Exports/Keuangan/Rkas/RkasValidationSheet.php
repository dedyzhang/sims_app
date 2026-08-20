<?php

namespace App\Exports\Keuangan\Rkas;

use App\Exports\Keuangan\Rkas\Concerns\EscapesSpreadsheetText;
use App\Models\RkasPlan;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class RkasValidationSheet implements FromCollection, WithHeadings, WithMapping, WithTitle
{
    use EscapesSpreadsheetText;

    public function __construct(private RkasPlan $plan) {}

    public function title(): string
    {
        return 'Validasi';
    }

    public function collection(): Collection
    {
        return $this->plan->validations()->with('item')->latest()->get();
    }

    public function headings(): array
    {
        return ['Severity', 'Kode', 'Pesan', 'Kode Kegiatan', 'Detail'];
    }

    public function map($validation): array
    {
        return [
            $this->safeText(strtoupper($validation->severity)),
            $this->safeText($validation->kode),
            $this->safeText($validation->message),
            $this->safeText($validation->item?->kode_kegiatan ?? ''),
            $this->safeText($validation->details ? json_encode($validation->details, JSON_UNESCAPED_UNICODE) : ''),
        ];
    }
}
