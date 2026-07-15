<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AiUsageLog;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/*
| Perkakas bersama semua controller AI (FASE 1+): guard biaya (rate limit
| per user per fitur) + audit ke ai_usage_logs. Dipakai AiController,
| AiChatController, dan fitur AI berikutnya.
*/
trait InteractsWithAi
{
    /**
     * Cek & tambah hitungan rate limit per user per fitur.
     * @return JsonResponse|null  Response 429 bila lewat batas; null bila boleh lanjut.
     */
    protected function aiRateLimited(string $feature, ?string $userId): ?JsonResponse
    {
        $key = "ai:{$feature}:{$userId}";
        $max = (int) config('ai.rate_limit');

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $this->logAiUsage($userId, $feature, config('ai.model'), 0, 0, 'rate_limited');

            return response()->json([
                'ok'      => false,
                'message' => 'Terlalu banyak permintaan AI. Tunggu '
                    .RateLimiter::availableIn($key).' detik lalu coba lagi.',
            ], 429);
        }

        RateLimiter::increment($key, 60);

        return null;
    }

    /** Simpan satu baris audit; gagal-diam agar tak menjatuhkan response utama. */
    protected function logAiUsage(?string $userId, string $feature, ?string $model, int $promptTokens, int $completionTokens, string $status): void
    {
        try {
            AiUsageLog::create([
                'user_uuid'         => $userId,
                'feature'           => $feature,
                'model'             => $model,
                'prompt_tokens'     => $promptTokens,
                'completion_tokens' => $completionTokens,
                'status'            => $status,
            ]);
        } catch (\Throwable) {
            // Audit tak boleh menggagalkan fitur; abaikan bila gagal tulis.
        }
    }

    /**
     * Snapshot pemakaian free tier hari ini berdasarkan log SIMS.
     * Google menghitung kuota per project, jadi angka ini sengaja global, bukan per guru.
     *
     * @return array<string,mixed>
     */
    protected function aiFreeTierUsage(): array
    {
        if ($this->aiActiveProvider() === 'openrouter') {
            return $this->aiOpenRouterTierUsage(false);
        }

        return $this->aiGeminiTierUsage();
    }

    /** @return array<string,mixed> */
    protected function aiPublicQuotaUsage(bool $fresh = false): array
    {
        return match ($this->aiActiveProvider()) {
            'openrouter' => $this->aiOpenRouterPublicQuota($fresh),
            'ninerouter' => $this->aiNinerouterPublicQuota($fresh),
            default => $this->aiGeminiPublicQuota(),
        };
    }

    private function aiActiveProvider(): string
    {
        return strtolower((string) config('ai.provider', 'gemini'));
    }

    /** @return array<string,mixed> */
    private function aiGeminiTierUsage(): array
    {
        $models = $this->aiModelChain();
        $limits = (array) config('ai.free_tier_daily_limits', []);
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $todayPacific = CarbonImmutable::now('America/Los_Angeles')->startOfDay();
        $resetPacific = $todayPacific->addDay();

        $dayStartForDatabase = $todayPacific->setTimezone($timezone);
        $dayEndForDatabase = $resetPacific->setTimezone($timezone);

        $usage = AiUsageLog::query()
            ->selectRaw('model, COUNT(*) as request_count, COALESCE(SUM(prompt_tokens), 0) as prompt_tokens, COALESCE(SUM(completion_tokens), 0) as completion_tokens')
            ->where('status', 'success')
            ->whereNotNull('model')
            ->whereIn('model', $models)
            ->where('created_at', '>=', $dayStartForDatabase)
            ->where('created_at', '<', $dayEndForDatabase)
            ->groupBy('model')
            ->get()
            ->keyBy('model');

        $items = [];
        $totalUsed = 0;
        $totalLimit = 0;
        $knownLimitModels = 0;
        $exhaustedKnownModels = 0;

        foreach ($models as $model) {
            $row = $usage->get($model);
            $used = (int) ($row->request_count ?? 0);
            $limit = isset($limits[$model]) ? (int) $limits[$model] : null;
            $remaining = $limit !== null ? max(0, $limit - $used) : null;
            $percent = $limit !== null && $limit > 0 ? min(100, (int) floor(($used / $limit) * 100)) : 0;
            $status = match (true) {
                $limit !== null && $used >= $limit => 'exhausted',
                $limit !== null && $percent >= 80 => 'warning',
                default => 'ok',
            };

            if ($limit !== null) {
                $knownLimitModels++;
                $totalLimit += $limit;
                if ($used >= $limit) {
                    $exhaustedKnownModels++;
                }
            }

            $totalUsed += $used;
            $items[] = [
                'model' => $model,
                'used' => $used,
                'limit' => $limit,
                'remaining' => $remaining,
                'percent' => $percent,
                'status' => $status,
                'prompt_tokens' => (int) ($row->prompt_tokens ?? 0),
                'completion_tokens' => (int) ($row->completion_tokens ?? 0),
            ];
        }

        $lockedResetAt = $this->aiFreeTierLockedResetAt($models);
        $totalPercent = $totalLimit > 0 ? min(100, (int) floor(($totalUsed / $totalLimit) * 100)) : 0;
        $allKnownModelsExhausted = $knownLimitModels > 0 && $knownLimitModels === count($models) && $exhaustedKnownModels === $knownLimitModels;
        $status = match (true) {
            $lockedResetAt !== null => 'locked',
            $allKnownModelsExhausted => 'exhausted',
            $totalPercent >= 80 => 'warning',
            default => 'ok',
        };

        $resetAt = $lockedResetAt ?? $resetPacific;
        $resetLocal = $resetAt->setTimezone($timezone);

        return [
            'enabled' => (bool) config('ai.free_tier_only', true),
            'provider' => 'gemini',
            'live' => false,
            'status' => $status,
            'status_label' => match ($status) {
                'locked' => 'Kuota gratis habis',
                'exhausted' => 'Batas harian tercapai',
                'warning' => 'Kuota mulai menipis',
                default => 'Kuota tersedia',
            },
            'message' => match ($status) {
                'locked' => 'Sistem menunggu reset free tier sebelum mencoba Asisten Guru lagi.',
                'exhausted' => 'Estimasi batas harian model gratis sudah tercapai. Coba lagi setelah reset.',
                'warning' => 'Pemakaian hari ini sudah tinggi. Pakai seperlunya sampai reset berikutnya.',
                default => 'Pemakaian hari ini masih tersedia untuk model gratis yang dikonfigurasi.',
            },
            'notice' => 'Angka ini estimasi dari log SIMS.',
            'reset_at' => $resetAt->toIso8601String(),
            'reset_at_human' => $resetLocal->format('d/m/Y H:i T'),
            'day_start' => $todayPacific->toIso8601String(),
            'day_start_human' => $todayPacific->setTimezone($timezone)->format('d/m/Y H:i T'),
            'total' => [
                'used' => $totalUsed,
                'limit' => $totalLimit > 0 ? $totalLimit : null,
                'remaining' => $totalLimit > 0 ? max(0, $totalLimit - $totalUsed) : null,
                'percent' => $totalPercent,
            ],
            'models' => $items,
            'key' => null,
        ];
    }

    /** @return array<string,mixed> */
    private function aiOpenRouterTierUsage(bool $fresh = false): array
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $todayUtc = CarbonImmutable::now('UTC')->startOfDay();
        $resetUtc = $todayUtc->addDay();
        $dayStartLocal = $todayUtc->setTimezone($timezone);
        $dayEndLocal = $resetUtc->setTimezone($timezone);

        $models = $this->aiOpenRouterModelChain();
        $limit = max(1, (int) config('ai.openrouter.free_daily_limit', 50));

        // openrouter/free merutekan ke model :free aktual — hitung semua sukses hari ini
        // yang modelnya openrouter/free atau berakhiran :free, plus rantai yang dikonfigurasi.
        $used = (int) AiUsageLog::query()
            ->where('status', 'success')
            ->where(function ($q) use ($models) {
                $q->whereIn('model', $models)
                    ->orWhere('model', 'like', '%:free')
                    ->orWhere('model', 'like', 'openrouter/%');
            })
            ->where('created_at', '>=', $dayStartLocal)
            ->where('created_at', '<', $dayEndLocal)
            ->count();

        $gemini = app(GeminiService::class);
        $key = $gemini->openRouterKeyStatus($fresh);

        $lockedResetAt = $this->aiOpenRouterLockedResetAt($models);
        $remaining = max(0, $limit - $used);
        $percent = min(100, (int) floor(($used / $limit) * 100));

        $status = match (true) {
            ! ($key['alive'] ?? false) => 'error',
            $lockedResetAt !== null => 'locked',
            $used >= $limit => 'exhausted',
            ($key['limit_remaining'] ?? null) !== null && (float) $key['limit_remaining'] <= 0 => 'exhausted',
            $percent >= 80 => 'warning',
            default => 'ok',
        };

        $resetAt = $lockedResetAt ?? $resetUtc;
        $resetLocal = $resetAt->setTimezone($timezone);

        return [
            'enabled' => (bool) config('ai.openrouter.free_only', true),
            'provider' => 'openrouter',
            'live' => true,
            'status' => $status,
            'status_label' => match ($status) {
                'error' => 'Key Asisten Guru bermasalah',
                'locked' => 'Kuota gratis Asisten Guru terkunci',
                'exhausted' => 'Batas harian tercapai',
                'warning' => 'Kuota mulai menipis',
                default => 'Kuota live tersedia',
            },
            'message' => match ($status) {
                'error' => $key['message'] ?? 'API key Asisten Guru tidak bisa dipakai.',
                'locked' => 'Sistem menunggu reset free tier Asisten Guru.',
                'exhausted' => 'Batas request gratis hari ini sudah tercapai. Coba lagi setelah reset UTC.',
                'warning' => 'Pemakaian hari ini sudah tinggi. Pakai seperlunya sampai reset berikutnya.',
                default => 'Status live Asisten Guru + pemakaian dari log SIMS.',
            },
            'notice' => 'Live dari status key Asisten Guru. Request tersisa dihitung dari log SIMS vs batas harian free.',
            'reset_at' => $resetAt->toIso8601String(),
            'reset_at_human' => $resetLocal->format('d/m/Y H:i T'),
            'day_start' => $todayUtc->toIso8601String(),
            'day_start_human' => $dayStartLocal->format('d/m/Y H:i T'),
            'total' => [
                'used' => $used,
                'limit' => $limit,
                'remaining' => $remaining,
                'percent' => $percent,
            ],
            'models' => [],
            'key' => $key,
        ];
    }

    /** @return array<string,mixed> */
    private function aiGeminiPublicQuota(): array
    {
        $quota = $this->aiGeminiTierUsage();
        $status = $quota['status'] ?? 'ok';
        $remaining = $quota['total']['remaining'] ?? null;
        $limit = $quota['total']['limit'] ?? null;

        if (in_array($status, ['locked', 'exhausted'], true)) {
            $remaining = 0;
        }

        $remainingPercent = $remaining !== null && $limit
            ? max(0, min(100, (int) floor(($remaining / $limit) * 100)))
            : null;
        $remainingLabel = match ($status) {
            'locked' => 'Kuota gratis habis — tunggu reset',
            'exhausted' => 'Batas harian tercapai',
            default => $remaining !== null
                ? number_format((int) $remaining, 0, ',', '.').' request tersisa'
                : 'Sisa kuota tidak diketahui',
        };

        return [
            'enabled' => $quota['enabled'] ?? true,
            'provider' => 'gemini',
            'live' => false,
            'status' => $status,
            'status_label' => $quota['status_label'] ?? null,
            'message' => $quota['message'] ?? null,
            'reset_at' => $quota['reset_at'] ?? null,
            'reset_at_human' => $quota['reset_at_human'] ?? '-',
            'day_start' => $quota['day_start'] ?? null,
            'day_start_human' => $quota['day_start_human'] ?? null,
            'remaining' => $remaining,
            'remaining_percent' => $remainingPercent,
            'remaining_label' => $remainingLabel,
            'total' => [
                'used' => $quota['total']['used'] ?? null,
                'remaining' => $remaining,
                'limit' => $limit,
            ],
            'models' => [],
            'can_view_usage' => false,
            'key' => null,
            'credit_remaining' => null,
            'credit_label' => null,
            'updated_at_human' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('H:i:s'),
        ];
    }

    /** @return array<string,mixed> */
    private function aiOpenRouterPublicQuota(bool $fresh = false): array
    {
        $quota = $this->aiOpenRouterTierUsage($fresh);
        $status = $quota['status'] ?? 'ok';
        $remaining = $quota['total']['remaining'] ?? null;
        $limit = $quota['total']['limit'] ?? null;
        $used = $quota['total']['used'] ?? null;
        $key = $quota['key'] ?? [];

        if (in_array($status, ['locked', 'exhausted', 'error'], true) && $status !== 'error') {
            $remaining = 0;
        }

        if ($status === 'error') {
            $remaining = null;
        }

        $remainingPercent = $remaining !== null && $limit
            ? max(0, min(100, (int) floor(($remaining / $limit) * 100)))
            : null;

        $remainingLabel = match ($status) {
            'error' => 'Key Asisten Guru tidak aktif',
            'locked' => 'Kuota gratis habis — tunggu reset',
            'exhausted' => 'Batas harian tercapai',
            default => $remaining !== null
                ? number_format((int) $remaining, 0, ',', '.').' request tersisa'
                : 'Sisa kuota tidak diketahui',
        };

        $creditRemaining = $key['limit_remaining'] ?? null;
        $creditLabel = null;
        if ($creditRemaining !== null) {
            $creditLabel = '$'.number_format((float) $creditRemaining, 4, '.', '').' kredit tersisa';
        } elseif (($key['alive'] ?? false) && ($key['is_free_tier'] ?? false)) {
            $creditLabel = 'Free tier Asisten Guru';
        }

        return [
            'enabled' => $quota['enabled'] ?? true,
            'provider' => 'openrouter',
            'live' => true,
            'status' => $status,
            'status_label' => $quota['status_label'] ?? null,
            'message' => $quota['message'] ?? null,
            'reset_at' => $quota['reset_at'] ?? null,
            'reset_at_human' => $quota['reset_at_human'] ?? '-',
            'day_start' => $quota['day_start'] ?? null,
            'day_start_human' => $quota['day_start_human'] ?? null,
            'remaining' => $remaining,
            'remaining_percent' => $remainingPercent,
            'remaining_label' => $remainingLabel,
            'total' => [
                'used' => $used,
                'remaining' => $remaining,
                'limit' => $limit,
            ],
            'models' => [],
            'can_view_usage' => false,
            'key_alive' => (bool) ($key['alive'] ?? false),
            'key_status' => $key['status'] ?? null,
            'credit_remaining' => $creditRemaining,
            'credit_label' => $creditLabel,
            'usage_daily_usd' => $key['usage_daily'] ?? null,
            'updated_at_human' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('H:i:s'),
        ];
    }

    /** @return array<string,mixed> */
    private function aiNinerouterPublicQuota(bool $fresh = false): array
    {
        $key = app(GeminiService::class)->nineRouterKeyStatus($fresh);
        $alive = (bool) ($key['alive'] ?? false);
        $status = $alive ? 'ok' : 'error';

        return [
            'enabled' => true,
            'provider' => 'ninerouter',
            'live' => true,
            'status' => $status,
            'status_label' => 'Asisten Guru',
            'message' => null,
            'reset_at' => null,
            'reset_at_human' => '-',
            'day_start' => null,
            'day_start_human' => null,
            'remaining' => null,
            'remaining_percent' => null,
            'remaining_label' => 'Asisten Guru',
            'total' => [
                'used' => null,
                'remaining' => null,
                'limit' => null,
            ],
            'models' => [],
            'can_view_usage' => false,
            'key_alive' => $alive,
            'key_status' => $key['status'] ?? null,
            'credit_remaining' => null,
            'credit_label' => null,
            'usage_daily_usd' => null,
            'updated_at_human' => now()->timezone(config('app.timezone', 'Asia/Jakarta'))->format('H:i:s'),
        ];
    }

    /** @return string[] */
    private function aiModelChain(): array
    {
        return match ($this->aiActiveProvider()) {
            'openrouter' => $this->aiOpenRouterModelChain(),
            'ninerouter' => array_values(array_unique(array_filter(array_map(
                'trim',
                array_merge(
                    [(string) config('ai.ninerouter.model')],
                    (array) config('ai.ninerouter.fallback_models', []),
                ),
            )))),
            default => array_values(array_unique(array_filter(array_map(
                'trim',
                array_merge([config('ai.model')], (array) config('ai.fallback_models', [])),
            )))),
        };
    }

    /** @return string[] */
    private function aiOpenRouterModelChain(): array
    {
        $chain = array_merge(
            [config('ai.openrouter.model')],
            (array) config('ai.openrouter.fallback_models', []),
        );

        return array_values(array_unique(array_filter(array_map('trim', $chain))));
    }

    /** @param string[] $modelChain */
    private function aiFreeTierLockedResetAt(array $modelChain): ?CarbonImmutable
    {
        if (! (bool) config('ai.free_tier_only', true)) {
            return null;
        }

        $resetAt = Cache::get($this->aiFreeTierQuotaCacheKey($modelChain));

        return $resetAt ? CarbonImmutable::parse((string) $resetAt) : null;
    }

    /** @param string[] $modelChain */
    private function aiOpenRouterLockedResetAt(array $modelChain): ?CarbonImmutable
    {
        if (! (bool) config('ai.openrouter.free_only', true)) {
            return null;
        }

        $resetAt = Cache::get(
            'ai:openrouter:free-tier-quota-exhausted:'.sha1((string) config('ai.openrouter.api_key').'|'.implode('|', $modelChain))
        );

        return $resetAt ? CarbonImmutable::parse((string) $resetAt) : null;
    }

    /** @param string[] $modelChain */
    private function aiFreeTierQuotaCacheKey(array $modelChain): string
    {
        return 'ai:gemini:free-tier-quota-exhausted:'.sha1((string) config('ai.api_key').'|'.implode('|', $modelChain));
    }
}