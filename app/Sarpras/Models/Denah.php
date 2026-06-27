<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Denah extends SarprasModel
{
    protected $table = 'sarpras_denah';

    protected $fillable = ['school_id', 'nama', 'gedung', 'lantai', 'gambar_path', 'deskripsi'];

    public function ruangan(): HasMany
    {
        return $this->hasMany(DenahRuangan::class, 'denah_id');
    }
}
