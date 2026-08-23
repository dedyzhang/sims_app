<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * Pivot eksplisit (bukan pivot polos) — WAJIB krn tabelnya punya PK `uuid`
 * (konvensi UUID-di-semua-tabel app ini, bukan auto-increment id). BelongsToMany
 * TANPA `->using()` cuma insert baris via query builder mentah saat sync()/attach(),
 * jadi HasUuids TAK PERNAH sempat auto-generate uuid-nya (constraint NOT NULL
 * gagal). Dgn `->using(self::class)`, sync() menyimpan tiap baris lewat model
 * ini, jadi hook `creating` HasUuids tetap jalan.
 */
class UjianBeritaAcaraUjian extends Pivot
{
    use HasUuids;

    protected $table = 'ujian_berita_acara_ujian';
    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';
}
