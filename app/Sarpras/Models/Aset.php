<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Aset extends SarprasModel
{
    protected $table = 'sarpras_aset';

    protected $fillable = [
        'school_id', 'kode', 'nama', 'kategori_id', 'ruangan_id', 'merk',
        'spesifikasi', 'kondisi', 'status', 'tgl_perolehan',
        'nilai_perolehan', 'sumber_dana', 'foto_path',
    ];

    protected $casts = [
        'spesifikasi' => 'array',          // spec key-value JSON
        'tgl_perolehan' => 'date',
        'nilai_perolehan' => 'integer',    // UANG integer rupiah (BCMath)
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'kategori_id');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(DenahRuangan::class, 'ruangan_id');
    }

    public function laporanKerusakan(): HasMany
    {
        return $this->hasMany(LaporanKerusakan::class, 'aset_id');
    }

    public function perbaikan(): HasMany
    {
        return $this->hasMany(Perbaikan::class, 'aset_id');
    }

    public function jadwalPemeliharaan(): HasMany
    {
        return $this->hasMany(JadwalPemeliharaan::class, 'aset_id');
    }

    public function peminjamanItem(): HasMany
    {
        return $this->hasMany(PeminjamanItem::class, 'aset_id');
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiAset::class, 'aset_id');
    }

    /** Tampilan: "Rp 1.500.000". */
    public function getNilaiPerolehanRpAttribute(): string
    {
        return $this->rupiah('nilai_perolehan');
    }

    /** Label kondisi yang ramah dibaca. */
    public function getKondisiLabelAttribute(): string
    {
        return match ($this->kondisi) {
            'baik' => 'Baik',
            'rusak_ringan' => 'Rusak Ringan',
            'rusak_berat' => 'Rusak Berat',
            'hilang' => 'Hilang',
            default => ucfirst((string) $this->kondisi),
        };
    }
}
