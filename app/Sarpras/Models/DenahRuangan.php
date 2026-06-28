<?php

namespace App\Sarpras\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DenahRuangan extends SarprasModel
{
    protected $table = 'sarpras_denah_ruangan';

    /** Warna blok default bila ruangan belum diberi warna (emerald-600). */
    public const WARNA_DEFAULT = '#059669';

    protected $fillable = [
        'school_id', 'denah_id', 'kode', 'nama',
        'pos_x', 'pos_y', 'lebar', 'tinggi', 'warna', 'gambar_denah_path', 'foto_path',
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

    /** Warna blok efektif (pakai default bila kosong). */
    public function getWarnaHexAttribute(): string
    {
        $w = $this->attributes['warna'] ?? null;

        return is_string($w) && preg_match('/^#[0-9a-fA-F]{6}$/', $w) ? $w : self::WARNA_DEFAULT;
    }

    /** Warna teks kontras (hitam/putih) menurut luminansi warna blok. */
    public function getWarnaTeksAttribute(): string
    {
        $hex = ltrim($this->warna_hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        // Luminansi perseptual (0-255). Terang -> teks gelap, gelap -> teks putih.
        $luminansi = 0.299 * $r + 0.587 * $g + 0.114 * $b;

        return $luminansi > 150 ? '#111827' : '#ffffff';
    }
}
