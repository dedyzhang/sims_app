<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PengadaanDokumen extends SarprasModel
{
    protected $table = 'sarpras_pengadaan_dokumen';

    protected $fillable = ['school_id', 'pengadaan_id', 'nama', 'file_path'];

    public function pengadaan(): BelongsTo
    {
        return $this->belongsTo(Pengadaan::class, 'pengadaan_id');
    }

    public function getUrlAttribute(): ?string
    {
        return $this->file_path ? Storage::url($this->file_path) : null;
    }
}
