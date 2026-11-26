<?php

namespace App\Models;

use ArrayObject;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    /**
     * Memo per-request, dimuat SEKALIGUS (1 query utk semua baris) begitu get() pertama
     * dipanggil — bukan 1 query per key spt sebelumnya. Satu render halaman membaca
     * puluhan setting berbeda (layout saja mengecek 22 modul, + macam2 toggle fitur di
     * berbagai blok dashboard/halaman) — dgn memo per-key lama, itu jadi puluhan query
     * kecil terpisah tiap page load (nyata: dashboard admin turun dari 67 query jadi
     * jauh lebih sedikit setelah ini). Karena SEMUA baris sudah termuat di awal, key yg
     * memang tak ada di tabel otomatis tak pernah ketemu di memo — tak perlu lagi
     * penanda ABSENT terpisah spt sebelumnya. Memo disimpan di container agar otomatis
     * segar tiap request — dan tiap test, krn Laravel membangun ulang aplikasi per test.
     */
    private static function cacheKey(): string
    {
        return app()->runningUnitTests() ? 'test_settings_rows' : 'global_settings_rows';
    }

    private static function memo(): ArrayObject
    {
        if (! app()->bound('setting.memo')) {
            try {
                $rows = \Illuminate\Support\Facades\Cache::remember(
                    self::cacheKey(),
                    300,
                    fn () => static::query()->get(['key', 'value'])
                        ->mapWithKeys(fn (self $s) => [$s->key => ['value' => $s->value]])
                        ->all()
                );
                $lengkap = true;
            } catch (\Throwable) {
                $rows = [];
                $lengkap = false;
            }
            app()->instance('setting.memo', new ArrayObject($rows));
            // Memo memuat SELURUH baris tabel settings → key yg tak ada di memo memang tak ada
            // di DB (setiap tulis lewat Eloquent membuang cache ini, lihat booted()).
            app()->instance('setting.memo.lengkap', $lengkap);
        }

        return app('setting.memo');
    }

    /**
     * Memo dijaga lewat event Eloquent, bukan hanya di set(), supaya penulisan
     * lewat jalur mana pun (create/update/delete langsung) tetap konsisten.
     */
    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            \Illuminate\Support\Facades\Cache::forget(self::cacheKey());
            self::memo()[$setting->key] = ['value' => $setting->value];
        });

        static::deleted(function (self $setting): void {
            \Illuminate\Support\Facades\Cache::forget(self::cacheKey());
            self::memo()->offsetUnset($setting->key);
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $memo = self::memo();

        if ($memo->offsetExists($key)) {
            $val = $memo[$key]['value'];
            return $val !== null ? $val : $default;
        }

        // Memo lengkap (seluruh tabel termuat) & key tak ada di dalamnya = key memang tak ada di
        // DB → langsung default, TANPA query. Dulu tiap key yg tak pernah diset (mis.
        // fitur_ujian_aktif) di-query ulang di SETIAP request — termasuk tiap autosave ujian.
        if (app()->bound('setting.memo.lengkap') && app('setting.memo.lengkap')) {
            $memo[$key] = ['value' => null];
            return $default;
        }

        // Memo TIDAK lengkap (gagal memuat seluruh tabel): cek sekali ke database, dan simpan
        // hasilnya ke memo (meskipun null) agar pemanggilan berikutnya tak query ulang.
        try {
            $row = static::query()->where('key', $key)->first(['value']);
            $val = $row !== null ? $row->value : null;
        } catch (\Throwable) {
            $val = null;
        }

        $memo[$key] = ['value' => $val];

        return $val !== null ? $val : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
