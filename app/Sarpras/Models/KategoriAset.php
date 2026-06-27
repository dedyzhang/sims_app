<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriAset extends SarprasModel
{
    protected $table = 'sarpras_kategori_aset';

    protected $fillable = ['school_id', 'kode', 'nama', 'parent_id', 'deskripsi'];

    /** Kategori induk (hierarki opsional). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function aset(): HasMany
    {
        return $this->hasMany(Aset::class, 'kategori_id');
    }
}
