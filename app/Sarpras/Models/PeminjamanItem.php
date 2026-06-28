<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PeminjamanItem extends SarprasModel
{
    protected $table = 'sarpras_peminjaman_item';

    protected $fillable = [
        'school_id', 'peminjaman_id', 'aset_id', 'qty',
        'kondisi_pinjam', 'kondisi_kembali',
    ];

    protected $casts = ['qty' => 'integer'];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(Peminjaman::class, 'peminjaman_id');
    }

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }
}
