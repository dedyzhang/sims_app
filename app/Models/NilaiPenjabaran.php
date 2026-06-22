<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NilaiPenjabaran extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'nilai_penjabaran';
    protected $primaryKey = 'uuid';
    protected $fillable = ['id_ngajar', 'id_siswa', 'id_komponen', 'id_semester', 'nilai'];

    protected function casts(): array
    {
        return ['nilai' => 'integer'];
    }
}
