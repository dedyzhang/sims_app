<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Materi extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'materi';
    protected $primaryKey = 'uuid';
    protected $fillable = ['id_ngajar', 'nama', 'id_semester', 'urutan', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function tujuan()
    {
        return $this->hasMany(TujuanPembelajaran::class, 'id_materi', 'uuid')->orderBy('urutan');
    }

    public function ngajar()
    {
        return $this->belongsTo(Ngajar::class, 'id_ngajar', 'uuid');
    }
}
