<?php

namespace App\Sarpras\Services;

use App\Sarpras\Models\SarprasActivityLog;
use Illuminate\Database\Eloquent\Model;

class SarprasActivityLogger
{
    public static function log(string $aksi, ?Model $subjek = null, ?array $meta = null): void
    {
        SarprasActivityLog::create([
            'aksi' => $aksi,
            'subjek_tipe' => $subjek ? $subjek::class : null,
            'subjek_id' => $subjek?->getKey(),
            'pelaku_id' => auth()->id(),
            'meta' => $meta,
        ]);
    }
}
