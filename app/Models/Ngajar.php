<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ngajar extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $fillable = ['id_guru', 'id_pelajaran', 'id_kelas', 'kkm'];

    /** KKTP efektif: kkm penugasan → kkm mapel → 75. */
    public function getKktpAttribute(): int
    {
        return (int) ($this->kkm ?? $this->pelajaran?->kkm ?? 75);
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'id_guru', 'uuid');
    }

    public function pelajaran()
    {
        return $this->belongsTo(Pelajaran::class, 'id_pelajaran', 'uuid');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'uuid');
    }
}
