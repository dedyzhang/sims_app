<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * "Folder" pengelompokan beberapa Ujian sekaligus jadi satu periode formal
 * (mis. "PAS Semester 1 2026/2027") — menaungi Ruangan, Jadwal, dan ujian-
 * ujian anggotanya (satu Ujian per mapel, tetap berdiri sendiri strukturnya).
 */
class UjianPaket extends Model
{
    use HasUuids;

    protected $table = 'ujian_paket';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'nama', 'jenis', 'id_semester', 'tanggal_mulai', 'tanggal_selesai', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai'   => 'date',
            'tanggal_selesai' => 'date',
        ];
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class, 'id_semester', 'id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function ujian()
    {
        return $this->hasMany(Ujian::class, 'id_ujian_paket', 'uuid');
    }

    public function ruangan()
    {
        return $this->hasMany(UjianRuangan::class, 'id_ujian_paket', 'uuid');
    }

    public function jadwal()
    {
        return $this->hasMany(UjianJadwal::class, 'id_ujian_paket', 'uuid');
    }

    public function jenisLabel(): string
    {
        return match ($this->jenis) {
            'harian' => 'Ulangan Harian',
            'pts'    => 'PTS',
            'pas'    => 'PAS',
            'uas'    => 'UAS',
            default  => $this->jenis,
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'berjalan' => 'Berjalan',
            'selesai'  => 'Selesai',
            default    => 'Draf',
        };
    }
}
