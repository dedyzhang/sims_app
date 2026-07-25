<?php

namespace App\Integrations\Ludensa;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Log;
use Ludensa\Contracts\GeneratesAiJson;

/**
 * Bridge Ludensa → GeminiService SIMS (satu gateway AI / API key).
 */
class SimsGeminiAiJsonGenerator implements GeneratesAiJson
{
    public function __construct(
        protected GeminiService $gemini
    ) {}

    public function tersedia(): bool
    {
        return $this->gemini->enabled();
    }

    public function generateJson(string $prompt, array $options = []): ?array
    {
        if (! $this->tersedia()) {
            return null;
        }

        try {
            $result = $this->gemini->generate($prompt, [
                'system' => trim(
                    (string) config('ai.teacher.quiz', '')
                    ."\n\nKamu HANYA mengembalikan satu objek JSON valid. Tanpa markdown, tanpa ```json, tanpa teks lain."
                ),
                'answer_style' => '',
                'temperature' => $options['temperature'] ?? 0.35,
                'max_output_tokens' => $options['max_output_tokens'] ?? 1024,
                'api_key' => $options['api_key'] ?? null,
            ]);

            return $this->parseJson((string) ($result['text'] ?? ''));
        } catch (\Throwable $e) {
            Log::warning('Ludensa: generate soal via GeminiService gagal', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseJson(string $text): ?array
    {
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        if (preg_match('/```(?:json)?\s*(.*?)\s*```/s', $text, $matches)) {
            $text = trim($matches[1]);
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }
}
