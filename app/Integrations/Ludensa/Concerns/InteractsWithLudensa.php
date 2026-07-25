<?php

namespace App\Integrations\Ludensa\Concerns;

use App\Integrations\Ludensa\LudensaJenjang;
use App\Integrations\Ludensa\LudensaSchool;
use App\Support\UserRole;
use Illuminate\Database\Eloquent\Builder;

trait InteractsWithLudensa
{
    public function hasRole(string|array $roles): bool
    {
        $roles = is_array($roles) ? $roles : [$roles];
        $access = UserRole::canonicalize((string) $this->access);

        if ($access === 'superadmin') {
            return true;
        }

        foreach ($roles as $role) {
            $role = UserRole::canonicalize((string) $role);

            if ($access === $role) {
                return true;
            }

            if ($role === 'admin' && $this->isAdmin()) {
                return true;
            }

            if ($role === 'guru' && UserRole::matches($access, 'guru', 'walikelas')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeRole(Builder $query, string|array $roles): Builder
    {
        $roles = is_array($roles) ? $roles : array_slice(func_get_args(), 1);
        $allowed = [];

        foreach ($roles as $role) {
            if ($role === 'siswa') {
                $allowed[] = 'siswa';
            } elseif ($role === 'guru') {
                $allowed = array_merge($allowed, ['guru', 'walikelas']);
            } elseif ($role === 'admin') {
                $allowed = array_merge($allowed, ['admin', 'superadmin']);
            } else {
                $allowed[] = $role;
            }
        }

        return $query->whereIn('access', array_values(array_unique($allowed)));
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeOrderByName(Builder $query): Builder
    {
        return $query
            ->leftJoin('siswa', 'users.uuid', '=', 'siswa.id_login')
            ->leftJoin('gurus', 'users.uuid', '=', 'gurus.id_login')
            ->orderByRaw('COALESCE(siswa.nama, gurus.nama, users.username)')
            ->select('users.*');
    }

    public function getSchoolIdAttribute(?string $value): ?string
    {
        if (filled($value)) {
            return $value;
        }

        return LudensaSchool::id();
    }

    public function getJenjangAttribute(?string $value): ?string
    {
        if (filled($value)) {
            return $value;
        }

        if ((string) $this->access !== 'siswa') {
            return null;
        }

        $siswa = $this->relationLoaded('siswa')
            ? $this->siswa
            : $this->siswa()->with('kelas')->first();

        $tingkat = (int) ($siswa?->kelas?->tingkat ?? 0);

        return LudensaJenjang::fromKelasTingkat($tingkat);
    }

    public function getAvatarAttribute(?string $value): ?string
    {
        if (filled($value)) {
            return $value;
        }

        $config = $this->mission_avatar_config;
        if (is_array($config) && filled($config['emoji'] ?? null)) {
            return (string) $config['emoji'];
        }

        return '🙂';
    }

    public function pakaiFotoAvatar(): bool
    {
        return \Ludensa\Support\AvatarPemain::pakaiFoto($this);
    }

    public function urlFotoAvatar(): ?string
    {
        return \Ludensa\Support\AvatarPemain::urlFoto($this);
    }

    /** @param  string[]  $roles */
    public function syncRoles(array $roles): static
    {
        return $this;
    }
}
