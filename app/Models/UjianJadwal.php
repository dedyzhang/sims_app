<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Jadwal hari×jam per-mapel dlm satu UjianPaket — menentukan kapan SATU Ujian
 * (sudah scoped ke mapel tertentu) terbuka. Disimpan/dihapus lewat
 * UjianJadwalController, yg memanggil UjianJadwalSync::terapkan() utk mengisi
 * ujian_kelas.dibuka_mulai/dibuka_sampai milik Ujian itu — kolom yg SUDAH ADA
 * & sudah dibaca UjianPolicy::take()/UjianKelas::isOpenNow(), tapi selama ini
 * tak pernah diisi controller manapun.
 */
class UjianJadwal extends Model
{
    use HasUuids;

    protected $table = 'ujian_jadwal';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ujian_paket', 'id_ujian', 'id_sesi', 'tanggal', 'jam_mulai', 'jam_selesai', 'sesi_label'];

    protected function casts(): array
    {
        return ['tanggal' => 'date'];
    }

    public function paket()
    {
        return $this->belongsTo(UjianPaket::class, 'id_ujian_paket', 'uuid');
    }

    public function ujian()
    {
        return $this->belongsTo(Ujian::class, 'id_ujian', 'uuid');
    }

    public function sesi()
    {
        return $this->belongsTo(UjianSesi::class, 'id_sesi', 'uuid');
    }
}
