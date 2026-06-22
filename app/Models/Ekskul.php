<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ekskul extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ekskul';
    protected $primaryKey = 'uuid';
    protected $fillable = ['nama', 'id_guru', 'id_pelajaran', 'urutan', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'id_guru', 'uuid');
    }

    public function pelajaran()
    {
        return $this->belongsTo(Pelajaran::class, 'id_pelajaran', 'uuid');
    }

    /** Apakah ekskul ini mengambil nilai dari mapel (bukan manual). */
    public function dariMapel(): bool
    {
        return !empty($this->id_pelajaran);
    }
}
