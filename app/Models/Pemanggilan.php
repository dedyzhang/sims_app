<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Pemanggilan extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'pemanggilan';

    protected $fillable = [
        'id_siswa', 'tanggal', 'dipanggil', 'perihal', 'permasalahan', 'hasil', 'id_pencatat',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public const DIPANGGIL = [
        'siswa'    => 'Siswa',
        'orangtua' => 'Orang Tua',
        'keduanya' => 'Siswa & Orang Tua',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }

    public function pencatat()
    {
        return $this->belongsTo(User::class, 'id_pencatat', 'uuid');
    }

    public function dokumentasi()
    {
        return $this->hasMany(PemanggilanDokumentasi::class, 'id_pemanggilan', 'uuid')->orderBy('sort_order');
    }
}
