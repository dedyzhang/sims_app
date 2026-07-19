<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'absensis';
    protected $fillable = [
        'id_siswa', 'id_kelas', 'tanggal', 'jam_masuk', 'status', 'keterangan', 'dicatat_oleh',
        'geo_lat', 'geo_lng', 'geo_accuracy', 'geo_jarak',
    ];

    /** True bila hadir & jam_masuk melewati batas (HH:MM). */
    public function terlambat(string $batas): bool
    {
        return $this->status === 'hadir'
            && $this->jam_masuk
            && substr($this->jam_masuk, 0, 5) > $batas;
    }

    protected $casts = ['tanggal' => 'date:Y-m-d'];

    public const STATUS = [
        'hadir' => 'Hadir',
        'izin'  => 'Izin',
        'sakit' => 'Sakit',
        'alpa'  => 'Alpa',
    ];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'uuid');
    }
}
