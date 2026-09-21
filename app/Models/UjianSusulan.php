<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class UjianSusulan extends Model
{
    use HasUuids;

    protected $table = 'ujian_susulans';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ujian', 'id_siswa', 'tanggal'];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function ujian()
    {
        return $this->belongsTo(Ujian::class, 'id_ujian', 'uuid');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }
}
