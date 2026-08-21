<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaporanKerusakanFoto extends SarprasModel
{
    protected $table = 'sarpras_laporan_kerusakan_foto';

    protected $fillable = ['school_id', 'laporan_id', 'foto_path'];

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(LaporanKerusakan::class, 'laporan_id');
    }

    /** URL foto lewat route Sarpras agar tidak bergantung ke APP_URL atau symlink public/storage. */
    public function getUrlAttribute(): ?string
    {
        return $this->foto_path ? route('sarpras.kerusakan.foto', $this->id, false) : null;
    }
}
