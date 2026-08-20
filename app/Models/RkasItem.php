<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RkasItem extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'rkas_items';

    protected $fillable = [
        'plan_uuid', 'reference_uuid', 'kode_kegiatan', 'komponen', 'penjelasan_implementasi',
        'uraian_belanja', 'bulan_dianggarkan', 'jumlah', 'satuan', 'harga_satuan', 'total',
        'kode_rekening_belanja',
    ];

    protected function casts(): array
    {
        return [
            'bulan_dianggarkan' => 'integer',
            'jumlah' => 'integer',
            'harga_satuan' => 'integer',
            'total' => 'integer',
        ];
    }

    public function plan()
    {
        return $this->belongsTo(RkasPlan::class, 'plan_uuid', 'uuid');
    }

    public function reference()
    {
        return $this->belongsTo(RkasReference::class, 'reference_uuid', 'uuid');
    }
}
