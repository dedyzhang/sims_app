<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class EventPeserta extends Model
{
    use HasUuids;

    protected $table = 'event_pesertas';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'biodata' => 'array',
            'waktu_hadir' => 'datetime',
        ];
    }

    public function kegiatan()
    {
        return $this->belongsTo(EventKegiatan::class, 'event_kegiatan_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
