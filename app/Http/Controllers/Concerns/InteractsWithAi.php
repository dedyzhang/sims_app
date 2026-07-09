<?php

namespace App\Http\Controllers\Concerns;

use App\Models\AiUsageLog;
use Illuminate\Http\JsonResponse;
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
}
