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
    private static function memo(): ArrayObject
    {
        if (! app()->bound('setting.memo')) {
            $rows = static::query()->get(['key', 'value'])
                ->mapWithKeys(fn (self $s) => [$s->key => ['value' => $s->value]])
                ->all();
            app()->instance('setting.memo', new ArrayObject($rows));
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
            self::memo()[$setting->key] = ['value' => $setting->value];
        });

        static::deleted(function (self $setting): void {
            self::memo()->offsetUnset($setting->key);
        });
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $memo = self::memo();

        return $memo->offsetExists($key) ? $memo[$key]['value'] : $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
