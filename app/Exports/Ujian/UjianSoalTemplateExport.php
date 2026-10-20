<?php

namespace App\Exports\Ujian;

use App\Exports\Ujian\Sheets\UjianSoalDataSheet;
use App\Exports\Ujian\Sheets\UjianSoalPanduanSheet;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UjianSoalTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new UjianSoalDataSheet(null), // null = template dengan baris-baris contoh
            new UjianSoalPanduanSheet(),
        ];
    }
}
