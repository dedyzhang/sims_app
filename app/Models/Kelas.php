<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Kelas extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $fillable = ['tingkat', 'kelas'];

    public function walikelas()
    {
        return $this->hasOne(Walikelas::class, 'id_kelas', 'uuid');
    }

    public function guru()
    {
        return $this->hasOneThrough(Guru::class, Walikelas::class, 'id_kelas', 'uuid', 'uuid', 'id_guru');
    }

    public function siswa()
    {
        return $this->hasMany(Siswa::class, 'id_kelas', 'uuid')->orderBy('nama');
    }

    public function getNamaLengkapAttribute(): string
    {
        return "Kelas {$this->tingkat} {$this->kelas}";
    }
}
