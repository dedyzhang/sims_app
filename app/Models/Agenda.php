<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Agenda extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'agendas';
    protected $fillable = [
        'tanggal', 'id_jadwal', 'id_guru', 'id_kelas', 'id_pelajaran',
        'pembahasan', 'metode', 'proses', 'kegiatan', 'kendala',
        'validasi', 'catatan_kepsek', 'semester',
    ];

    protected $casts = ['tanggal' => 'date:Y-m-d'];

    /** Label dimensi Profil Pelajar Pancasila (1..6). */
    public const DIMENSI = [
        1 => 'Beriman, bertakwa kepada Tuhan YME, dan berakhlak mulia',
        2 => 'Mandiri',
        3 => 'Bergotong-royong',
        4 => 'Berkebhinekaan global',
        5 => 'Bernalar Kritis',
        6 => 'Kreatif',
    ];

    public const ABSENSI = ['S' => 'Sakit', 'I' => 'Izin', 'A' => 'Alpha'];

    public function absensi()
    {
        return $this->hasMany(AgendaAbsensi::class, 'id_agenda', 'uuid');
    }

    public function pancasila()
    {
        return $this->hasMany(AgendaPancasila::class, 'id_agenda', 'uuid');
    }

    public function jadwal()
    {
        return $this->belongsTo(Jadwal::class, 'id_jadwal', 'uuid');
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'id_guru', 'uuid');
    }

    public function kelas()
    {
        return $this->belongsTo(Kelas::class, 'id_kelas', 'uuid');
    }

    public function pelajaran()
    {
        return $this->belongsTo(Pelajaran::class, 'id_pelajaran', 'uuid');
    }
}
