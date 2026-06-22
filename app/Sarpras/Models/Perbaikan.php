<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Perbaikan extends SarprasModel
{
    protected $table = 'sarpras_perbaikan';

    protected $fillable = [
        'school_id', 'kode', 'aset_id', 'laporan_id', 'teknisi_id',
        'deskripsi', 'status', 'biaya', 'tgl_mulai', 'tgl_selesai', 'catatan',
    ];

    protected $casts = [
        'biaya' => 'integer',          // UANG integer rupiah (BCMath)
        'tgl_mulai' => 'date',
        'tgl_selesai' => 'date',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(LaporanKerusakan::class, 'laporan_id');
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(Teknisi::class, 'teknisi_id');
    }

    public function getBiayaRpAttribute(): string
    {
        return $this->rupiah('biaya');
    }
}
