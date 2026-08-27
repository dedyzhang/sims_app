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

    /** True bila guru yang diberikan adalah piket aktif untuk tanggal ini.
     *  Di-memo per-request (per id_guru|tanggal) di container — isPiketAktif() dipanggil 3x saat
     *  render dashboard guru (menu sidebar, DashboardController, dashboard.blade) dgn argumen sama
     *  → dulu 3 query exists identik. Pakai app()->instance biar otomatis segar tiap request/test
     *  (bukan static array yg bisa nyangkut antar-test/worker). Pola sama Setting/RolePermission. */
    public static function isPiketAktif(string $idGuru, ?string $tanggal = null): bool
    {
        $tgl = $tanggal ?: now()->toDateString();
        $key = $idGuru . '|' . $tgl;

        if (! \Illuminate\Support\Facades\App::bound('jadwal_piket.aktif_memo')) {
            \Illuminate\Support\Facades\App::instance('jadwal_piket.aktif_memo', new \ArrayObject());
        }
        $memo = \Illuminate\Support\Facades\App::make('jadwal_piket.aktif_memo');

        if ($memo->offsetExists($key)) {
            return $memo[$key];
        }

        $dayOfWeek = \Carbon\Carbon::parse($tgl)->dayOfWeekIso;

        return $memo[$key] = static::query()
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
