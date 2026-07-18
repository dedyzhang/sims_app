<?php

namespace App\Support;

/**
 * Normalisasi alias peran (sapras ↔ sarpras) agar middleware & UI konsisten.
 */
class UserRole
{
    public static function canonicalize(string $role): string
    {
        $role = strtolower(trim($role));

        return match ($role) {
            'sapras' => 'sarpras',
            default => $role,
        };
    }

    /** @param  string  ...$roles  Daftar peran yang diizinkan (boleh campur sapras/sarpras). */
    public static function matches(string $userAccess, string ...$roles): bool
    {
        $access = self::canonicalize($userAccess);
        foreach ($roles as $role) {
            if ($access === self::canonicalize($role)) {
                return true;
            }
        }

        return false;
    }

    /** Alias untuk pengecekan Blade/controller. */
    public static function is(string $userAccess, string ...$roles): bool
    {
        return self::matches($userAccess, ...$roles);
    }
}
