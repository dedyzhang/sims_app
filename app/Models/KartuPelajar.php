<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Kartu Pelajar Digital milik seorang siswa (satu kartu per siswa). */
class KartuPelajar extends Model
{
    protected $table = 'kartu_pelajar';

    protected $fillable = ['id_siswa', 'path', 'original_name', 'mime', 'uploaded_by'];

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }

    /** True bila file kartu berupa gambar (untuk pratinjau inline). */
    public function isImage(): bool
    {
        return in_array(strtolower(pathinfo($this->path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
    }
}
