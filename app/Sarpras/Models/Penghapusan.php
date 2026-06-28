<?php

namespace App\Sarpras\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Penghapusan extends SarprasModel
{
    protected $table = 'sarpras_penghapusan';

    protected $fillable = [
        'school_id', 'kode', 'aset_id', 'alasan', 'metode', 'status',
        'diajukan_oleh', 'disetujui_oleh', 'disetujui_pada', 'alasan_tolak',
    ];

    protected $casts = ['disetujui_pada' => 'datetime'];

    public function aset(): BelongsTo
    {
        return $this->belongsTo(Aset::class, 'aset_id');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }
}
