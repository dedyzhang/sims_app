<?php

namespace App\Sarpras\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BookingRuangan extends SarprasModel
{
    protected $table = 'sarpras_booking_ruangan';

    protected $fillable = [
        'school_id', 'ruangan_id', 'pemohon_id', 'keperluan',
        'mulai', 'selesai', 'status',
    ];

    protected $casts = [
        'mulai' => 'datetime',
        'selesai' => 'datetime',
    ];

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(DenahRuangan::class, 'ruangan_id');
    }

    public function pemohon(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pemohon_id');
    }

    /**
     * Scope: booking yang BENTROK dengan rentang [$mulai, $selesai] pada ruangan sama.
     * Dua rentang bentrok bila: mulai < selesai_lain DAN selesai > mulai_lain.
     */
    public function scopeBentrok(Builder $query, string $ruanganId, $mulai, $selesai, ?string $kecualiId = null): Builder
    {
        return $query
            ->where('ruangan_id', $ruanganId)
            ->whereIn('status', ['diajukan', 'disetujui'])
            ->where('mulai', '<', $selesai)
            ->where('selesai', '>', $mulai)
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId));
    }
}
