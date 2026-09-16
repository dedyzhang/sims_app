<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DemoAccess extends Model
{
    use HasUuids;

    public const STATUS_PROVISIONING = 'provisioning';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_REVOKED = 'revoked';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'source_request_id',
        'school_display_name',
        'contact_name',
        'contact_email_encrypted',
        'contact_email_hash',
        'status',
        'starts_at',
        'expires_at',
        'first_login_at',
        'onboarding_sent_at',
        'revoked_at',
        'approved_actor',
        'correlation_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'first_login_at' => 'datetime',
            'onboarding_sent_at' => 'datetime',
            'revoked_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'demo_access_id', 'id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(DemoProvisioningEvent::class, 'demo_access_id', 'id');
    }

    public function isCurrentlyActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->starts_at !== null
            && $this->expires_at !== null
            && $this->starts_at->lte(now())
            && $this->expires_at->gt(now());
    }

    public function contactEmail(): string
    {
        try {
            return Crypt::decryptString((string) $this->contact_email_encrypted);
        } catch (\Throwable) {
            return '';
        }
    }

    public static function emailHash(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key'));
    }

    public function markExpiredAndInvalidateSessions(): bool
    {
        if ($this->status === self::STATUS_REVOKED) {
            return false;
        }

        if ($this->status === self::STATUS_EXPIRED) {
            $this->invalidateSessions();

            return false;
        }

        $this->forceFill([
            'status' => self::STATUS_EXPIRED,
        ])->save();

        $this->invalidateSessions();

        return true;
    }

    public function markRevokedAndInvalidateSessions(): void
    {
        $this->forceFill([
            'status' => self::STATUS_REVOKED,
            'revoked_at' => $this->revoked_at ?? now(),
        ])->save();

        $this->invalidateSessions();
    }

    /*
    | Cabut sesi yang sedang berjalan. Ini HARUS sadar driver: deployment sekolah
    | berjalan dengan SESSION_DRIVER=file (lihat .env.example), sehingga versi lama
    | yang hanya DELETE dari tabel `sessions` tidak mencabut apa pun — endpoint
    | revoke membalas 200 sementara sesi penyalahguna tetap hidup.
    |
    | Driver tanpa indeks per-user (redis/memcached/cookie/array) tidak bisa disapu
    | dari sini; di sana EnforceDemoAccess tetap jadi jaring pengaman pada request
    | web berikutnya, dan remember-token sudah dinolkan di bawah untuk semua driver.
    */
    public function invalidateSessions(): void
    {
        $users = $this->users()->get(['uuid', 'remember_token']);
        if ($users->isEmpty()) {
            return;
        }

        $userIds = $users->pluck('uuid')->map(fn ($id) => (string) $id)->all();

        // Login "ingat saya" bertahan melewati penghapusan sesi — matikan lebih dulu,
        // dan ini berlaku di semua driver.
        DB::table((new User)->getTable())->whereIn('uuid', $userIds)->update(['remember_token' => null]);

        match ((string) config('session.driver')) {
            'database' => $this->purgeDatabaseSessions($userIds),
            'file' => $this->purgeFileSessions($userIds),
            default => null,
        };
    }

    /** @param  list<string>  $userIds */
    private function purgeDatabaseSessions(array $userIds): void
    {
        $table = (string) config('session.table', 'sessions');
        $connection = config('session.connection');

        if (! Schema::connection($connection)->hasTable($table)) {
            return;
        }

        DB::connection($connection)->table($table)->whereIn('user_id', $userIds)->delete();
    }

    /**
     * Driver file tidak punya indeks user → satu-satunya cara adalah memindai
     * direktori sesi. Akun demo jumlahnya kecil dan pemanggilnya jarang (revoke /
     * transisi kedaluwarsa, digerbangi cache di EnforceDemoAccess), jadi biaya
     * pemindaian ditanggung di jalur administratif, bukan jalur request normal.
     *
     * @param  list<string>  $userIds
     */
    private function purgeFileSessions(array $userIds): void
    {
        $dir = (string) config('session.files');
        if ($dir === '' || ! is_dir($dir)) {
            return;
        }

        $files = glob(rtrim($dir, '/').'/*');
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (! is_file($file)) {
                continue;
            }

            $payload = @file_get_contents($file);
            if ($payload === false || $payload === '') {
                continue;
            }

            // Payload berisi id user pada key login_web_* (terenkripsi bila
            // SESSION_ENCRYPT=true — di kasus itu pencocokan gagal dan kita jatuh ke
            // jaring pengaman EnforceDemoAccess, bukan menghapus sesi orang lain).
            foreach ($userIds as $id) {
                if ($id !== '' && str_contains($payload, $id)) {
                    if (! @unlink($file)) {
                        Log::warning('demo.session.purge-failed', ['file' => basename($file)]);
                    }
                    break;
                }
            }
        }
    }
}
