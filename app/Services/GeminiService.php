<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/*
| Gateway terpusat ke Google Gemini. SEMUA fitur AI SIMS memanggil kelas ini —
| key ditambahkan di server, tak pernah sampai ke browser. Meniru pola
| FcmService: ada enabled() guard supaya bila key belum diisi, fitur AI mati
| diam-diam alih-alih melempar error keras.
|
| generate() mengembalikan array ternormalisasi:
|   ['text' => string, 'model' => string, 'prompt_tokens' => int, 'completion_tokens' => int]
| dan melempar RuntimeException berpesan Bahasa Indonesia bila gagal.
*/
class GeminiService
{
    /** AI aktif hanya bila GEMINI_API_KEY terisi. */
    public function enabled(): bool
    {
        return !empty(config('ai.api_key'));
    }

    /**
     * Hasilkan teks dari Gemini.
     *
     * @param  string  $prompt   Pesan/konteks dari pengguna (sudah dibangun controller).
     * @param  array   $options  system, model, temperature, max_output_tokens, history
     * @return array{text:string,model:string,prompt_tokens:int,completion_tokens:int}
     */
    public function generate(string $prompt, array $options = []): array
    {
        if (!$this->enabled()) {
            throw new RuntimeException('Fitur AI belum dikonfigurasi (GEMINI_API_KEY kosong).');
        }

        $model  = $options['model'] ?? config('ai.model');
        $system = trim(($options['system'] ?? '')."\n\n".config('ai.system_prompt'));

        $body = [
            'systemInstruction' => [
                'parts' => [['text' => $system]],
            ],
            'contents' => $this->buildContents($prompt, $options['history'] ?? []),
            'generationConfig' => [
                'temperature'     => $options['temperature'] ?? config('ai.temperature'),
                'maxOutputTokens' => $options['max_output_tokens'] ?? config('ai.max_output_tokens'),
            ],
        ];

        // Grounding Google Search: biarkan model mencari di web & menautkan sumber.
        if (!empty($options['grounding'])) {
            $body['tools'] = [$this->groundingTool($model)];
        }

        $url = rtrim(config('ai.base_url'), '/')."/models/{$model}:generateContent";

        try {
            $response = Http::timeout(config('ai.timeout'))
                ->retry(config('ai.retries'), config('ai.retry_delay'), throw: false)
                ->withQueryParameters(['key' => config('ai.api_key')])
                ->acceptJson()
                ->post($url, $body);
        } catch (\Throwable $e) {
            throw new RuntimeException('Gagal menghubungi layanan AI. Coba lagi beberapa saat.');
        }

        if ($response->failed()) {
            throw new RuntimeException($this->normalizeError($response->status(), $response->json()));
        }

        return $this->parse($response->json(), $model);
    }

    /**
     * Hasilkan vektor embedding untuk satu teks (FASE 5 — RAG).
     *
     * @return float[]  Vektor embedding.
     */
    public function embed(string $text): array
    {
        if (!$this->enabled()) {
            throw new RuntimeException('Fitur AI belum dikonfigurasi (GEMINI_API_KEY kosong).');
        }

        $model = config('ai.rag.embed_model');
        $url = rtrim(config('ai.base_url'), '/')."/models/{$model}:embedContent";

        try {
            $response = Http::timeout(config('ai.timeout'))
                ->retry(config('ai.retries'), config('ai.retry_delay'), throw: false)
                ->withQueryParameters(['key' => config('ai.api_key')])
                ->acceptJson()
                ->post($url, [
                    'model'   => "models/{$model}",
                    'content' => ['parts' => [['text' => $text]]],
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Gagal menghubungi layanan embedding AI.');
        }

        if ($response->failed()) {
            throw new RuntimeException($this->normalizeError($response->status(), $response->json()));
        }

        $values = $response->json('embedding.values');
        if (!is_array($values) || $values === []) {
            throw new RuntimeException('Layanan embedding tidak mengembalikan vektor.');
        }

        return array_map('floatval', $values);
    }

    /** Susun contents Gemini: riwayat opsional + giliran user terakhir. */
    private function buildContents(string $prompt, array $history): array
    {
        $contents = [];

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? 'user') === 'assistant' ? 'model' : 'user';
            $text = (string) ($turn['text'] ?? $turn['content'] ?? '');
            if ($text !== '') {
                $contents[] = ['role' => $role, 'parts' => [['text' => $text]]];
            }
        }

        $contents[] = ['role' => 'user', 'parts' => [['text' => $prompt]]];

        return $contents;
    }

    /** Ambil teks + hitungan token dari respons; deteksi jawaban yang diblokir. */
    private function parse(array $json, string $model): array
    {
        $candidate    = $json['candidates'][0] ?? null;
        $finishReason = $candidate['finishReason'] ?? null;

        if ($finishReason === 'SAFETY' || $finishReason === 'PROHIBITED_CONTENT') {
            throw new RuntimeException('Permintaan diblokir oleh filter keamanan AI. Ubah pertanyaanmu.');
        }

        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            $text .= $part['text'] ?? '';
        }

        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('AI tidak mengembalikan jawaban. Coba lagi.');
        }

        $usage = $json['usageMetadata'] ?? [];

        return [
            'text'              => $text,
            'model'             => $model,
            'prompt_tokens'     => (int) ($usage['promptTokenCount'] ?? 0),
            'completion_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
            'sources'           => $this->extractSources($candidate),
        ];
    }

    /**
     * Tool grounding sesuai versi model: Gemini 2.0+ pakai `google_search`,
     * Gemini 1.5/1.0 memakai `google_search_retrieval`. Objek kosong (stdClass)
     * agar ter-encode `{}` bukan `[]` di JSON.
     */
    private function groundingTool(string $model): array
    {
        if (str_contains($model, '1.5') || str_contains($model, '1.0')) {
            return ['google_search_retrieval' => new \stdClass()];
        }

        return ['google_search' => new \stdClass()];
    }

    /**
     * Ambil daftar sumber (judul + URL) dari groundingMetadata bila model
     * memakai hasil pencarian. Ter-dedup per URL. Kosong bila tak ada grounding.
     *
     * @return array<int,array{title:string,uri:string}>
     */
    private function extractSources(?array $candidate): array
    {
        $chunks = $candidate['groundingMetadata']['groundingChunks'] ?? [];
        $sources = [];

        foreach ($chunks as $chunk) {
            $uri = $chunk['web']['uri'] ?? null;
            if (!$uri || isset($sources[$uri])) {
                continue;
            }
            $sources[$uri] = [
                'title' => (string) ($chunk['web']['title'] ?? $uri),
                'uri'   => $uri,
            ];
        }

        return array_values($sources);
    }

    /** Terjemahkan status HTTP Gemini jadi pesan Bahasa Indonesia yang aman. */
    private function normalizeError(int $status, ?array $json): string
    {
        $detail = $json['error']['message'] ?? '';

        return match (true) {
            $status === 429             => 'Kuota AI sedang penuh. Coba lagi sebentar lagi.',
            $status === 400             => 'Permintaan ke AI tidak valid.'.($detail ? " ({$detail})" : ''),
            $status === 401,
            $status === 403             => 'Konfigurasi AI bermasalah (kredensial ditolak).',
            $status >= 500              => 'Layanan AI sedang gangguan. Coba lagi nanti.',
            default                     => 'Terjadi kesalahan saat memproses permintaan AI.',
        };
    }
}
