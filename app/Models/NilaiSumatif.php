<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NilaiSumatif extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'nilai_sumatif';
    protected $primaryKey = 'uuid';
    protected $fillable = ['id_materi', 'id_siswa', 'nilai'];

    protected function casts(): array
    {
        return ['nilai' => 'integer'];
    }
}
