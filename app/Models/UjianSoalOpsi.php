<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjianSoalOpsi extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ujian_soal_opsi';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_soal', 'teks_opsi', 'is_benar', 'urutan'];

    protected function casts(): array
    {
        return [
            'is_benar' => 'boolean',
            'urutan'   => 'integer',
        ];
    }

    public function soal()
    {
        return $this->belongsTo(UjianSoal::class, 'id_soal', 'uuid');
    }
}
