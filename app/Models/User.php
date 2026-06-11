<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;

class User extends Authenticatable implements WebAuthnAuthenticatable
{
    use HasFactory, Notifiable, HasUuids, WebAuthnAuthentication;

    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'username',
        'identifier',
        'password',
        'access',
        'pin',
        'reset_token',
        'must_change_password',
        'username_customized',
    ];

    protected $hidden = [
        'password',
        'pin',
        'reset_token',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'username_customized' => 'boolean',
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->access === 'superadmin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->access, ['superadmin', 'admin']);
    }

    public function guru()
    {
        return $this->hasOne(Guru::class, 'id_login', 'uuid');
    }

    public function siswa()
    {
        return $this->hasOne(Siswa::class, 'id_login', 'uuid');
    }

    public function preference()
    {
        return $this->hasOne(UserPreference::class, 'user_uuid', 'uuid');
    }
}
