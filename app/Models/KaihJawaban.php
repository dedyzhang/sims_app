<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class KaihJawaban extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'kaih_jawaban';
    protected $fillable = ['id_siswa', 'tanggal', 'total_skor', 'refleksi', 'status', 'diisi_oleh', 'keterangan'];

    protected $casts = ['tanggal' => 'date'];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }

    public function detail()
    {
        return $this->hasMany(KaihJawabanDetail::class, 'id_jawaban', 'uuid');
    }

    public function diisiOleh()
    {
        return $this->belongsTo(User::class, 'diisi_oleh', 'uuid');
    }
}
