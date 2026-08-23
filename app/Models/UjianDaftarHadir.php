<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Daftar hadir resmi per siswa per SESI (bukan per hari — siswa bisa beda status tiap sesi), diisi manual/via scan oleh pengawas ruangan. */
class UjianDaftarHadir extends Model
{
    use HasUuids;

    protected $table = 'ujian_daftar_hadir';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ruangan', 'id_siswa', 'id_sesi', 'tanggal', 'status', 'keterangan', 'dicatat_oleh', 'dicatat_pada'];

    protected function casts(): array
    {
        // 'date:Y-m-d' — simpanSesi() di UjianRuanganMonitorController mencocokkan 'tanggal'
        // via updateOrCreate dgn string mentah "Y-m-d"; cast 'date' polos serialize dgn jam
        // ("Y-m-d H:i:s"), bikin pencocokan hari berikutnya selalu meleset (jadi bikin baris baru).
        return ['tanggal' => 'date:Y-m-d', 'dicatat_pada' => 'datetime'];
    }

    public function ruangan()
    {
        return $this->belongsTo(UjianRuangan::class, 'id_ruangan', 'uuid');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }

    public function pencatat()
    {
        return $this->belongsTo(User::class, 'dicatat_oleh', 'uuid');
    }

    public function sesi()
    {
        return $this->belongsTo(UjianSesi::class, 'id_sesi', 'uuid');
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'izin' => 'Izin',
            'sakit' => 'Sakit',
            'alpa' => 'Alpa',
            default => 'Hadir',
        };
    }
}
