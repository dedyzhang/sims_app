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
     * Satu panggilan HTTP = 1 hit di sini; hop auto-continue tambahan
     * di-charge lewat {@see aiRateLimitChargeExtra} / {@see logAiGenerationUsage}.
     *
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

    /**
     * Charge hit rate-limit tambahan setelah multi-chunk API (auto-continue).
     * Hit pertama sudah dihitung di {@see aiRateLimited}.
     */
    protected function aiRateLimitChargeExtra(string $feature, ?string $userId, int $extraHits): void
    {
        if ($userId === null || $userId === '' || $extraHits <= 0) {
            return;
        }

        $key = "ai:{$feature}:{$userId}";
        RateLimiter::increment($key, 60, $extraHits);
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
     * Catat pemakaian AI per panggilan HTTP (termasuk auto-lanjut MAX_TOKENS).
     * Satu baris log = satu hit API — selaras kuota provider & progress bar guru.
     * Multi-chunk juga men-charge rate-limit per-menit agar 1 aksi ≠ N provider hit gratis.
     *
     * @param  array{model?:string,prompt_tokens?:int,completion_tokens?:int,api_calls?:int,chunks?:list<array{model?:string,prompt_tokens?:int,completion_tokens?:int}>}  $result
     */
    protected function logAiGenerationUsage(?string $userId, string $feature, array $result, string $status): void
    {
        $chunks = $result['chunks'] ?? null;

        if (! is_array($chunks) || $chunks === []) {
            $this->logAiUsage(
                $userId,
                $feature,
                $result['model'] ?? config('ai.model'),
                (int) ($result['prompt_tokens'] ?? 0),
                (int) ($result['completion_tokens'] ?? 0),
                $status,
            );

            return;
        }

        foreach ($chunks as $chunk) {
            $this->logAiUsage(
                $userId,
                $feature,
                $chunk['model'] ?? $result['model'] ?? config('ai.model'),
                (int) ($chunk['prompt_tokens'] ?? 0),
                (int) ($chunk['completion_tokens'] ?? 0),
                $status,
            );
        }

        // aiRateLimited sudah charge 1 hit; hop lanjutan (api_calls-1) ikut dihitung.
        $apiCalls = (int) ($result['api_calls'] ?? count($chunks));
        $this->aiRateLimitChargeExtra($feature, $userId, max(0, $apiCalls - 1));
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

    /**
     * Snapshot kuota untuk UI (polling). Sertakan breakdown AI Studio (key guru)
     * vs AI Sekolah (key server) dari log SIMS — real-time via refresh endpoint.
     *
     * @return array<string,mixed>
     */
    protected function aiPublicQuotaUsage(bool $fresh = false, ?string $userUuid = null): array
    {
        $userUuid = $userUuid ?? (auth()->id() ? (string) auth()->id() : null);

        $base = match ($this->aiActiveProvider()) {
            'openrouter' => $this->aiOpenRouterPublicQuota($fresh),
            'ninerouter' => $this->aiNinerouterPublicQuota($fresh),
            default => $this->aiGeminiPublicQuota(),
        };

        return $this->aiAttachDualUsageBreakdown($base, $userUuid);
    }

    private function aiActiveProvider(): string
    {
        return strtolower((string) config('ai.provider', 'gemini'));
    }

    /**
     * Window hari kuota (selaras provider: Gemini Pacific RPD, OpenRouter UTC).
     *
     * @return array{start: CarbonImmutable, end: CarbonImmutable, reset_human: string}
     */
    private function aiQuotaDayWindow(): array
    {
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $provider = $this->aiActiveProvider();

        if ($provider === 'gemini') {
            $start = CarbonImmutable::now('America/Los_Angeles')->startOfDay();
            $end = $start->addDay();
        } else {
            $start = CarbonImmutable::now('UTC')->startOfDay();
            $end = $start->addDay();
        }

        return [
            'start' => $start->setTimezone($timezone),
            'end' => $end->setTimezone($timezone),
            'reset_human' => $end->setTimezone($timezone)->format('d/m/Y H:i T'),
        ];
    }

    /**
     * Hitung pemakaian harian AI Studio (request sukses user ini) + AI Sekolah (global log).
     *
     * @param  array<string,mixed>  $base
     * @return array<string,mixed>
     */
    private function aiAttachDualUsageBreakdown(array $base, ?string $userUuid): array
    {
        $window = $this->aiQuotaDayWindow();
        $dayStart = $window['start'];
        $dayEnd = $window['end'];

        // AI Studio = pemakaian key pribadi guru (semua hit sukses user di Asisten Guru hari ini).
        $studioUsed = 0;
        if ($userUuid) {
            $studioUsed = (int) AiUsageLog::query()
                ->where('user_uuid', $userUuid)
                ->where('status', 'success')
                ->where('created_at', '>=', $dayStart)
                ->where('created_at', '<', $dayEnd)
                ->count();
        }

        $studioLimit = max(1, (int) config(
            'ai.teacher_studio_daily_limit',
            (int) config('ai.openrouter.free_daily_limit', 50)
        ));
        $studioRemaining = max(0, $studioLimit - $studioUsed);
        $studioPercentUsed = min(100, (int) floor(($studioUsed / $studioLimit) * 100));
        $studioPercentLeft = max(0, 100 - $studioPercentUsed);

        // AI Sekolah = total free-tier / log global yang sudah dihitung di snapshot provider.
        $schoolUsed = $base['total']['used'] ?? null;
        $schoolLimit = $base['total']['limit'] ?? null;
        $schoolRemaining = $base['total']['remaining'] ?? null;

        if ($schoolUsed === null) {
            // Fallback: seluruh request sukses hari ini (provider tanpa limit terstruktur).
            $schoolUsed = (int) AiUsageLog::query()
                ->where('status', 'success')
                ->where('created_at', '>=', $dayStart)
                ->where('created_at', '<', $dayEnd)
                ->count();
        }

        $schoolPercentLeft = null;
        $schoolPercentUsed = null;
        if ($schoolLimit !== null && (int) $schoolLimit > 0) {
            $schoolRemaining = $schoolRemaining ?? max(0, (int) $schoolLimit - (int) $schoolUsed);
            $schoolPercentUsed = min(100, (int) floor(((int) $schoolUsed / (int) $schoolLimit) * 100));
            $schoolPercentLeft = max(0, 100 - $schoolPercentUsed);
        }

        $base['studio'] = [
            'label' => 'AI Studio',
            'hint' => 'Key pribadi Anda (hari ini)',
            'used' => $studioUsed,
            'limit' => $studioLimit,
            'remaining' => $studioRemaining,
            'percent' => $studioPercentLeft,
            'percent_used' => $studioPercentUsed,
            'used_label' => number_format($studioUsed, 0, ',', '.'),
            'limit_label' => number_format($studioLimit, 0, ',', '.'),
            'remaining_label' => number_format($studioRemaining, 0, ',', '.').' tersisa',
        ];

        $base['school'] = [
            'label' => 'AI Sekolah',
            'hint' => 'Key server sekolah (hari ini)',
            'used' => (int) $schoolUsed,
            'limit' => $schoolLimit !== null ? (int) $schoolLimit : null,
            'remaining' => $schoolRemaining !== null ? (int) $schoolRemaining : null,
            'percent' => $schoolPercentLeft,
            'percent_used' => $schoolPercentUsed,
            'used_label' => number_format((int) $schoolUsed, 0, ',', '.'),
            'limit_label' => $schoolLimit !== null ? number_format((int) $schoolLimit, 0, ',', '.') : '—',
            'remaining_label' => $schoolRemaining !== null
                ? number_format((int) $schoolRemaining, 0, ',', '.').' tersisa'
                : number_format((int) $schoolUsed, 0, ',', '.').' dipakai',
        ];

        $base['usage_window'] = [
            'start' => $dayStart->toIso8601String(),
            'end' => $dayEnd->toIso8601String(),
            'reset_human' => $window['reset_human'],
        ];

        // Ringkasan untuk tile: prioritaskan angka dual-usage.
        if (($base['status'] ?? '') !== 'error' && ($base['key_alive'] ?? true) !== false) {
            $base['remaining_label'] = 'Studio '.$base['studio']['remaining_label']
                .' · Sekolah '.$base['school']['remaining_label'];
        }

        return $base;
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