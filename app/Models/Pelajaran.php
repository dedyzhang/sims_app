<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pelajaran extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $fillable = ['nama', 'kode', 'urutan', 'jp', 'kkm'];

    public function ngajars()
    {
        return $this->hasMany(Ngajar::class, 'id_pelajaran', 'uuid');
    }

    public function penjabaranKomponen()
    {
        return $this->hasMany(PenjabaranKomponen::class, 'id_pelajaran', 'uuid')->orderBy('urutan');
    }
}
