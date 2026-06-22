<?php

namespace App\Sarpras\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Peminjaman extends SarprasModel
{
    protected $table = 'sarpras_peminjaman';

    protected $fillable = [
        'school_id', 'kode', 'peminjam_id', 'ruangan_id', 'keperluan',
        'tgl_pinjam', 'tgl_kembali_rencana', 'tgl_kembali_aktual',
        'mulai', 'selesai',
        'status', 'disetujui_oleh', 'disetujui_pada', 'alasan_tolak',
    ];

    protected $casts = [
        'tgl_pinjam' => 'date',
        'tgl_kembali_rencana' => 'date',
        'tgl_kembali_aktual' => 'date',
        'mulai' => 'datetime',
        'selesai' => 'datetime',
        'disetujui_pada' => 'datetime',
    ];

    public function peminjam(): BelongsTo
    {
        return $this->belongsTo(User::class, 'peminjam_id');
    }

    public function ruangan(): BelongsTo
    {
        return $this->belongsTo(DenahRuangan::class, 'ruangan_id');
    }

    /**
     * Scope: pengajuan yang BENTROK pada ruangan + rentang waktu sama.
     * Hanya status yang "menahan" ruangan: diajukan, dipinjam, terlambat.
     */
    public function scopeBentrok(Builder $query, string $ruanganId, $mulai, $selesai, ?string $kecualiId = null): Builder
    {
        return $query
            ->where('ruangan_id', $ruanganId)
            ->whereIn('status', ['diajukan', 'dipinjam', 'terlambat'])
            ->where('mulai', '<', $selesai)
            ->where('selesai', '>', $mulai)
            ->when($kecualiId, fn ($q) => $q->where('id', '!=', $kecualiId));
    }

    public function penyetuju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_oleh');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PeminjamanItem::class, 'peminjaman_id');
    }

    /** Cek apakah peminjaman terlambat (rencana lewat & belum dikembalikan). */
    public function getTerlambatAttribute(): bool
    {
        return $this->status === 'dipinjam'
            && $this->tgl_kembali_rencana !== null
            && $this->tgl_kembali_rencana->isPast();
    }
}
