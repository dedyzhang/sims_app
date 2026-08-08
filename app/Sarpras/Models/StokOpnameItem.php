<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokOpnameItem extends SarprasModel
{
    protected $table = 'sarpras_stok_opname_item';

    protected $fillable = [
        'school_id', 'opname_id', 'aset_id', 'kondisi_sistem', 'kondisi_fisik', 'catatan',
    ];

    public function opname(): BelongsTo
    {
        return $this->belongsTo(StokOpname::class, 'opname_id');
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }
}
