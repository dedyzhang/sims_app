<?php

namespace App\Models;

use App\Support\Forum;
use App\Support\UserRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Crypt;
use Laragear\WebAuthn\Contracts\WebAuthnAuthenticatable;
use Laragear\WebAuthn\WebAuthnAuthentication;
use Laragear\WebAuthn\WebAuthnData;
use Throwable;

class User extends Authenticatable implements WebAuthnAuthenticatable
{
    use HasFactory, Notifiable, HasUuids, WebAuthnAuthentication;

    protected $primaryKey = 'uuid';
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function booted(): void
    {
        static::saving(function (User $user): void {
            if (is_string($user->access) && $user->access !== '') {
                $user->access = UserRole::canonicalize($user->access);
            }
        });
    }

    protected $fillable = [
        'username',
        'identifier',
        'password',
        'access',
        'leaderboard_visible',
        'mission_avatar_config',
        'pin',
        'reset_token',
        'must_change_password',
        'username_customized',
        'dismissed_update_id',
        'gemini_account',
        'gemini_api_key',
        'gemini_api_key_hint',
        'canva_belajar_id',
    ];

    protected $hidden = [
        'password',
        'pin',
        'reset_token',
        'remember_token',
        'gemini_api_key',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'username_customized' => 'boolean',
            'leaderboard_visible' => 'boolean',
            'mission_avatar_config' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function hasGeminiApiKey(): bool
    {
        return trim((string) ($this->gemini_api_key ?? '')) !== '';
    }

    public function geminiApiKeyMasked(): ?string
    {
        $hint = trim((string) ($this->gemini_api_key_hint ?? ''));
        if ($hint === '' || ! $this->hasGeminiApiKey()) {
            return null;
        }

        return '••••'.$hint;
    }

    public function plainGeminiApiKey(): ?string
    {
        $encrypted = trim((string) ($this->gemini_api_key ?? ''));
        if ($encrypted === '') {
            return null;
        }

        try {
            $plain = Crypt::decryptString($encrypted);
        } catch (Throwable) {
            return null;
        }

        $plain = trim($plain);

        return $plain !== '' ? $plain : null;
    }

    public function setGeminiApiKey(string $plainKey): void
    {
        $plain = trim($plainKey);
        $hint = strlen($plain) >= 4 ? substr($plain, -4) : $plain;

        $this->forceFill([
            'gemini_api_key' => Crypt::encryptString($plain),
            'gemini_api_key_hint' => $hint,
        ])->save();
    }

    public function clearGeminiApiKey(): void
    {
        $this->forceFill([
            'gemini_api_key' => null,
            'gemini_api_key_hint' => null,
        ])->save();
    }

    // ─────────────── Forum: izin (berbasis matriks, bukan hardcode role) ───────────────

    protected ?array $forumPermCache = null;

    /**
     * Cek izin forum dari matriks forum_role_permissions (dapat diatur admin).
     * Super admin selalu diizinkan. Policy SELALU memakai method ini.
     */
    public function canForum(string $permission): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        return \App\Models\ForumRolePermission::granted((string) $this->access, $permission);
    }

    /**
     * Izin bawaan per-peran yang MELEKAT tanpa perlu dikonfigurasi lewat RBAC.
     * Dipakai untuk peran fungsional yang modulnya memang miliknya (mis.
     * bendahara ↔ Keuangan) supaya modul tetap bisa diakses out-of-the-box
     * meski matriks RolePermission masih kosong. RBAC tetap bisa MENAMBAH izin
     * lain di atas ini, tapi tak bisa mencabut default (selain admin yang selalu boleh).
     */
    public const DEFAULT_ROLE_PERMISSIONS = [
        'bendahara' => ['manage_keuangan'],
    ];

    /**
     * Check if the user's role has a specific application permission.
     */
    public function canAccess(string $permission): bool
    {
        if ($this->isAdmin()) {
            return true;
        }
        if (in_array($permission, self::DEFAULT_ROLE_PERMISSIONS[$this->access] ?? [], true)) {
            return true;
        }
        return \App\Models\RolePermission::granted((string) $this->access, $permission);
    }

    // ─────────────── Forum: relasi orang tua → kelas anak ───────────────

    /**
     * Daftar id_kelas (uuid) dari anak-anak user ini bila ia orang tua.
     *
     * ASUMSI RELASI (sesuai struktur saat ini): tabel `orangtua(id_login → users.uuid,
     * id_siswa → siswa.uuid)`, dan `siswa.id_kelas`. Bila struktur relasi orang tua
     * Anda berbeda, sesuaikan query di sini (TODO) — jangan diubah di banyak tempat.
     *
     * @return string[]
     */
    public function childrenClassroomIds(): array
    {
        if ($this->access !== 'orangtua') {
            return [];
        }
        return Orangtua::where('id_login', $this->uuid)
            ->with('siswa:uuid,id_kelas')
            ->get()
            ->pluck('siswa.id_kelas')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function orangtuaRecords()
    {
        return $this->hasMany(Orangtua::class, 'id_login', 'uuid');
    }

    // ─────────────── Presence (panel "Peserta Aktif") ───────────────

    public function isOnline(): bool
    {
        return $this->last_seen_at && $this->last_seen_at->gte(now()->subMinutes(3));
    }

    /** 'online' (<3 mnt) | 'recent' (3-15 mnt) | 'offline' (>15 mnt / null). */
    public function presenceStatus(): string
    {
        if (!$this->last_seen_at) {
            return 'offline';
        }
        if ($this->last_seen_at->gte(now()->subMinutes(3))) {
            return 'online';
        }
        if ($this->last_seen_at->gte(now()->subMinutes(15))) {
            return 'recent';
        }
        return 'offline';
    }

    public function presenceLabel(): string
    {
        if (!$this->last_seen_at) {
            return 'Tidak aktif';
        }
        if ($this->isOnline()) {
            return 'Online';
        }
        return $this->last_seen_at->locale('id')->diffForHumans();
    }

    // ─────────────── Tampilan ───────────────

    public function displayName(): string
    {
        if ($this->access === 'orangtua') {
            $anak = Orangtua::where('id_login', $this->uuid)->with('siswa:uuid,nama')->first()?->siswa?->nama;
            return $anak ? 'Ortu ' . $anak : ($this->username ?? 'Ortu');
        }
        return $this->guru?->nama ?? $this->siswa?->nama ?? $this->username ?? '-';
    }

    public function roleLabel(): string
    {
        return Forum::ROLE_LABELS[$this->access] ?? ucfirst((string) $this->access);
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr(trim($this->displayName()), 0, 1)) ?: '?';
    }

    /**
     * Data identitas untuk WebAuthn (biometrik). Model ini tak punya kolom
     * email/name, jadi pakai username sebagai handle & nama guru/siswa sebagai
     * display name. Tanpa override ini, trait default mengirim null → TypeError.
     */
    public function webAuthnData(): WebAuthnData
    {
        $display = $this->guru?->nama ?? $this->siswa?->nama ?? $this->username;

        return WebAuthnData::make($this->username, $display);
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

    /** Token FCM perangkat (multi-device) untuk push notification. */
    public function fcmTokens()
    {
        return $this->hasMany(UserFcmToken::class, 'user_uuid', 'uuid');
    }

    /** Preferensi notifikasi inbox chat admin. */
    public function chatbotAdminSetting()
    {
        return $this->hasOne(ChatbotAdminSetting::class, 'admin_user_id', 'uuid');
    }

    /**
     * Nama tampilan user. Diambil dari profil guru/siswa bila ada, jika tidak
     * memakai username. Dipakai antara lain oleh modul Sarpras (pelapor,
     * peminjam, pengaju, dst). SIMS tidak memiliki kolom `name`.
     */
    public function getNameAttribute(): ?string
    {
        return $this->guru?->nama ?? $this->siswa?->nama ?? $this->username;
    }
}
