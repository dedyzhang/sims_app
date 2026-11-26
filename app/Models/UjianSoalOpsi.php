<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UjianSoalOpsi extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'ujian_soal_opsi';
    protected $primaryKey = 'uuid';

    protected $fillable = ['id_soal', 'teks_opsi', 'is_benar', 'urutan'];

    protected function casts(): array
    {
        return [
            'is_benar' => 'boolean',
            'urutan'   => 'integer',
        ];
    }

    /** Sama spt UjianSoal::booted() — opsi berubah = isi soal yg tampil ke siswa berubah. */
    protected static function booted(): void
    {
        $lupakan = fn (self $opsi) => Ujian::lupakanCacheSoal(
            UjianSoal::whereKey($opsi->id_soal)->value('id_ujian')
        );
        static::saved($lupakan);
        static::deleted($lupakan);
    }

    public function soal()
    {
        return $this->belongsTo(UjianSoal::class, 'id_soal', 'uuid');
    }
}
