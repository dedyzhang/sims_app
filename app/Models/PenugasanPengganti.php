<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PenugasanPengganti extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'penugasan_pengganti';
    protected $fillable = [
        'id_guru_tidak_hadir', 'id_jadwal', 'id_guru_pengganti', 'id_guru_piket', 'status',
    ];

    public const STATUS = [
        'menunggu' => 'Menunggu',
        'ditugaskan' => 'Ditugaskan',
        'selesai' => 'Selesai',
    ];

    public function guruTidakHadir()
    {
        return $this->belongsTo(GuruTidakHadir::class, 'id_guru_tidak_hadir', 'uuid');
    }

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal', 'uuid');
    }

    public function guruPengganti()
    {
        return $this->belongsTo(Guru::class, 'id_guru_pengganti', 'uuid');
    }

    public function guruPiket()
    {
        return $this->belongsTo(Guru::class, 'id_guru_piket', 'uuid');
    }

    public function tugasKelas()
    {
        return $this->hasOne(TugasKelas::class, 'id_penugasan_pengganti', 'uuid');
    }

    /** Nama guru yang benar-benar mengisi jam ini (pengganti atau piket sendiri). */
    public function getGuruPengisiAttribute(): ?string
    {
        return $this->guruPengganti?->nama ?? $this->guruPiket?->nama;
    }
}
