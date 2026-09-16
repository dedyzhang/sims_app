<?php

namespace App\Services;

use App\Models\DemoAccess;
use App\Models\DemoProvisioningEvent;
use App\Models\Guru;
use App\Models\User;
use App\Notifications\DemoOnboardingNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class DemoProvisioningService
{
    public function __construct(private readonly DemoCallbackClient $callbacks) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}
     */
    public function provision(array $payload, string $idempotencyKey, string $bodyHash, string $correlationId): array
    {
        $validation = $this->validate($payload);
        if ($validation !== null) {
            return $validation;
        }

        $sourceRequestId = (string) $payload['source_request_id'];
        $accounts = $this->normalizedAccounts($payload['accounts']);
        $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];
        $email = strtolower(trim((string) ($contact['email'] ?? '')));
        $name = trim((string) ($contact['name'] ?? ''));
        $durationHours = (int) $payload['duration_hours'];
        $plainPasswords = [];

        $result = DB::transaction(function () use (
            $payload,
            $idempotencyKey,
            $bodyHash,
            $correlationId,
            $sourceRequestId,
            $accounts,
            $email,
            $name,
            $durationHours,
            &$plainPasswords,
        ) {
            $event = DemoProvisioningEvent::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($event) {
                if (! hash_equals($event->body_hash, $bodyHash)) {
                    $event->forceFill([
                        'status' => DemoProvisioningEvent::STATUS_CONFLICT,
                    ])->save();

                    return [
                        'status' => 409,
                        'body' => $this->error('IDEMPOTENCY_CONFLICT', 'Idempotency key sudah dipakai dengan body berbeda.', $correlationId),
                    ];
                }

                if ($event->status === DemoProvisioningEvent::STATUS_PROCESSED && is_array($event->safe_metadata)) {
                    return [
                        'status' => 200,
                        'body' => $event->safe_metadata,
                    ];
                }
            }

            $existing = DemoAccess::query()->where('source_request_id', $sourceRequestId)->lockForUpdate()->first();
            if ($existing && $existing->status === DemoAccess::STATUS_ACTIVE) {
                $body = $this->successBody($existing, $correlationId);
                $this->storeEvent($event, $existing, $sourceRequestId, $idempotencyKey, $bodyHash, $correlationId, $body, 200);

                return ['status' => 200, 'body' => $body];
            }

            // Pencabutan bersifat FINAL. Tanpa gerbang ini, POST ulang dengan
            // source_request_id yang sama + idempotency key baru akan menghidupkan
            // kembali demo yang sengaja dimatikan (jendela waktu & akun baru).
            // Demo baru harus datang dengan source_request_id baru.
            if ($existing && $existing->status === DemoAccess::STATUS_REVOKED) {
                return [
                    'status' => 409,
                    'body' => $this->error('DEMO_ACCESS_REVOKED', 'Akses demo untuk permintaan ini sudah dicabut.', $correlationId),
                ];
            }

            if (! $event) {
                $event = new DemoProvisioningEvent;
                $event->forceFill([
                    'source_request_id' => $sourceRequestId,
                    'idempotency_key' => $idempotencyKey,
                    'body_hash' => $bodyHash,
                    'event_type' => 'provision',
                    'status' => DemoProvisioningEvent::STATUS_RECEIVED,
                ]);
                $event->save();
            }

            $starts = now();
            $access = $existing ?? new DemoAccess;
            $access->forceFill([
                'source_request_id' => $sourceRequestId,
                'school_display_name' => (string) config('demo.school_display_name'),
                'contact_name' => $name,
                'contact_email_encrypted' => Crypt::encryptString($email),
                'contact_email_hash' => DemoAccess::emailHash($email),
                'status' => DemoAccess::STATUS_ACTIVE,
                'starts_at' => $starts,
                'expires_at' => $starts->copy()->addHours($durationHours),
                'approved_actor' => (string) ($payload['approved_by'] ?? ''),
                'correlation_id' => $correlationId,
                'metadata' => [
                    'focus_modules' => is_array($payload['focus_modules'] ?? null) ? array_values($payload['focus_modules']) : [],
                    'duration_hours' => $durationHours,
                ],
            ])->save();

            if ($existing) {
                $access->users()->get()->each(function (User $user) {
                    $user->guru()?->delete();
                    $user->delete();
                });
            }

            $index = 1;
            $suffix = substr(str_replace('-', '', $sourceRequestId), 0, 8);
            foreach ($accounts as $account) {
                for ($n = 1; $n <= $account['count']; $n++, $index++) {
                    $password = Str::password(12, symbols: false);
                    $username = sprintf('demo.%s.%d.%s', $account['role'], $n, $suffix);
                    while (User::query()->where('username', $username)->exists()) {
                        $username = sprintf('demo.%s.%d.%s', $account['role'], $n, Str::lower(Str::random(6)));
                    }

                    $user = new User;
                    $user->forceFill([
                        'username' => $username,
                        'password' => $password,
                        'access' => $account['role'],
                        'demo_access_id' => $access->id,
                        'must_change_password' => false,
                        'username_customized' => true,
                    ])->save();

                    Guru::query()->create([
                        'id_login' => $user->uuid,
                        'nama' => sprintf('Demo %s %d', ucfirst($account['role']), $n),
                        'nik' => sprintf('D%s%02d', $suffix, $index),
                        'jk' => 'L',
                    ]);

                    $plainPasswords[] = [
                        'role' => $account['role'],
                        'username' => $username,
                        'password' => $password,
                    ];
                }
            }

            $body = $this->successBody($access->fresh(), $correlationId, 201);
            $this->storeEvent($event, $access, $sourceRequestId, $idempotencyKey, $bodyHash, $correlationId, $body, 201);

            return ['status' => 201, 'body' => $body, 'access_id' => $access->id];
        });

        if (($result['status'] ?? 0) === 201 && isset($result['access_id'])) {
            $access = DemoAccess::query()->find($result['access_id']);
            if ($access) {
                $this->sendOnboarding($access, $plainPasswords, false);
                $this->callbacks->notify($access, 'provision.ready');
            }
            unset($result['access_id']);
        }

        return $result;
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function revoke(string $demoAccessId, string $correlationId): array
    {
        $access = DemoAccess::query()->lockForUpdate()->find($demoAccessId);
        if (! $access) {
            return [
                'status' => 422,
                'body' => $this->error('DEMO_REQUEST_INVALID', 'Akses demo tidak ditemukan.', $correlationId),
            ];
        }

        $access->markRevokedAndInvalidateSessions();
        DB::afterCommit(fn () => $this->callbacks->notify($access->fresh() ?? $access, 'access.revoked'));

        return [
            'status' => 200,
            'body' => [
                'data' => [
                    'demo_access_id' => $access->id,
                    'status' => DemoAccess::STATUS_REVOKED,
                ],
                'correlation_id' => $correlationId,
            ],
        ];
    }

    /**
     * @return array{status: int, body: array<string, mixed>}
     */
    public function resendOnboarding(string $demoAccessId, string $correlationId): array
    {
        $access = DemoAccess::query()->find($demoAccessId);
        if (! $access || ! $access->isCurrentlyActive()) {
            return [
                'status' => 422,
                'body' => $this->error('DEMO_REQUEST_INVALID', 'Akses demo tidak aktif.', $correlationId),
            ];
        }

        // Kunci per-akses: dua resend paralel sama-sama merotasi password, dan
        // email yg dikirim duluan langsung mati begitu rotasi kedua tersimpan.
        $lock = Cache::lock('demo:resend:'.$access->id, 60);
        if (! $lock->get()) {
            return [
                'status' => 409,
                'body' => $this->error('DEMO_RESEND_IN_PROGRESS', 'Pengiriman ulang sedang diproses.', $correlationId),
            ];
        }

        try {
            $access->load('users');
            $accounts = [];
            $previousHashes = [];

            foreach ($access->users as $user) {
                $previousHashes[(string) $user->getKey()] = (string) $user->getRawOriginal('password');
                $password = Str::password(12, symbols: false);
                $user->forceFill(['password' => $password])->save();
                $accounts[] = [
                    'role' => (string) $user->access,
                    'username' => (string) $user->username,
                    'password' => $password,
                ];
            }

            if (! $this->sendOnboarding($access, $accounts, true)) {
                // Satu-satunya salinan plaintext password baru ada di email yang gagal
                // terkirim. Tanpa rollback ini, setiap akun demo terkunci permanen —
                // dan pemanggil tak punya sinyal apa pun kalau kita balas 200/"sent".
                $this->restorePasswords($access, $previousHashes);

                return [
                    'status' => 502,
                    'body' => $this->error('DEMO_ONBOARDING_FAILED', 'Email onboarding gagal dikirim; kredensial lama tetap berlaku.', $correlationId),
                ];
            }

            return [
                'status' => 200,
                'body' => [
                    'data' => [
                        'demo_access_id' => $access->id,
                        'onboarding_delivery' => $access->fresh()?->onboarding_sent_at ? 'sent' : 'queued',
                    ],
                    'correlation_id' => $correlationId,
                ],
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * Tulis hash lama apa adanya lewat query builder — cast 'hashed' di User akan
     * menghash-ulang nilai yang sudah berupa hash pada sebagian konfigurasi.
     *
     * @param  array<string, string>  $hashes
     */
    private function restorePasswords(DemoAccess $access, array $hashes): void
    {
        foreach ($access->users as $user) {
            $key = (string) $user->getKey();
            if (($hashes[$key] ?? '') === '') {
                continue;
            }

            DB::table($user->getTable())
                ->where($user->getKeyName(), $user->getKey())
                ->update(['password' => $hashes[$key]]);
        }
    }

    /**
     * @param  list<array{role: string, username: string, password: string}>  $accounts
     */
    private function sendOnboarding(DemoAccess $access, array $accounts, bool $rotated): bool
    {
        $email = $access->contactEmail();
        if ($email === '' || $accounts === []) {
            return false;
        }

        try {
            Notification::route('mail', $email)->notify(new DemoOnboardingNotification(
                $access->school_display_name,
                rtrim((string) config('app.url'), '/').'/login',
                $access->expires_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-',
                $accounts,
                $rotated,
            ));
            $access->forceFill(['onboarding_sent_at' => now()])->save();
            $this->callbacks->notify($access, 'onboarding.sent');

            return true;
        } catch (\Throwable $e) {
            Log::warning('demo.onboarding.failed', [
                'demo_access_id' => $access->id,
                'rotated' => $rotated,
                'exception' => $e->getMessage(),
            ]);
            $this->callbacks->notify($access, 'onboarding.failed');

            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: int, body: array<string, mixed>}|null
     */
    private function validate(array $payload): ?array
    {
        $roles = config('demo.limits.roles', ['kepala', 'admin', 'guru']);
        $minHours = (int) config('demo.limits.min_duration_hours', 24);
        $maxHours = (int) config('demo.limits.max_duration_hours', 2160);
        $maxAccounts = (int) config('demo.limits.max_accounts', 10);
        $duration = (int) ($payload['duration_hours'] ?? 0);
        $source = (string) ($payload['source_request_id'] ?? '');
        $contact = is_array($payload['contact'] ?? null) ? $payload['contact'] : [];
        $email = (string) ($contact['email'] ?? '');
        $name = trim((string) ($contact['name'] ?? ''));
        $accounts = $payload['accounts'] ?? null;

        if (! Str::isUuid($source) || $name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['status' => 422, 'body' => $this->error('DEMO_REQUEST_INVALID', 'Permintaan akun tidak valid.', (string) ($payload['correlation_id'] ?? ''))];
        }

        if ($duration < $minHours || $duration > $maxHours || $duration % 24 !== 0) {
            return ['status' => 422, 'body' => $this->error('DEMO_REQUEST_INVALID', 'Durasi akses tidak valid.', '')];
        }

        if (! is_array($accounts) || $accounts === []) {
            return ['status' => 422, 'body' => $this->error('DEMO_REQUEST_INVALID', 'Daftar akun wajib diisi.', '')];
        }

        $total = 0;
        foreach ($accounts as $account) {
            if (! is_array($account)) {
                return ['status' => 422, 'body' => $this->error('DEMO_ROLE_INVALID', 'Permintaan akun tidak valid.', '')];
            }
            $role = (string) ($account['role'] ?? '');
            $count = (int) ($account['count'] ?? 0);
            if (! in_array($role, $roles, true) || $count < 1) {
                return ['status' => 422, 'body' => $this->error('DEMO_ROLE_INVALID', 'Permintaan akun tidak valid.', '')];
            }
            $total += $count;
        }

        if ($total < 1 || $total > $maxAccounts) {
            return ['status' => 422, 'body' => $this->error('DEMO_REQUEST_INVALID', 'Jumlah akun di luar batas.', '')];
        }

        return null;
    }

    /**
     * @param  list<array{role: string, count: int|string}>  $accounts
     * @return list<array{role: string, count: int}>
     */
    private function normalizedAccounts(array $accounts): array
    {
        $normalized = [];
        foreach ($accounts as $account) {
            $normalized[] = [
                'role' => (string) $account['role'],
                'count' => (int) $account['count'],
            ];
        }

        return $normalized;
    }

    private function successBody(DemoAccess $access, string $correlationId, int $http = 200): array
    {
        return [
            'data' => [
                'demo_access_id' => $access->id,
                'status' => $access->status,
                'starts_at' => $access->starts_at?->utc()->toIso8601String(),
                'expires_at' => $access->expires_at?->utc()->toIso8601String(),
                'account_count' => $access->users()->count(),
                'onboarding_delivery' => $access->onboarding_sent_at ? 'sent' : 'queued',
            ],
            'correlation_id' => $correlationId,
        ];
    }

    private function storeEvent(
        ?DemoProvisioningEvent $event,
        DemoAccess $access,
        string $sourceRequestId,
        string $idempotencyKey,
        string $bodyHash,
        string $correlationId,
        array $body,
        int $code,
    ): void {
        $record = $event ?? new DemoProvisioningEvent;
        $record->forceFill([
            'demo_access_id' => $access->id,
            'source_request_id' => $sourceRequestId,
            'idempotency_key' => $idempotencyKey,
            'body_hash' => $bodyHash,
            'event_type' => 'provision',
            'status' => DemoProvisioningEvent::STATUS_PROCESSED,
            'response_code' => $code,
            'safe_metadata' => $body,
            'processed_at' => now(),
        ])->save();
    }

    /**
     * @return array{error: array{code: string, message: string, correlation_id: string}}
     */
    private function error(string $code, string $message, string $correlationId): array
    {
        return [
            'error' => [
                'code' => $code,
                'message' => $message,
                'correlation_id' => $correlationId,
            ],
        ];
    }
}
