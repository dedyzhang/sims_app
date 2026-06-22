<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JadwalPemeliharaan extends SarprasModel
{
    protected $table = 'sarpras_jadwal_pemeliharaan';

    protected $fillable = [
        'school_id', 'aset_id', 'nama', 'interval_hari',
        'tgl_terakhir', 'tgl_berikutnya', 'aktif', 'catatan',
    ];

    protected $casts = [
        'interval_hari' => 'integer',
        'tgl_terakhir' => 'date',
        'tgl_berikutnya' => 'date',
        'aktif' => 'boolean',
    ];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    /** Apakah jadwal sudah jatuh tempo (untuk reminder scheduler). */
    public function getJatuhTempoAttribute(): bool
    {
        return $this->aktif
            && $this->tgl_berikutnya !== null
            && $this->tgl_berikutnya->isToday() === false
            && $this->tgl_berikutnya->isPast();
    }
}
