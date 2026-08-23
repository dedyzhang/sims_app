<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Berita acara resmi — satu per (ruangan, SESI ujian). Satu sesi bisa mencakup
 * BEBERAPA mapel sekaligus (guru centang checkbox di modal) — daftar mapel yg
 * dicakup ada di relasi many-to-many `ujianList()`, dicetak sbg satu dokumen
 * gabungan (bukan banyak dokumen terpisah per mapel). Dokumen PERSISTEN, BUKAN
 * sekadar view cetak sekali pakai spt Rapat::cetak(). `id_guru_pengawas` otomatis
 * terisi dari siapa yg scan QR ruangan saat sesi itu aktif (lihat
 * UjianRuanganScanController::scan()), bisa dikoreksi manual lewat form.
 */
class UjianBeritaAcara extends Model
{
    use HasUuids;

    protected $table = 'ujian_berita_acara';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'id_ruangan', 'id_sesi', 'tanggal', 'jam_mulai_aktual', 'jam_selesai_aktual',
        'jumlah_hadir', 'jumlah_tidak_hadir', 'catatan_kejadian',
        'id_guru_pengawas', 'diisi_oleh', 'diisi_pada',
    ];

    protected function casts(): array
    {
        // 'date:Y-m-d' — simpanSesi() mencocokkan 'tanggal' via updateOrCreate dgn
        // string mentah "Y-m-d"; cast 'date' polos serialize dgn jam ("Y-m-d H:i:s"), bikin
        // pencocokan hari berikutnya selalu meleset (jadi bikin baris baru, bukan update).
        return [
            'tanggal' => 'date:Y-m-d',
            'jumlah_hadir' => 'integer',
            'jumlah_tidak_hadir' => 'integer',
            'diisi_pada' => 'datetime',
        ];
    }

    public function ruangan()
    {
        return $this->belongsTo(UjianRuangan::class, 'id_ruangan', 'uuid');
    }

    public function sesi()
    {
        return $this->belongsTo(UjianSesi::class, 'id_sesi', 'uuid');
    }

    public function ujianList()
    {
        return $this->belongsToMany(Ujian::class, 'ujian_berita_acara_ujian', 'id_berita_acara', 'id_ujian', 'uuid', 'uuid')
            ->using(UjianBeritaAcaraUjian::class)
            ->withTimestamps();
    }

    /** Nama mapel gabungan (koma-separated) — dipakai di PDF cetak "Mata Pelajaran". */
    public function mapelNama(): string
    {
        return $this->ujianList->pluck('pelajaran.nama')->filter()->unique()->implode(', ');
    }

    public function pengawas()
    {
        return $this->belongsTo(Guru::class, 'id_guru_pengawas', 'uuid');
    }

    public function pengisi()
    {
        return $this->belongsTo(User::class, 'diisi_oleh', 'uuid');
    }
}
