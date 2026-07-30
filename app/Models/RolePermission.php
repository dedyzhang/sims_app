<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;

class RolePermission extends Model
{
    protected $fillable = ['role', 'permission'];

    /**
     * Memo per-request (per role): sekali dimuat, semua permission milik role itu tersimpan
     * di memori — bukan 1 query EXISTS per (role, permission) spt sebelumnya. User::canAccess()
     * dipanggil berkali-kali dgn permission BERBEDA per page load (tiap item sidebar + macam2
     * feature-gate di controller/view) — tanpa memo ini, itu jadi belasan query kecil terpisah
     * di HAMPIR SEMUA halaman berlogin (bukan cuma satu route, beda dari N+1 lain yg sudah
     * diperbaiki sebelumnya). Disimpan di container (bukan static property biasa) supaya
     * otomatis segar tiap request — dan tiap test, krn Laravel membangun ulang aplikasi per test.
     */
    private static function memo(): \ArrayObject
    {
        if (! App::bound('role_permission.memo')) {
            App::instance('role_permission.memo', new \ArrayObject());
        }

        return App::make('role_permission.memo');
    }

    /**
     * Memo dijaga lewat event Eloquent, spy penulisan lewat jalur mana pun (create/update/delete
     * SATU baris) tetap konsisten. Mass-delete (RolePermission::query()->delete(), dipakai
     * SettingController::rolesSave() saat menyimpan ulang seluruh matriks) TIDAK memicu event
     * model sama sekali — jalur itu WAJIB memanggil clearCache() manual setelahnya.
     */
    protected static function booted(): void
    {
        static::saved(function (self $rp): void {
            self::memo()->offsetUnset($rp->role);
        });

        static::deleted(function (self $rp): void {
            self::memo()->offsetUnset($rp->role);
        });
    }

    /**
     * Helper to check if a specific role has been granted a permission.
     */
    public static function granted(string $role, string $permission): bool
    {
        $memo = self::memo();

        if (! $memo->offsetExists($role)) {
            $memo[$role] = static::where('role', $role)->pluck('permission')->all();
        }

        return in_array($permission, $memo[$role], true);
    }

    /** Wajib dipanggil manual setelah mass-delete (query()->delete() tak memicu event model). */
    public static function clearCache(): void
    {
        if (App::bound('role_permission.memo')) {
            App::forgetInstance('role_permission.memo');
        }
    }
}
