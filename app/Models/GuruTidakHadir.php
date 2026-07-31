<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GuruTidakHadir extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'guru_tidak_hadir';
    protected $fillable = [
        'id_guru', 'tanggal', 'sumber', 'alasan', 'keterangan', 'id_presensi_guru', 'dicatat_oleh',
    ];

    protected $casts = ['tanggal' => 'date:Y-m-d'];

    public const SUMBER = [
        'otomatis_presensi' => 'Otomatis dari Absensi PTK',
        'manual_piket' => 'Manual oleh Guru Piket',
        'lapor_mandiri' => 'Lapor Mandiri (Guru Asli)',
    ];

    public const ALASAN = [
        'sakit' => 'Sakit',
        'izin' => 'Izin',
        'dinas_luar' => 'Dinas Luar',
        'alpa' => 'Alpa',
    ];

    /** Peta status presensi_gurus -> alasan guru_tidak_hadir (hadir sengaja tidak dipetakan). */
    public const ALASAN_DARI_PRESENSI = [
        'izin' => 'izin',
        'sakit' => 'sakit',
        'alpa' => 'alpa',
    ];

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'id_guru', 'uuid');
    }

    public function presensi()
    {
        return $this->belongsTo(PresensiGuru::class, 'id_presensi_guru', 'uuid');
    }

    public function pencatat()
    {
        return $this->belongsTo(User::class, 'dicatat_oleh', 'uuid');
    }

    public function penugasanPengganti()
    {
        return $this->hasMany(PenugasanPengganti::class, 'id_guru_tidak_hadir', 'uuid');
    }
}
