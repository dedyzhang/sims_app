<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class RapatDokumentasi extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'rapat_dokumentasi';
    protected $fillable = [
        'id_rapat', 'original_name', 'stored_name', 'path', 'mime',
        'size_original', 'size_compressed', 'sort_order', 'id_pengunggah',
    ];

    public function rapat()
    {
        return $this->belongsTo(Rapat::class, 'id_rapat', 'uuid');
    }

    public function pengunggah()
    {
        return $this->belongsTo(Guru::class, 'id_pengunggah', 'uuid');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }

    public function getUrlAttribute(): string
    {
        return asset('storage/' . $this->path);
    }
}
