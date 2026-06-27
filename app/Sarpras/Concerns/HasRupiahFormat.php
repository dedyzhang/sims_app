<?php

namespace App\Sarpras\Concerns;

use App\Sarpras\Support\Rupiah;

/*
| Trait pembantu format Rupiah untuk model.
| Definisikan properti $rupiahFields pada model, lalu panggil
| $model->rupiah('nilai_perolehan') untuk dapat string "Rp ...".
*/
trait HasRupiahFormat
{
    /** Format satu kolom uang integer menjadi "Rp 1.500.000". */
    public function rupiah(string $field): string
    {
        return Rupiah::format($this->{$field} ?? 0);
    }
}
