<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JamPelajaran extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'jam_pelajaran';
    protected $fillable = ['hari', 'jam_ke', 'jam_mulai', 'jam_selesai', 'jenis', 'label', 'urutan'];

    /** Jenis jam: pelajaran + berbagai jam khusus */
    public const JENIS = [
        'pelajaran'  => 'Pelajaran',
        'istirahat'  => 'Istirahat',
        'upacara'    => 'Upacara',
        'sholat'     => 'Sholat / Ibadah',
        'literasi'   => 'Literasi',
        'pembiasaan' => 'Pembiasaan',
        'ekskul'     => 'Ekstrakurikuler',
        'lainnya'    => 'Lainnya',
    ];

    /** Ikon lucide per jenis khusus */
    public const IKON = [
        'istirahat'  => 'coffee',
        'upacara'    => 'flag',
        'sholat'     => 'moon',
        'literasi'   => 'book-open',
        'pembiasaan' => 'sparkles',
        'ekskul'     => 'volleyball',
        'lainnya'    => 'star',
    ];

    public function getRentangAttribute(): string
    {
        return \Carbon\Carbon::parse($this->jam_mulai)->format('H:i') . ' – ' . \Carbon\Carbon::parse($this->jam_selesai)->format('H:i');
    }

    public function isPelajaran(): bool
    {
        return $this->jenis === 'pelajaran';
    }

    /** Nama tampil untuk jam khusus */
    public function getNamaKhususAttribute(): string
    {
        return $this->label ?: (self::JENIS[$this->jenis] ?? ucfirst($this->jenis));
    }

    public function getIkonAttribute(): string
    {
        return self::IKON[$this->jenis] ?? 'star';
    }
}
