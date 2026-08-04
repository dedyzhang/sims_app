<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JadwalPiket extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    protected $table = 'jadwal_piket';
    protected $fillable = ['id_guru', 'hari', 'is_ketua'];

    protected function casts(): array
    {
        return ['is_ketua' => 'boolean'];
    }

    public function guru()
    {
        return $this->belongsTo(Guru::class, 'id_guru', 'uuid');
    }

    /** True bila guru yang diberikan adalah piket aktif untuk tanggal ini. */
    public static function isPiketAktif(string $idGuru, ?string $tanggal = null): bool
    {
        $dayOfWeek = \Carbon\Carbon::parse($tanggal ?: now()->toDateString())->dayOfWeekIso;

        return static::query()
            ->where('id_guru', $idGuru)
            ->where('hari', $dayOfWeek)
            ->exists();
    }

    public static function isKetuaAktif(string $idGuru, ?string $tanggal = null): bool
    {
        $dayOfWeek = \Carbon\Carbon::parse($tanggal ?: now()->toDateString())->dayOfWeekIso;

        return static::query()
            ->where('id_guru', $idGuru)
            ->where('hari', $dayOfWeek)
            ->where('is_ketua', true)
            ->exists();
    }
}
