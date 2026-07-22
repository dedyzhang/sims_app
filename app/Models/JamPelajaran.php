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
    protected $fillable = ['hari', 'jam_ke', 'jam_mulai', 'jam_selesai', 'jenis', 'label', 'urutan', 'kelas_scope'];

    protected $casts = ['kelas_scope' => 'array'];

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

    /** true bila jam ini (istirahat/upacara/dll) berlaku utk SEMUA kelas — kelas_scope kosong/null. */
    public function untukSemuaKelas(): bool
    {
        return empty($this->kelas_scope);
    }

    /** true bila kelas ini termasuk cakupan jam khusus ini (atau cakupannya "semua kelas"). */
    public function berlakuUntukKelas(string $idKelas): bool
    {
        return $this->untukSemuaKelas() || in_array($idKelas, $this->kelas_scope, true);
    }

    /**
     * true bila, UNTUK KELAS INI, jam ini adalah slot khusus (bukan slot pelajaran biasa) —
     * dipakai di semua tempat yg memutuskan apakah sebuah sel (jam, kelas) boleh diisi mapel
     * atau harus tampil sbg istirahat/khusus. Kelas di luar cakupan tetap dapat slot pelajaran
     * biasa pada jam yg sama (istirahat bergilir per-kelas).
     */
    public function isKhususUntukKelas(string $idKelas): bool
    {
        return !$this->isPelajaran() && $this->berlakuUntukKelas($idKelas);
    }
}
