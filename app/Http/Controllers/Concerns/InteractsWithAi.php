<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AiUsageLog;
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
            'status' => $status,
            'status_label' => match ($status) {
                'locked' => 'Kuota gratis habis',
                'exhausted' => 'Batas harian tercapai',
                'warning' => 'Kuota mulai menipis',
                default => 'Kuota tersedia',
            },
            'message' => match ($status) {
                'locked' => 'Sistem menunggu reset free tier sebelum mencoba Gemini lagi.',
                'exhausted' => 'Estimasi batas harian model gratis sudah tercapai. Coba lagi setelah reset.',
                'warning' => 'Pemakaian hari ini sudah tinggi. Pakai seperlunya sampai reset berikutnya.',
                default => 'Pemakaian hari ini masih tersedia untuk model gratis yang dikonfigurasi.',
            },
            'notice' => 'Angka ini estimasi dari log SIMS. Kuota resmi dihitung Google per project, bukan per guru/API key.',
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
        ];
    }

    /** @return string[] */
    private function aiModelChain(): array
    {
        $chain = array_merge([config('ai.model')], (array) config('ai.fallback_models', []));

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
    private function aiFreeTierQuotaCacheKey(array $modelChain): string
    {
        return 'ai:gemini:free-tier-quota-exhausted:'.sha1((string) config('ai.api_key').'|'.implode('|', $modelChain));
    }
}