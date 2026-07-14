<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rapat extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'rapats';
    protected $fillable = ['judul', 'tanggal', 'pokok_permasalahan', 'hasil_rapat', 'id_pencatat'];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function pencatat()
    {
        return $this->belongsTo(Guru::class, 'id_pencatat', 'uuid');
    }

    public function guruHadir()
    {
        return $this->belongsToMany(Guru::class, 'rapat_hadir', 'id_rapat', 'id_guru', 'uuid', 'uuid')->withTimestamps();
    }

    public function dokumentasi()
    {
        return $this->hasMany(RapatDokumentasi::class, 'id_rapat', 'uuid')->orderBy('sort_order');
    }
}
