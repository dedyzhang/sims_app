<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu sitting fisik ujian di suatu ruangan pada suatu tanggal — bisa menaungi
 * BEBERAPA baris UjianJadwal (mapel) sekaligus kalau jamnya sama & admin kasih
 * label yg sama (mis. Pendidikan Agama & Pendidikan Pancasila sama-sama
 * 08:00-16:00, dibedakan dari sesi lain lewat sesi_label "1"/"2"). Entitas
 * sungguhan (bukan derivasi ad-hoc dari sesi_label+tanggal tiap request) supaya
 * Berita Acara & Daftar Hadir per sesi py FK yg stabil thd edit jadwal.
 */
class UjianSesi extends Model
{
    use HasUuids;

    protected $table = 'ujian_sesi';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_ujian_paket', 'tanggal', 'jam_mulai', 'jam_selesai', 'label'];

    protected function casts(): array
    {
        // 'date:Y-m-d' (bukan 'date' polos) — firstOrCreate() di UjianJadwalController
        // mencocokkan tanggal sbg exact-string, sama alasannya dgn cast yg sama di
        // UjianBeritaAcara/UjianDaftarHadir.
        return ['tanggal' => 'date:Y-m-d'];
    }

    public function paket()
    {
        return $this->belongsTo(UjianPaket::class, 'id_ujian_paket', 'uuid');
    }

    public function jadwal()
    {
        return $this->hasMany(UjianJadwal::class, 'id_sesi', 'uuid');
    }

    public function beritaAcara()
    {
        return $this->hasMany(UjianBeritaAcara::class, 'id_sesi', 'uuid');
    }

    public function daftarHadir()
    {
        return $this->hasMany(UjianDaftarHadir::class, 'id_sesi', 'uuid');
    }

    /** Nama mapel gabungan (koma-separated) — dipakai di kartu monitor & PDF cetak. */
    public function mapelNama(): string
    {
        return $this->jadwal->pluck('ujian.pelajaran.nama')->filter()->unique()->implode(', ');
    }
}
