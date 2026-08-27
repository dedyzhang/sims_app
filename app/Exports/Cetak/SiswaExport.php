<?php

namespace App\Exports\Cetak;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SiswaExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private string $idKelas)
    {
    }

    public function sheets(): array
    {
        return [
            new SiswaDataSheet($this->idKelas),
            new SiswaStatistikSheet($this->idKelas),
        ];
    }
}
