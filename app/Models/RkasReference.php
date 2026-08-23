<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RkasReference extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'rkas_references';

    protected $fillable = [
        'reference_set_uuid', 'kode_kegiatan', 'snp', 'komponen', 'uraian_kegiatan',
        'kode_rekening_belanja', 'metadata',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function referenceSet()
    {
        return $this->belongsTo(RkasReferenceSet::class, 'reference_set_uuid', 'uuid');
    }

    public function items()
    {
        return $this->hasMany(RkasItem::class, 'reference_uuid', 'uuid');
    }
}
