<?php

namespace App\Exports\Keuangan\Rkas;

use App\Models\RkasPlan;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class RkasPlanExport implements WithMultipleSheets
{
    public function __construct(private RkasPlan $plan) {}

    public function sheets(): array
    {
        $this->plan->load(['referenceSet', 'items.reference']);

        return [
            new RkasMetadataSheet($this->plan),
            new RkasWorksheetSheet($this->plan),
            new RkasSummarySheet($this->plan),
            new RkasValidationSheet($this->plan),
            new RkasChecklistSheet($this->plan),
        ];
    }
}
