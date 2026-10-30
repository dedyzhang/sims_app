<?php

namespace App\Policies;

use App\Models\RpmDigital;
use App\Models\User;
use App\Support\UserRole;

/**
 * Otorisasi dokumen RPM Digital.
 * Ditemukan otomatis oleh Laravel (App\Models\RpmDigital -> App\Policies\RpmDigitalPolicy).
 */
class RpmDigitalPolicy
{
    private const ALLOWED_ROLES = [
        'admin', 'superadmin', 'kepala', 'kurikulum', 'kesiswaan', 'sarpras', 'sapras', 'guru', 'walikelas',
    ];

    /** Apakah user boleh membuka modul RPM Digital. */
    public function viewAny(User $user): bool
    {
        return UserRole::matches($user->access, ...self::ALLOWED_ROLES);
    }

    /** Apakah user boleh melihat/pratinjau dokumen RPM tertentu. */
    public function view(User $user, RpmDigital $rpm): bool
    {
        if ($user->isAdmin() || in_array($user->access, ['kepala', 'kurikulum'], true)) {
            return true;
        }

        return $user->uuid === $rpm->user_uuid;
    }

    /** Apakah user boleh membuat RPM Digital baru. */
    public function create(User $user): bool
    {
        return UserRole::matches($user->access, ...self::ALLOWED_ROLES);
    }

    /** Apakah user boleh mengubah dokumen RPM Digital. */
    public function update(User $user, RpmDigital $rpm): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->uuid === $rpm->user_uuid;
    }

    /** Apakah user boleh menghapus dokumen RPM Digital. */
    public function delete(User $user, RpmDigital $rpm): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->uuid === $rpm->user_uuid;
    }
}
