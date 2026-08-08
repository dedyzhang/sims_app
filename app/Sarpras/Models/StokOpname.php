<?php

namespace App\Sarpras\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StokOpname extends SarprasModel
{
    protected $table = 'sarpras_stok_opname';

    protected $fillable = [
        'school_id', 'periode', 'judul', 'status', 'dibuat_oleh', 'selesai_pada',
    ];

    protected $casts = [
        'selesai_pada' => 'datetime',
    ];

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StokOpnameItem::class, 'opname_id');
    }
}
