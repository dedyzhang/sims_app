<?php

namespace App\Exports\Ujian;

use App\Exports\Ujian\Sheets\UjianSoalDataSheet;
use App\Exports\Ujian\Sheets\UjianSoalPanduanSheet;
use App\Models\Ujian;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class UjianSoalExport implements WithMultipleSheets
{
    public function __construct(protected Ujian $ujian)
    {
    }

    public function sheets(): array
    {
        return [
            new UjianSoalDataSheet($this->ujian),
            new UjianSoalPanduanSheet(),
        ];
    }
}
