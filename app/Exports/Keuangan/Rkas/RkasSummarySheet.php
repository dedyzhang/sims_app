<?php

namespace App\Exports\Keuangan\Rkas;

use App\Exports\Keuangan\Rkas\Concerns\EscapesSpreadsheetText;
use App\Models\RkasPlan;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class RkasSummarySheet implements FromArray, WithTitle
{
    use EscapesSpreadsheetText;

    public function __construct(private RkasPlan $plan) {}

    public function title(): string
    {
        return 'Rekap';
    }

    public function array(): array
    {
        $rows = [['Komponen', 'Total (Rp)']];
        $totals = [];
        foreach ($this->plan->items as $item) {
            $key = $item->komponen ?: 'Tanpa komponen';
            $totals[$key] = ($totals[$key] ?? 0) + (int) $item->total;
        }
        foreach ($totals as $component => $total) {
            $rows[] = [$this->safeText($component), $total];
        }
        $rows[] = ['Total direncanakan', $this->plan->totalPlanned()];
        $rows[] = ['Pagu sumber dana', (int) $this->plan->pagu];
        $rows[] = ['Sisa pagu', max(0, (int) $this->plan->pagu - $this->plan->totalPlanned())];

        return $rows;
    }
}
