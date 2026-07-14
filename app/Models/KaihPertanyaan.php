<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KaihPertanyaan extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'kaih_pertanyaan';
    protected $fillable = ['urutan', 'kebiasaan', 'pertanyaan', 'aktif'];

    protected $casts = ['aktif' => 'boolean'];

    public function opsi()
    {
        return $this->hasMany(KaihOpsi::class, 'id_pertanyaan', 'uuid')->orderBy('urutan');
    }

    public function jawabanDetail()
    {
        return $this->hasMany(KaihJawabanDetail::class, 'id_pertanyaan', 'uuid');
    }
}
