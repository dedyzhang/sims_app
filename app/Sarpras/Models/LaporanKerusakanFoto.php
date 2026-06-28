<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class LaporanKerusakanFoto extends SarprasModel
{
    protected $table = 'sarpras_laporan_kerusakan_foto';

    protected $fillable = ['school_id', 'laporan_id', 'foto_path'];

    public function laporan(): BelongsTo
    {
        return $this->belongsTo(LaporanKerusakan::class, 'laporan_id');
    }

    /** URL publik foto (path relatif -> Storage::url). */
    public function getUrlAttribute(): ?string
    {
        return $this->foto_path ? Storage::url($this->foto_path) : null;
    }
}
