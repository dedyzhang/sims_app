<?php

namespace App\Sarpras\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Teknisi extends SarprasModel
{
    protected $table = 'sarpras_teknisi';

    protected $fillable = [
        'school_id', 'nama', 'tipe', 'spesialisasi', 'telepon', 'alamat', 'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function perbaikan(): HasMany
    {
        return $this->hasMany(Perbaikan::class, 'teknisi_id');
    }
}
