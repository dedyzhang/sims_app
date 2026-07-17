<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class CanvaConnection extends Model
{
    use HasUuids;

    protected $table = 'canva_connections';

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'user_uuid',
        'canva_user_id',
        'email',
        'display_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'connected_at',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    public function setAccessTokenAttribute(?string $value): void
    {
        $this->attributes['access_token'] = $value !== null && $value !== ''
            ? Crypt::encryptString($value)
            : '';
    }

    public function setRefreshTokenAttribute(?string $value): void
    {
        $this->attributes['refresh_token'] = $value !== null && $value !== ''
            ? Crypt::encryptString($value)
            : null;
    }

    public function plainAccessToken(): string
    {
        $raw = (string) ($this->attributes['access_token'] ?? '');
        if ($raw === '') {
            return '';
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return '';
        }
    }

    public function plainRefreshToken(): ?string
    {
        $raw = $this->attributes['refresh_token'] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }

        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    public function isExpired(?int $leewaySeconds = 60): bool
    {
        if (! $this->token_expires_at) {
            return false;
        }

        return $this->token_expires_at->lte(now()->addSeconds($leewaySeconds));
    }

    public function emailMasked(): ?string
    {
        $email = trim((string) $this->email);
        if ($email === '' || ! str_contains($email, '@')) {
            return $email !== '' ? $email : null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $keep = min(3, max(1, mb_strlen($local) - 1));

        return mb_substr($local, 0, $keep).str_repeat('*', max(1, mb_strlen($local) - $keep)).'@'.$domain;
    }
}
