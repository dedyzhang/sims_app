<?php

namespace App\Sarpras\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pengadaan extends SarprasModel
{
    protected $table = 'sarpras_pengadaan';

    protected $fillable = [
        'school_id', 'kode', 'judul', 'deskripsi', 'diajukan_oleh',
        'status', 'total_estimasi', 'disetujui_oleh', 'disetujui_pada', 'alasan_tolak',
    ];

    protected $casts = [
        'total_estimasi' => 'integer',     // UANG integer rupiah (BCMath)
        'disetujui_pada' => 'datetime',
    ];

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PengadaanItem::class, 'pengadaan_id');
    }

    public function dokumen(): HasMany
    {
        return $this->hasMany(PengadaanDokumen::class, 'pengadaan_id');
    }

    public function getTotalEstimasiRpAttribute(): string
    {
        return $this->rupiah('total_estimasi');
    }
}
