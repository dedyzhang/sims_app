<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

class RkasReferenceImport implements ToCollection
{
    public function __construct(public ?Collection $rows = null) {}

    public function collection(Collection $rows): void
    {
        $this->rows = $rows;
    }
}
