<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TujuanPembelajaran extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'tujuan_pembelajaran';
    protected $primaryKey = 'uuid';
    protected $fillable = ['id_materi', 'tupe', 'urutan', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function materi()
    {
        return $this->belongsTo(Materi::class, 'id_materi', 'uuid');
    }
}
