<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $fillable = ['role', 'permission'];

    /**
     * Helper to check if a specific role has been granted a permission.
     */
    public static function granted(string $role, string $permission): bool
    {
        return static::where('role', $role)->where('permission', $permission)->exists();
    }
}
