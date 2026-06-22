<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NilaiRapor extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'nilai_rapor';
    protected $primaryKey = 'uuid';
    protected $fillable = ['id_ngajar', 'id_siswa', 'id_semester', 'nilai', 'deskripsi_positif', 'deskripsi_negatif'];

    protected function casts(): array
    {
        return ['nilai' => 'integer'];
    }
}
