<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EkskulSiswa extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ekskul_siswa';
    protected $primaryKey = 'uuid';
    protected $fillable = ['id_ekskul', 'id_siswa', 'id_semester', 'deskripsi'];

    public function ekskul()
    {
        return $this->belongsTo(Ekskul::class, 'id_ekskul', 'uuid');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }
}
