<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DenahRuangan extends SarprasModel
{
    protected $table = 'sarpras_denah_ruangan';

    protected $fillable = [
        'school_id', 'denah_id', 'kode', 'nama',
        'pos_x', 'pos_y', 'lebar', 'tinggi', 'gambar_denah_path', 'foto_path',
        'kapasitas', 'deskripsi',
    ];

    protected $casts = [
        // Koordinat & ukuran dalam persen 0-100 (responsif, bukan pixel absolut).
        'pos_x' => 'decimal:2',
        'pos_y' => 'decimal:2',
        'lebar' => 'decimal:2',
        'tinggi' => 'decimal:2',
        'kapasitas' => 'integer',
    ];

    public function denah(): BelongsTo
    {
        return $this->belongsTo(Denah::class, 'denah_id');
    }

    public function aset(): HasMany
    {
        return $this->hasMany(Aset::class, 'ruangan_id');
    }

    public function booking(): HasMany
    {
        return $this->hasMany(BookingRuangan::class, 'ruangan_id');
    }
}
