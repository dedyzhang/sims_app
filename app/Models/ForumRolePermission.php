<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Matriks izin forum (access-role × permission). Sumber kebenaran izin forum,
 * dibaca oleh User::canForum() saat runtime.
 */
class ForumRolePermission extends Model
{
    use HasUuids;

    protected $table = 'forum_role_permissions';
    protected $primaryKey = 'uuid';
    protected $fillable = ['access', 'permission', 'allowed'];

    protected function casts(): array
    {
        return ['allowed' => 'boolean'];
    }

    /** Cache per-request: [access][permission] => bool. */
    protected static ?array $cache = null;

    public static function matrix(): array
    {
        if (static::$cache === null) {
            static::$cache = [];
            foreach (static::all() as $row) {
                static::$cache[$row->access][$row->permission] = (bool) $row->allowed;
            }
        }
        return static::$cache;
    }

    public static function granted(string $access, string $permission): bool
    {
        return static::matrix()[$access][$permission] ?? false;
    }

    public static function clearCache(): void
    {
        static::$cache = null;
    }
}
