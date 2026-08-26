<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Poin extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'poin';
    protected $fillable = ['tanggal', 'id_siswa', 'id_aturan'];

    protected $casts = ['tanggal' => 'date:Y-m-d'];

    /** Cache podium (top3Sekolah/dashboard sekolah) di PoinController dibuang tiap ada perubahan poin. */
    public const CACHE_PODIUM = ['poin:top3_sekolah', 'poin:dashboard_sekolah'];

    /**
     * Setiap tulis/hapus poin (lewat jalur mana pun: poinStore, approval temp, auto-deduksi
     * terlambat) langsung membuang cache podium — podium tetap fast (di-cache di halaman
     * dashboard yg ramai) TAPI tak pernah stale setelah data berubah.
     */
    protected static function booted(): void
    {
        static::saved(fn () => self::lupakanCachePodium());
        static::deleted(fn () => self::lupakanCachePodium());
    }

    public static function lupakanCachePodium(): void
    {
        foreach (self::CACHE_PODIUM as $key) {
            Cache::forget($key);
        }
    }

    public function aturan()
    {
        return $this->belongsTo(Aturan::class, 'id_aturan', 'uuid');
    }

    public function siswa()
    {
        return $this->belongsTo(Siswa::class, 'id_siswa', 'uuid');
    }
}
