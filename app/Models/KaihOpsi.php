<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KaihOpsi extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'kaih_opsi';
    protected $fillable = ['id_pertanyaan', 'label', 'bobot', 'urutan'];

    public function pertanyaan()
    {
        return $this->belongsTo(KaihPertanyaan::class, 'id_pertanyaan', 'uuid');
    }
}
