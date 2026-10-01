<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventKegiatan extends Model
{
    use HasUuids;

    protected $table = 'event_kegiatans';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'form_fields' => 'array',
        ];
    }

    public function pesertas()
    {
        return $this->hasMany(EventPeserta::class);
    }
}
