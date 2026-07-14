<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KaihJawabanDetail extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'kaih_jawaban_detail';
    protected $fillable = ['id_jawaban', 'id_pertanyaan', 'id_opsi', 'bobot'];

    public function jawaban()
    {
        return $this->belongsTo(KaihJawaban::class, 'id_jawaban', 'uuid');
    }

    public function pertanyaan()
    {
        return $this->belongsTo(KaihPertanyaan::class, 'id_pertanyaan', 'uuid');
    }

    public function opsi()
    {
        return $this->belongsTo(KaihOpsi::class, 'id_opsi', 'uuid');
    }
}
