<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ujian extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'ujians';
    protected $primaryKey = 'uuid';

    protected $fillable = [
        'id_pelajaran', 'id_materi', 'id_ujian_paket', 'created_by', 'judul', 'instruksi', 'jenis',
        'target_nilai', 'durasi_menit', 'acak_soal', 'acak_opsi', 'tampilkan_pembahasan', 'status',
    ];

    protected function casts(): array
    {
        return [
            'durasi_menit'          => 'integer',
            'acak_soal'             => 'boolean',
            'acak_opsi'             => 'boolean',
            'tampilkan_pembahasan'  => 'boolean',
        ];
    }

    public function pelajaran()
    {
        return $this->belongsTo(Pelajaran::class, 'id_pelajaran', 'uuid');
    }

    public function materi()
    {
        return $this->belongsTo(Materi::class, 'id_materi', 'uuid');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    public function soal()
    {
        return $this->hasMany(UjianSoal::class, 'id_ujian', 'uuid')->orderBy('urutan');
    }

    public function kelas()
    {
        return $this->hasMany(UjianKelas::class, 'id_ujian', 'uuid');
    }

    /** Paket (folder periode ujian, mis. "PAS Semester 1") — opsional, ujian standalone tetap sah tanpa ini. */
    public function paket()
    {
        return $this->belongsTo(UjianPaket::class, 'id_ujian_paket', 'uuid');
    }

    /** Baris jadwal (hari×jam) milik ujian ini — lihat UjianJadwalSync utk cara dipakai. */
    public function jadwalUjian()
    {
        return $this->hasMany(UjianJadwal::class, 'id_ujian', 'uuid');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    /** target_nilai='sumatif' butuh id_materi (per-Ngajar) — jadi cuma boleh 1 kelas. */
    public function butuhSatuKelas(): bool
    {
        return $this->target_nilai === 'sumatif';
    }

    public function jenisLabel(): string
    {
        return match ($this->jenis) {
            'pts'   => 'PTS',
            'pas'   => 'PAS',
            'uas'   => 'UAS',
            default => 'Ulangan Harian',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'published' => 'Terbit',
            'closed'    => 'Ditutup',
            default     => 'Draf',
        };
    }
}
