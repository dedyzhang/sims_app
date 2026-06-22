<?php

namespace App\Sarpras\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LaporanKerusakan extends SarprasModel
{
    protected $table = 'sarpras_laporan_kerusakan';

    protected $fillable = [
        'school_id', 'kode', 'aset_id', 'ruangan_id', 'pelapor_id',
        'deskripsi', 'urgensi', 'status', 'alasan_tolak',
        'ditangani_oleh', 'ditangani_pada',
    ];

    protected $casts = [
        'ditangani_pada' => 'datetime',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(DenahRuangan::class, 'ruangan_id');
    }

    public function pelapor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pelapor_id');
    }

    public function penangan(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ditangani_oleh');
    }

    public function foto(): HasMany
    {
        return $this->hasMany(LaporanKerusakanFoto::class, 'laporan_id');
    }

    public function perbaikan(): HasMany
    {
        return $this->hasMany(Perbaikan::class, 'laporan_id');
    }

    public function getUrgensiLabelAttribute(): string
    {
        return ucfirst((string) $this->urgensi);
    }
}
