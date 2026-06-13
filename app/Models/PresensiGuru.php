<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PresensiGuru extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'presensi_gurus';
    protected $fillable = [
        'id_guru', 'tanggal', 'jam_masuk', 'jam_pulang', 'status', 'keterangan', 'dicatat_oleh',
    ];

    protected $casts = ['tanggal' => 'date:Y-m-d'];

    public const STATUS = [
        'hadir' => 'Hadir',
        'izin'  => 'Izin',
        'sakit' => 'Sakit',
        'alpa'  => 'Alpa',
    ];

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'id_guru', 'uuid');
    }

    /** True bila hadir & jam_masuk melewati batas (HH:MM). */
    public function terlambat(string $batas): bool
    {
        return $this->status === 'hadir'
            && $this->jam_masuk
            && substr($this->jam_masuk, 0, 5) > $batas;
    }
}
