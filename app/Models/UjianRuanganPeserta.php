<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Roster siswa (lintas kelas) yg duduk di satu UjianRuangan. */
class UjianRuanganPeserta extends Model
{
    use HasUuids;

    protected $table = 'ujian_ruangan_peserta';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ruangan', 'id_siswa', 'nomor_urut'];

    protected function casts(): array
    {
        return ['nomor_urut' => 'integer'];
    }

    public function ruangan()
    {
        return $this->belongsTo(UjianRuangan::class, 'id_ruangan', 'uuid');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }
}
