<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PemanggilanDokumentasi extends Model
{
    use HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'pemanggilan_dokumentasi';

    protected $fillable = [
        'id_pemanggilan', 'original_name', 'stored_name', 'path', 'mime',
        'size_original', 'size_compressed', 'sort_order', 'id_pengunggah',
    ];

    public function pemanggilan()
    {
        return $this->belongsTo(Pemanggilan::class, 'id_pemanggilan', 'uuid');
    }

    public function pengunggah()
    {
        return $this->belongsTo(User::class, 'id_pengunggah', 'uuid');
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
