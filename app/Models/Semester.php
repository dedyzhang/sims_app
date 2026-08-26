<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class Semester extends Model
{
    use HasFactory;

    protected $fillable = ['semester', 'tahun', 'aktif'];

    protected function casts(): array
    {
        return ['aktif' => 'boolean'];
    }

    /**
     * Memo per-request untuk semester aktif — aktif() dipanggil di ~18 lokasi berbeda
     * (nyaris tiap controller yg berurusan dgn nilai/absensi/rapor/dashboard); tanpa memo
     * itu jadi belasan query SELECT identik per page load. Pola sama dgn Setting::memo() &
     * RolePermission::memo() — disimpan di container agar otomatis segar tiap request/test.
     */
    private static function memo(): \ArrayObject
    {
        if (! App::bound('semester.memo_aktif')) {
            App::instance('semester.memo_aktif', new \ArrayObject(['loaded' => false, 'value' => null]));
        }

        return App::make('semester.memo_aktif');
    }

    /**
     * Memo dijaga lewat event Eloquent (create/update/delete SATU baris). Mass-update
     * (Semester::query()->update(['aktif'=>false]), dipakai SettingController::updateSemester()
     * saat menonaktifkan SEMUA semester sebelum mengaktifkan yg baru) TIDAK memicu event model
     * sama sekali — jalur itu WAJIB memanggil clearCache() manual, sama pola RolePermission.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $s) => self::clearCache());
        static::deleted(fn (self $s) => self::clearCache());
    }

    public static function aktif(): ?self
    {
        $memo = self::memo();

        if (! $memo['loaded']) {
            $memo['value'] = static::where('aktif', true)->first();
            $memo['loaded'] = true;
        }

        return $memo['value'];
    }

    /** Wajib dipanggil manual setelah mass-update (query()->update(...) tak memicu event model). */
    public static function clearCache(): void
    {
        if (App::bound('semester.memo_aktif')) {
            App::forgetInstance('semester.memo_aktif');
        }
    }

    public function getNamaLengkapAttribute(): string
    {
        return "Semester {$this->semester} - {$this->tahun}";
    }
}
