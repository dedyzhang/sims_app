<?php

namespace App\Services;

use App\Exceptions\AiProviderUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/*
| Gateway terpusat ke provider AI. SEMUA fitur AI SIMS memanggil kelas ini —
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
    private const SUPPORTED_PROVIDERS = ['gemini', 'openrouter', 'ninerouter'];

    /** AI aktif bila ada minimal satu provider (utama atau cadangan) yang punya key. */
    public function enabled(): bool
    {
        return $this->providerChain() !== [];
    }

    private function provider(): string
    {
        return strtolower(trim((string) config('ai.provider', 'gemini')));
    }

    /**
     * Urutan provider yang dicoba: provider utama lebih dulu, lalu cadangan dari
     * ai.fallback_providers. Provider tanpa API key dibuang di sini supaya router
     * tidak membuang waktu pada provider yang memang belum dikonfigurasi.
     *
     * @return string[]
     */
    private function providerChain(): array
    {
        $chain = array_merge([$this->provider()], (array) config('ai.fallback_providers', []));
        $chain = array_map(fn ($provider) => strtolower(trim((string) $provider)), $chain);
        $chain = array_values(array_unique(array_filter($chain)));

        return array_values(array_filter($chain, fn (string $provider) => $this->providerConfigured($provider)));
    }

    private function providerConfigured(string $provider): bool
    {
        return match ($provider) {
            'gemini' => ! empty(config('ai.api_key')),
            'openrouter' => ! empty(config('ai.openrouter.api_key')),
            'ninerouter' => ! empty(config('ai.ninerouter.api_key')),
            default => false,
        };
    }

    private function missingConfigurationMessage(): string
    {
        return match ($this->provider()) {
            'openrouter' => 'Fitur AI belum dikonfigurasi (OPENROUTER_API_KEY kosong).',
            'ninerouter' => 'Fitur AI belum dikonfigurasi (NINEROUTER_API_KEY kosong).',
            default => 'Fitur AI belum dikonfigurasi (GEMINI_API_KEY kosong).',
        };
    }

    /**
     * Hasilkan teks dari Gemini.
     *
     * @param  string  $prompt  Pesan/konteks dari pengguna (sudah dibangun controller).
     * @param  array  $options  system, model, temperature, max_output_tokens, history
     * @return array{text:string,model:string,prompt_tokens:int,completion_tokens:int}
     */
    public function generate(string $prompt, array $options = []): array
    {
        // Key pribadi guru (opsi eksplisit): selalu lewat Gemini langsung, tanpa fallback sekolah.
        if (trim((string) ($options['api_key'] ?? '')) !== '') {
            return $this->generateGemini($prompt, $options);
        }

        if (! in_array($this->provider(), self::SUPPORTED_PROVIDERS, true)) {
            throw new RuntimeException('Provider AI tidak dikenali. Gunakan gemini, openrouter, atau ninerouter.');
        }

        $chain = $this->providerChain();

        if ($chain === []) {
            throw new RuntimeException($this->missingConfigurationMessage());
        }

        $failures = [];

        // Router provider: bila provider utama kehabisan kuota / sedang down, pindah ke
        // provider cadangan alih-alih menampilkan error ke guru. Kegagalan lain (mis.
        // konfigurasi salah, prompt ditolak) tidak di-failover — biar cepat ketahuan.
        foreach ($chain as $index => $provider) {
            // Opsi `model` milik provider sebelumnya (mis. nama model Gemini) tidak
            // berlaku di provider cadangan — biarkan cadangan memakai model defaultnya.
            $providerOptions = $index === 0 ? $options : Arr::except($options, ['model']);

            try {
                return match ($provider) {
                    'openrouter' => $this->generateOpenRouter($prompt, $providerOptions),
                    'ninerouter' => $this->generateNinerouter($prompt, $providerOptions),
                    default => $this->generateGemini($prompt, $providerOptions),
                };
            } catch (AiProviderUnavailableException $e) {
                $failures[$provider] = $e->getMessage();

                Log::warning('Provider AI tidak tersedia, mencoba cadangan berikutnya.', [
                    'provider' => $provider,
                    'next' => $chain[$index + 1] ?? null,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        throw new AiProviderUnavailableException($this->routerFailureMessage($failures));
    }

    /**
     * Hasilkan satu gambar via model Gemini image-capable (responseModalities IMAGE).
     * Selalu memakai API key Gemini (pribadi atau sekolah) — OpenRouter tidak dipakai.
     *
     * @return array{binary:string,mime:string,model:string,prompt_tokens:int,completion_tokens:int}
     */
    public function generateImage(string $prompt, array $options = []): array
    {
        $apiKey = $this->resolveApiKey($options);
        if ($apiKey === '') {
            throw new RuntimeException('Fitur AI belum dikonfigurasi (GEMINI_API_KEY kosong).');
        }

        $models = array_values(array_unique(array_filter(array_merge(
            [trim((string) ($options['model'] ?? config('ai.image.model', 'gemini-2.5-flash-image')))],
            (array) ($options['fallback_models'] ?? config('ai.image.fallback_models', [])),
        ))));

        if ($models === []) {
            throw new RuntimeException('Model generate gambar belum dikonfigurasi.');
        }

        $lastError = null;
        $body = [
            'contents' => [[
                'role' => 'user',
                'parts' => [['text' => $prompt]],
            ]],
            'generationConfig' => [
                'responseModalities' => ['TEXT', 'IMAGE'],
            ],
        ];

        foreach ($models as $model) {
            $this->extendExecutionTime($options + [
                'timeout' => $options['timeout'] ?? config('ai.image.timeout', 90),
            ]);

            try {
                $response = Http::timeout($options['timeout'] ?? config('ai.image.timeout', 90))
                    ->retry(
                        $options['retries'] ?? 1,
                        config('ai.retry_delay'),
                        fn (\Throwable $e) => $this->isTransient($e),
                        throw: false,
                    )
                    ->withQueryParameters(['key' => $apiKey])
                    ->acceptJson()
                    ->post(rtrim((string) config('ai.base_url'), '/')."/models/{$model}:generateContent", $body);
            } catch (\Throwable $e) {
                $lastError = 'Gagal menghubungi layanan generate gambar AI.';

                continue;
            }

            if ($response->successful()) {
                return $this->parseImage($response->json(), $model);
            }

            if ($response->status() === 429) {
                $lastError = $this->normalizeError(429, $response->json());

                continue;
            }

            if ($response->status() >= 500) {
                $lastError = $this->normalizeError($response->status(), $response->json());

                continue;
            }

            throw new RuntimeException($this->normalizeError($response->status(), $response->json()));
        }

        throw new AiProviderUnavailableException($lastError ?? 'Gagal menghasilkan gambar soal.');
    }

    /**
     * @return array{binary:string,mime:string,model:string,prompt_tokens:int,completion_tokens:int}
     */
    private function parseImage(array $json, string $model): array
    {
        $candidate = $json['candidates'][0] ?? null;
        $finishReason = $candidate['finishReason'] ?? null;

        if ($finishReason === 'SAFETY' || $finishReason === 'PROHIBITED_CONTENT') {
            throw new RuntimeException('Generate gambar diblokir filter keamanan AI. Ubah deskripsi gambar.');
        }

        $binary = null;
        $mime = 'image/png';

        foreach ($candidate['content']['parts'] ?? [] as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline)) {
                continue;
            }

            $data = (string) ($inline['data'] ?? '');
            if ($data === '') {
                continue;
            }

            $decoded = base64_decode($data, true);
            if ($decoded === false || $decoded === '') {
                continue;
            }

            $binary = $decoded;
            $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'image/png');
        }

        if ($binary === null) {
            throw new RuntimeException('AI tidak mengembalikan gambar. Coba lagi atau nonaktifkan opsi soal bergambar.');
        }

        $usage = $json['usageMetadata'] ?? [];

        return [
            'binary' => $binary,
            'mime' => $mime !== '' ? $mime : 'image/png',
            'model' => $model,
            'prompt_tokens' => (int) ($usage['promptTokenCount'] ?? 0),
            'completion_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
        ];
    }

    /**
     * Ping ringan untuk memvalidasi API key Gemini (dipakai saat guru menyimpan key).
     */
    public function probeApiKey(string $apiKey): void
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new RuntimeException('API key kosong.');
        }

        $model = (string) config('ai.model', 'gemini-2.0-flash');
        $url = rtrim((string) config('ai.base_url'), '/')."/models/{$model}:generateContent";

        try {
            $response = Http::timeout(20)
                ->withQueryParameters(['key' => $apiKey])
                ->acceptJson()
                ->post($url, [
                    'contents' => [['role' => 'user', 'parts' => [['text' => 'ping']]]],
                    'generationConfig' => [
                        'maxOutputTokens' => 8,
                        'temperature' => 0,
                    ],
                ]);
        } catch (\Throwable) {
            throw new RuntimeException('Gagal menghubungi Gemini untuk memvalidasi API key. Coba lagi.');
        }

        if ($response->successful()) {
            return;
        }

        throw new RuntimeException($this->normalizeError($response->status(), $response->json())
            ?: 'API key tidak valid atau belum aktif di Google AI Studio.');
    }

    /** Key efektif: opsi request (key pribadi) atau key sekolah dari config. */
    private function resolveApiKey(array $options = []): string
    {
        $fromOptions = trim((string) ($options['api_key'] ?? ''));

        return $fromOptions !== '' ? $fromOptions : trim((string) config('ai.api_key'));
    }

    /** Gabungkan kegagalan tiap provider jadi satu pesan yang jujur untuk guru. */
    private function routerFailureMessage(array $failures): string
    {
        $primary = (string) array_key_first($failures);
        $message = (string) $failures[$primary];
        unset($failures[$primary]);

        if ($failures !== []) {
            $message .= ' Layanan cadangan AI Asisten SIMS juga gagal.';
        }

        return $message;
    }

    /**
     * Hasilkan teks via Gemini REST.
     *
     * @return array{text:string,model:string,prompt_tokens:int,completion_tokens:int}
     */
    private function generateGemini(string $prompt, array $options = []): array
    {
        // answer_style bisa dikosongkan per-request: keluaran dokumen (RPM/LKPD) harus
        // teks polos, sedangkan gaya global justru menyuruh model memakai Markdown.
        $answerStyle = $options['answer_style'] ?? config('ai.answer_style');
        $system = trim(($options['system'] ?? '')."\n\n".config('ai.system_prompt')."\n\n".$answerStyle);
        $contents = $this->buildContents($prompt, $options['history'] ?? []);
        $modelChain = $this->modelChain($options);
        $apiKey = $this->resolveApiKey($options);

        if ($apiKey === '') {
            throw new RuntimeException('Fitur AI belum dikonfigurasi (GEMINI_API_KEY kosong).');
        }

        $this->ensureFreeTierQuotaIsOpen($modelChain, $apiKey);

        $lastQuotaError = null;
        $allModelsHitDailyQuota = true;

        // Kuota free tier Gemini dihitung PER MODEL PER HARI (mis. gemini-3.5-flash hanya
        // 20 request/hari). Bila model utama habis, pindah ke model cadangan yang punya
        // jatah sendiri — jauh lebih baik daripada melempar "kuota penuh" ke guru.
        foreach ($modelChain as $model) {
            $body = [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => $options['temperature'] ?? config('ai.temperature'),
                    'maxOutputTokens' => $options['max_output_tokens'] ?? config('ai.max_output_tokens'),
                ],
            ];

            if (! empty($options['thinking_level'])) {
                $body['generationConfig']['thinkingConfig'] = $this->thinkingConfig($model, $options['thinking_level']);
            }

            // Grounding Google Search: biarkan model mencari di web & menautkan sumber.
            if (! empty($options['grounding'])) {
                $body['tools'] = [$this->groundingTool($model)];
            }

            $this->extendExecutionTime($options);

            try {
                $response = Http::timeout($options['timeout'] ?? config('ai.timeout'))
                    // `when`: JANGAN ulangi permintaan yang ditolak (429/4xx) — tiap percobaan
                    // memotong kuota harian, jadi retry justru mempercepat kuota habis.
                    // Yang layak diulang hanya kegagalan transien: koneksi putus & error 5xx.
                    ->retry(
                        $options['retries'] ?? config('ai.retries'),
                        config('ai.retry_delay'),
                        fn (\Throwable $e) => $this->isTransient($e),
                        throw: false,
                    )
                    ->withQueryParameters(['key' => $apiKey])
                    ->acceptJson()
                    ->post(rtrim(config('ai.base_url'), '/')."/models/{$model}:generateContent", $body);
            } catch (\Throwable $e) {
                throw new AiProviderUnavailableException('Gagal menghubungi layanan AI. Coba lagi beberapa saat.');
            }

            if ($response->successful()) {
                return $this->parse($response->json(), $model);
            }

            // Kuota model ini habis: coba model berikutnya. Jangan retry model yang sama.
            if ($response->status() === 429) {
                $json = $response->json();
                $lastQuotaError = $this->normalizeError(429, $json);
                $allModelsHitDailyQuota = $allModelsHitDailyQuota && $this->isDailyQuotaError($json);

                continue;
            }

            // Layanan sedang bermasalah (5xx) — layak dialihkan ke provider cadangan.
            if ($response->status() >= 500) {
                throw new AiProviderUnavailableException($this->normalizeError($response->status(), $response->json()));
            }

            throw new RuntimeException($this->normalizeError($response->status(), $response->json()));
        }

        if ($this->freeTierOnly() && $lastQuotaError !== null && $allModelsHitDailyQuota) {
            $this->rememberFreeTierQuotaExhausted($modelChain, $apiKey);

            throw new AiProviderUnavailableException($this->freeTierQuotaMessage());
        }

        throw new AiProviderUnavailableException($lastQuotaError ?? 'Terjadi kesalahan saat memproses permintaan AI.');
    }

    /**
     * Hasilkan teks via OpenRouter Chat Completions. Saat free-only aktif,
     * model berbayar ditolak sebelum HTTP request terkirim.
     *
     * @return array{text:string,model:string,prompt_tokens:int,completion_tokens:int,sources?:array}
     */
    private function generateOpenRouter(string $prompt, array $options = []): array
    {
        $answerStyle = $options['answer_style'] ?? config('ai.answer_style');
        $system = trim(($options['system'] ?? '')."\n\n".config('ai.system_prompt')."\n\n".$answerStyle);
        $messages = $this->buildOpenRouterMessages($system, $prompt, $options['history'] ?? []);
        $modelChain = $this->openRouterModelChain($options);

        $this->ensureOpenRouterModelsAreFree($modelChain);
        $this->ensureOpenRouterFreeQuotaIsOpen($modelChain);
        $this->ensureOpenRouterLocalDailyQuotaIsOpen();

        $lastQuotaError = null;

        foreach ($modelChain as $model) {
            $body = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? config('ai.temperature'),
                'max_tokens' => $options['max_output_tokens'] ?? config('ai.max_output_tokens'),
                'stream' => false,
            ];

            $this->extendExecutionTime($options);

            try {
                $response = Http::timeout($options['timeout'] ?? config('ai.timeout'))
                    ->retry(
                        $options['retries'] ?? config('ai.retries'),
                        config('ai.retry_delay'),
                        fn (\Throwable $e) => $this->isTransient($e),
                        throw: false,
                    )
                    ->withToken((string) config('ai.openrouter.api_key'))
                    ->withHeaders($this->openRouterHeaders())
                    ->acceptJson()
                    ->post(rtrim((string) config('ai.openrouter.base_url'), '/').'/chat/completions', $body);
            } catch (\Throwable $e) {
                throw new AiProviderUnavailableException('Gagal menghubungi layanan AI Asisten SIMS. Coba lagi beberapa saat.');
            }

            if ($response->successful()) {
                return $this->parseOpenAiCompatible($response->json(), $model, 'AI Asisten SIMS');
            }

            // 429 = rate/kuota; 402 = kredit habis — keduanya layak dialihkan ke Gemini.
            if (in_array($response->status(), [402, 429], true)) {
                $lastQuotaError = $this->normalizeOpenRouterError($response->status(), $response->json());

                continue;
            }

            if ($response->status() >= 500) {
                throw new AiProviderUnavailableException($this->normalizeOpenRouterError($response->status(), $response->json()));
            }

            throw new RuntimeException($this->normalizeOpenRouterError($response->status(), $response->json()));
        }

        if ($this->openRouterFreeOnly() && $lastQuotaError !== null) {
            $this->rememberOpenRouterQuotaExhausted($modelChain);

            throw new AiProviderUnavailableException($this->openRouterFreeQuotaMessage());
        }

        throw new AiProviderUnavailableException($lastQuotaError ?? 'Terjadi kesalahan saat memproses permintaan AI Asisten SIMS.');
    }

    /**
     * Hasilkan teks via 9Router (OpenAI-compatible Chat Completions).
     *
     * @return array{text:string,model:string,prompt_tokens:int,completion_tokens:int,sources?:array}
     */
    private function generateNinerouter(string $prompt, array $options = []): array
    {
        $answerStyle = $options['answer_style'] ?? config('ai.answer_style');
        $system = trim(($options['system'] ?? '')."\n\n".config('ai.system_prompt')."\n\n".$answerStyle);
        $messages = $this->buildOpenRouterMessages($system, $prompt, $options['history'] ?? []);
        $modelChain = $this->ninerouterModelChain($options);
        $lastQuotaError = null;

        foreach ($modelChain as $model) {
            $body = [
                'model' => $model,
                'messages' => $messages,
                'temperature' => $options['temperature'] ?? config('ai.temperature'),
                'max_tokens' => $options['max_output_tokens'] ?? config('ai.max_output_tokens'),
                'stream' => false,
            ];

            $this->extendExecutionTime($options);

            try {
                $response = Http::timeout($options['timeout'] ?? config('ai.timeout'))
                    ->retry(
                        $options['retries'] ?? config('ai.retries'),
                        config('ai.retry_delay'),
                        fn (\Throwable $e) => $this->isTransient($e),
                        throw: false,
                    )
                    ->withToken((string) config('ai.ninerouter.api_key'))
                    ->acceptJson()
                    ->post(rtrim((string) config('ai.ninerouter.base_url'), '/').'/chat/completions', $body);
            } catch (\Throwable $e) {
                throw new AiProviderUnavailableException('Gagal menghubungi layanan AI Asisten SIMS. Pastikan gateway aktif.');
            }

            if ($response->successful()) {
                return $this->parseOpenAiCompatible($response->json(), $model, 'AI Asisten SIMS');
            }

            if (in_array($response->status(), [402, 429], true)) {
                $lastQuotaError = $this->normalizeNinerouterError($response->status(), $response->json());

                continue;
            }

            if ($response->status() >= 500) {
                throw new AiProviderUnavailableException($this->normalizeNinerouterError($response->status(), $response->json()));
            }

            throw new RuntimeException($this->normalizeNinerouterError($response->status(), $response->json()));
        }

        throw new AiProviderUnavailableException($lastQuotaError ?? 'Terjadi kesalahan saat memproses permintaan AI Asisten SIMS.');
    }

    /** @return string[] */
    private function ninerouterModelChain(array $options): array
    {
        $chain = [$options['model'] ?? config('ai.ninerouter.model')];

        if (! isset($options['model'])) {
            $chain = array_merge($chain, (array) config('ai.ninerouter.fallback_models', []));
        }

        return array_values(array_unique(array_filter(array_map('trim', $chain))));
    }

    /**
     * Cegah fatal "Maximum execution time exceeded": batas waktu PHP (mis. 60 detik)
     * bisa lebih pendek daripada total waktu tunggu AI (timeout x retry x rantai model
     * x rantai provider). Timer PHP di-reset sebelum setiap percobaan, jadi anggaran
     * waktunya cukup untuk satu percobaan, bukan seluruh rantai.
     */
    private function extendExecutionTime(array $options): void
    {
        $timeout = (int) ($options['timeout'] ?? config('ai.timeout'));
        $retries = (int) ($options['retries'] ?? config('ai.retries'));
        $budget = $timeout * max(1, $retries) + 30;

        @set_time_limit($budget);
    }

    /** @return string[] */
    private function openRouterModelChain(array $options): array
    {
        $chain = [$options['model'] ?? config('ai.openrouter.model')];

        if (! isset($options['model'])) {
            $chain = array_merge($chain, (array) config('ai.openrouter.fallback_models', []));
        }

        return array_values(array_unique(array_filter(array_map('trim', $chain))));
    }

    /** @param string[] $modelChain */
    private function ensureOpenRouterModelsAreFree(array $modelChain): void
    {
        if (! $this->openRouterFreeOnly()) {
            return;
        }

        foreach ($modelChain as $model) {
            if ($this->isOpenRouterFreeModel($model)) {
                continue;
            }

            throw new RuntimeException("Mode free-only aktif. Model {$model} ditolak karena bukan model gratis yang diizinkan.");
        }
    }

    private function isOpenRouterFreeModel(string $model): bool
    {
        return $model === 'openrouter/free' || str_ends_with($model, ':free');
    }

    private function openRouterFreeOnly(): bool
    {
        return (bool) config('ai.openrouter.free_only', true);
    }

    /** @param string[] $modelChain */
    private function ensureOpenRouterFreeQuotaIsOpen(array $modelChain): void
    {
        if (! $this->openRouterFreeOnly()) {
            return;
        }

        $resetAt = Cache::get($this->openRouterQuotaCacheKey($modelChain));
        if (! $resetAt) {
            return;
        }

        throw new AiProviderUnavailableException($this->openRouterFreeQuotaMessage((string) $resetAt));
    }

    /**
     * Bila batas request harian free (SIMS) sudah penuh, langsung lempar unavailable
     * supaya router provider beralih ke Gemini tanpa menunggu 429 dari OpenRouter.
     */
    private function ensureOpenRouterLocalDailyQuotaIsOpen(): void
    {
        if (! $this->openRouterFreeOnly()) {
            return;
        }

        if (! \Illuminate\Support\Facades\Schema::hasTable('ai_usage_logs')) {
            return;
        }

        $limit = max(1, (int) config('ai.openrouter.free_daily_limit', 50));
        $timezone = config('app.timezone', 'Asia/Jakarta');
        $todayUtc = Carbon::now('UTC')->startOfDay();
        $resetUtc = $todayUtc->copy()->addDay();
        $dayStartLocal = $todayUtc->copy()->setTimezone($timezone);
        $dayEndLocal = $resetUtc->copy()->setTimezone($timezone);

        try {
            $used = \App\Models\AiUsageLog::query()
                ->where('status', 'success')
                ->where(function ($q) {
                    $q->where('model', 'like', '%:free')
                        ->orWhere('model', 'like', 'openrouter/%');
                })
                ->where('created_at', '>=', $dayStartLocal)
                ->where('created_at', '<', $dayEndLocal)
                ->count();
        } catch (\Throwable) {
            return;
        }

        if ($used < $limit) {
            return;
        }

        $this->rememberOpenRouterQuotaExhausted($this->openRouterModelChain([]));

        throw new AiProviderUnavailableException(
            'Batas request gratis AI Asisten SIMS hari ini ('.$limit.') sudah tercapai. Mengalihkan ke layanan cadangan bila tersedia.'
        );
    }

    /** @param string[] $modelChain */
    private function rememberOpenRouterQuotaExhausted(array $modelChain): void
    {
        $resetAt = now(config('app.timezone', 'Asia/Jakarta'))->addDay()->startOfDay();

        Cache::put(
            $this->openRouterQuotaCacheKey($modelChain),
            $resetAt->toIso8601String(),
            $resetAt,
        );
    }

    /** @param string[] $modelChain */
    private function openRouterQuotaCacheKey(array $modelChain): string
    {
        return 'ai:openrouter:free-tier-quota-exhausted:'.sha1((string) config('ai.openrouter.api_key').'|'.implode('|', $modelChain));
    }

    private function openRouterFreeQuotaMessage(?string $resetAt = null): string
    {
        $displayReset = '';
        if ($resetAt) {
            $displayReset = ' Perkiraan reset: '.Carbon::parse($resetAt)
                ->setTimezone(config('app.timezone', 'Asia/Jakarta'))
                ->format('d/m/Y H:i T').'.';
        }

        return 'Kuota gratis AI Asisten SIMS sedang habis atau terkena rate limit. '
            .'Sistem tidak akan mencoba jalur ini lagi sampai kuota free tier reset dan tidak akan memakai model berbayar.'
            .$displayReset;
    }

    private function openRouterHeaders(): array
    {
        return array_filter([
            'HTTP-Referer' => config('ai.openrouter.site_url'),
            'X-OpenRouter-Title' => config('ai.openrouter.site_name'),
        ]);
    }

    /**
     * Status live API key OpenRouter (GET /api/v1/key).
     * Dipakai Generate Kuota agar angka mengikuti kredit/usage nyata, bukan estimasi lokal semata.
     *
     * @return array{
     *   alive:bool,
     *   status:string,
     *   message:?string,
     *   label:?string,
     *   is_free_tier:?bool,
     *   limit:?float,
     *   limit_remaining:?float,
     *   limit_reset:?string,
     *   usage:?float,
     *   usage_daily:?float,
     *   usage_weekly:?float,
     *   usage_monthly:?float,
     *   fetched_at:string
     * }
     */
    public function openRouterKeyStatus(bool $fresh = false): array
    {
        $apiKey = (string) config('ai.openrouter.api_key');
        $fetchedAt = now()->toIso8601String();

        if ($apiKey === '') {
            return [
                'alive' => false,
                'status' => 'missing_key',
                'message' => 'API key AI Asisten SIMS belum diisi.',
                'label' => null,
                'is_free_tier' => null,
                'limit' => null,
                'limit_remaining' => null,
                'limit_reset' => null,
                'usage' => null,
                'usage_daily' => null,
                'usage_weekly' => null,
                'usage_monthly' => null,
                'fetched_at' => $fetchedAt,
            ];
        }

        $cacheKey = 'ai:openrouter:key-status:'.sha1($apiKey);
        $ttl = max(1, (int) config('ai.openrouter.quota_cache_seconds', 8));

        if (! $fresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $response = Http::timeout(8)
                ->withToken($apiKey)
                ->withHeaders($this->openRouterHeaders())
                ->acceptJson()
                ->get(rtrim((string) config('ai.openrouter.base_url'), '/').'/key');
        } catch (\Throwable $e) {
            return [
                'alive' => false,
                'status' => 'unreachable',
                'message' => 'Tidak dapat menghubungi AI Asisten SIMS.',
                'label' => null,
                'is_free_tier' => null,
                'limit' => null,
                'limit_remaining' => null,
                'limit_reset' => null,
                'usage' => null,
                'usage_daily' => null,
                'usage_weekly' => null,
                'usage_monthly' => null,
                'fetched_at' => $fetchedAt,
            ];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            $payload = [
                'alive' => false,
                'status' => 'invalid_key',
                'message' => 'API key AI Asisten SIMS ditolak (tidak valid / kedaluwarsa).',
                'label' => null,
                'is_free_tier' => null,
                'limit' => null,
                'limit_remaining' => null,
                'limit_reset' => null,
                'usage' => null,
                'usage_daily' => null,
                'usage_weekly' => null,
                'usage_monthly' => null,
                'fetched_at' => $fetchedAt,
            ];
            Cache::put($cacheKey, $payload, $ttl);

            return $payload;
        }

        if (! $response->successful()) {
            return [
                'alive' => false,
                'status' => 'error',
                'message' => 'AI Asisten SIMS mengembalikan status '.$response->status().'.',
                'label' => null,
                'is_free_tier' => null,
                'limit' => null,
                'limit_remaining' => null,
                'limit_reset' => null,
                'usage' => null,
                'usage_daily' => null,
                'usage_weekly' => null,
                'usage_monthly' => null,
                'fetched_at' => $fetchedAt,
            ];
        }

        $data = (array) ($response->json('data') ?? []);
        $payload = [
            'alive' => true,
            'status' => 'ok',
            'message' => null,
            'label' => isset($data['label']) ? (string) $data['label'] : null,
            'is_free_tier' => array_key_exists('is_free_tier', $data) ? (bool) $data['is_free_tier'] : null,
            'limit' => array_key_exists('limit', $data) && $data['limit'] !== null ? (float) $data['limit'] : null,
            'limit_remaining' => array_key_exists('limit_remaining', $data) && $data['limit_remaining'] !== null
                ? (float) $data['limit_remaining']
                : null,
            'limit_reset' => isset($data['limit_reset']) ? (string) $data['limit_reset'] : null,
            'usage' => isset($data['usage']) ? (float) $data['usage'] : null,
            'usage_daily' => isset($data['usage_daily']) ? (float) $data['usage_daily'] : null,
            'usage_weekly' => isset($data['usage_weekly']) ? (float) $data['usage_weekly'] : null,
            'usage_monthly' => isset($data['usage_monthly']) ? (float) $data['usage_monthly'] : null,
            'fetched_at' => $fetchedAt,
        ];

        Cache::put($cacheKey, $payload, $ttl);

        return $payload;
    }

    /**
     * Status live API key 9Router — probe GET /models (gateway OpenAI-compatible).
     *
     * @return array{
     *   alive:bool,
     *   status:string,
     *   message:?string,
     *   label:?string,
     *   model:?string,
     *   fetched_at:string
     * }
     */
    public function nineRouterKeyStatus(bool $fresh = false): array
    {
        $apiKey = (string) config('ai.ninerouter.api_key');
        $fetchedAt = now()->toIso8601String();
        $model = (string) config('ai.ninerouter.model');

        if ($apiKey === '') {
            return [
                'alive' => false,
                'status' => 'missing_key',
                'message' => 'NINEROUTER_API_KEY belum diisi.',
                'label' => null,
                'model' => $model !== '' ? $model : null,
                'fetched_at' => $fetchedAt,
            ];
        }

        $cacheKey = 'ai:ninerouter:key-status:'.sha1($apiKey.'|'.(string) config('ai.ninerouter.base_url'));
        $ttl = max(1, (int) config('ai.ninerouter.quota_cache_seconds', 8));

        if (! $fresh) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        try {
            $response = Http::timeout(8)
                ->withToken($apiKey)
                ->acceptJson()
                ->get(rtrim((string) config('ai.ninerouter.base_url'), '/').'/models');
        } catch (\Throwable $e) {
            return [
                'alive' => false,
                'status' => 'unreachable',
                'message' => 'Tidak dapat menghubungi AI Asisten SIMS. Pastikan gateway aktif.',
                'label' => null,
                'model' => $model !== '' ? $model : null,
                'fetched_at' => $fetchedAt,
            ];
        }

        if (in_array($response->status(), [401, 403], true)) {
            $payload = [
                'alive' => false,
                'status' => 'invalid_key',
                'message' => 'API key AI Asisten SIMS ditolak.',
                'label' => null,
                'model' => $model !== '' ? $model : null,
                'fetched_at' => $fetchedAt,
            ];
            Cache::put($cacheKey, $payload, $ttl);

            return $payload;
        }

        if (! $response->successful()) {
            return [
                'alive' => false,
                'status' => 'error',
                'message' => 'AI Asisten SIMS mengembalikan status '.$response->status().'.',
                'label' => null,
                'model' => $model !== '' ? $model : null,
                'fetched_at' => $fetchedAt,
            ];
        }

        $payload = [
            'alive' => true,
            'status' => 'ok',
            'message' => null,
            'label' => 'AI Asisten SIMS',
            'model' => $model !== '' ? $model : null,
            'fetched_at' => $fetchedAt,
        ];

        Cache::put($cacheKey, $payload, $ttl);

        return $payload;
    }

    private function buildOpenRouterMessages(string $system, string $prompt, array $history): array
    {
        $messages = [];

        if ($system !== '') {
            $messages[] = ['role' => 'system', 'content' => $system];
        }

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $text = (string) ($turn['text'] ?? $turn['content'] ?? '');
            if ($text !== '') {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $messages;
    }

    private function parseOpenRouter(array $json, string $model): array
    {
        return $this->parseOpenAiCompatible($json, $model, 'AI Asisten SIMS');
    }

    private function parseOpenAiCompatible(array $json, string $model, string $label): array
    {
        $choice = $json['choices'][0] ?? null;
        $finishReason = $choice['finish_reason'] ?? null;
        $content = $choice['message']['content'] ?? '';
        $text = is_array($content) ? $this->openRouterContentToText($content) : trim((string) $content);

        if ($text === '') {
            throw new RuntimeException("{$label} tidak mengembalikan jawaban. Coba lagi.");
        }

        if ($finishReason === 'length') {
            throw new RuntimeException("Jawaban {$label} terpotong karena terlalu panjang. Persempit topik atau coba lagi.");
        }

        $usage = $json['usage'] ?? [];

        return [
            'text' => $text,
            'model' => (string) ($json['model'] ?? $model),
            'prompt_tokens' => (int) ($usage['prompt_tokens'] ?? 0),
            'completion_tokens' => (int) ($usage['completion_tokens'] ?? 0),
            'sources' => [],
        ];
    }

    private function openRouterContentToText(array $content): string
    {
        $text = '';

        foreach ($content as $part) {
            $text .= is_array($part) ? (string) ($part['text'] ?? '') : (string) $part;
        }

        return trim($text);
    }

    private function normalizeOpenRouterError(int $status, ?array $json): string
    {
        $detail = (string) ($json['error']['message'] ?? $json['message'] ?? '');

        return match (true) {
            $status === 429 => 'Kuota atau rate limit free model AI Asisten SIMS sedang habis. Coba lagi besok atau setelah kuota free tier kembali.',
            $status === 402 => 'AI Asisten SIMS membutuhkan saldo/kredit untuk model ini. Mode free-only aktif dan tidak akan mencoba model berbayar.',
            $status === 400 => 'Permintaan ke AI Asisten SIMS tidak valid.'.($detail ? " ({$detail})" : ''),
            $status === 401,
            $status === 403 => 'Konfigurasi AI Asisten SIMS bermasalah (kredensial ditolak).',
            $status >= 500 => 'Layanan AI Asisten SIMS sedang gangguan. Coba lagi nanti.',
            default => 'Terjadi kesalahan saat memproses permintaan AI Asisten SIMS.',
        };
    }

    private function normalizeNinerouterError(int $status, ?array $json): string
    {
        $detail = (string) ($json['error']['message'] ?? $json['message'] ?? '');

        return match (true) {
            $status === 429 => 'Rate limit AI Asisten SIMS sedang penuh. Coba lagi sebentar.',
            $status === 402 => 'AI Asisten SIMS menolak permintaan karena kuota/kredit model habis.',
            $status === 400 => 'Permintaan ke AI Asisten SIMS tidak valid.'.($detail ? " ({$detail})" : ''),
            $status === 401,
            $status === 403 => 'Konfigurasi AI Asisten SIMS bermasalah (kredensial ditolak).',
            $status >= 500 => 'Layanan AI Asisten SIMS sedang gangguan. Coba lagi nanti.',
            default => 'Terjadi kesalahan saat memproses permintaan AI Asisten SIMS.',
        };
    }

    /**
     * Urutan model yang dicoba: model utama lalu cadangan. Cadangan punya kuota harian
     * sendiri, jadi rantai ini yang membuat fitur tetap hidup setelah kuota model utama habis.
     *
     * @return string[]
     */
    private function modelChain(array $options): array
    {
        $chain = [$options['model'] ?? config('ai.model')];

        // Override model per-request berarti pemanggil sengaja memilih model itu — hormati.
        if (! isset($options['model'])) {
            $chain = array_merge($chain, (array) config('ai.fallback_models', []));
        }

        return array_values(array_unique(array_filter(array_map('trim', $chain))));
    }

    /**
     * Penekan porsi "berpikir" berbeda antar keluarga model: Gemini 3.x memakai
     * `thinkingLevel`, sedangkan 2.5 ke bawah memakai `thinkingBudget` (angka token).
     * Mengirim kunci yang salah membuat Gemini menolak permintaan dengan 400.
     */
    private function thinkingConfig(string $model, string $level): array
    {
        if (preg_match('/gemini-3/', $model)) {
            return ['thinkingLevel' => $level];
        }

        return ['thinkingBudget' => $level === 'low' ? 0 : -1];
    }

    private function freeTierOnly(): bool
    {
        return (bool) config('ai.free_tier_only', true);
    }

    /** @param string[] $modelChain */
    private function ensureFreeTierQuotaIsOpen(array $modelChain, string $apiKey = ''): void
    {
        if (! $this->freeTierOnly()) {
            return;
        }

        $resetAt = Cache::get($this->freeTierQuotaCacheKey($modelChain, $apiKey));
        if (! $resetAt) {
            return;
        }

        throw new AiProviderUnavailableException($this->freeTierQuotaMessage((string) $resetAt));
    }

    /** @param string[] $modelChain */
    private function rememberFreeTierQuotaExhausted(array $modelChain, string $apiKey = ''): void
    {
        $resetAt = $this->nextFreeTierResetAt();

        Cache::put(
            $this->freeTierQuotaCacheKey($modelChain, $apiKey),
            $resetAt->toIso8601String(),
            $resetAt,
        );
    }

    /** @param string[] $modelChain */
    private function freeTierQuotaCacheKey(array $modelChain, string $apiKey = ''): string
    {
        $key = $apiKey !== '' ? $apiKey : (string) config('ai.api_key');

        return 'ai:gemini:free-tier-quota-exhausted:'.sha1($key.'|'.implode('|', $modelChain));
    }

    private function nextFreeTierResetAt(): Carbon
    {
        // Google menyebut RPD reset tengah malam Pacific time.
        return now('America/Los_Angeles')->addDay()->startOfDay();
    }

    private function freeTierQuotaMessage(?string $resetAt = null): string
    {
        $displayReset = '';
        if ($resetAt) {
            $displayReset = ' Perkiraan reset: '.Carbon::parse($resetAt)
                ->setTimezone(config('app.timezone', 'Asia/Jakarta'))
                ->format('d/m/Y H:i T').'.';
        }

        return 'Kuota gratis AI Asisten SIMS sudah habis untuk semua model yang dikonfigurasi. '
            .'Sistem tidak akan mencoba lagi sampai kuota free tier reset agar tidak memakai API berbayar.'
            .$displayReset;
    }

    /** Layak diulang hanya bila gangguan sesaat (koneksi/5xx), bukan penolakan (4xx). */
    private function isTransient(\Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        return $e instanceof RequestException && $e->response->serverError();
    }

    /**
     * Hasilkan vektor embedding untuk satu teks (FASE 5 — RAG).
     *
     * @return float[] Vektor embedding.
     */
    public function embed(string $text): array
    {
        if (empty(config('ai.api_key'))) {
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
                    'model' => "models/{$model}",
                    'content' => ['parts' => [['text' => $text]]],
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Gagal menghubungi layanan embedding AI.');
        }

        if ($response->failed()) {
            throw new RuntimeException($this->normalizeError($response->status(), $response->json()));
        }

        $values = $response->json('embedding.values');
        if (! is_array($values) || $values === []) {
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
        $candidate = $json['candidates'][0] ?? null;
        $finishReason = $candidate['finishReason'] ?? null;

        if ($finishReason === 'SAFETY' || $finishReason === 'PROHIBITED_CONTENT') {
            throw new RuntimeException('Permintaan diblokir oleh filter keamanan AI. Ubah pertanyaanmu.');
        }

        // Part ber-flag `thought` = catatan berpikir internal model, bukan jawaban.
        $text = '';
        foreach ($candidate['content']['parts'] ?? [] as $part) {
            if (($part['thought'] ?? false) === true) {
                continue;
            }
            $text .= $part['text'] ?? '';
        }

        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('AI tidak mengembalikan jawaban. Coba lagi.');
        }

        // Jawaban terpotong karena kehabisan jatah token: lebih baik gagal terang-terangan
        // daripada mengembalikan dokumen setengah jadi yang tampak benar.
        if ($finishReason === 'MAX_TOKENS') {
            throw new RuntimeException('Jawaban AI terpotong karena terlalu panjang. Persempit topik atau coba lagi.');
        }

        $usage = $json['usageMetadata'] ?? [];

        return [
            'text' => $text,
            'model' => $model,
            'prompt_tokens' => (int) ($usage['promptTokenCount'] ?? 0),
            'completion_tokens' => (int) ($usage['candidatesTokenCount'] ?? 0),
            'sources' => $this->extractSources($candidate),
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
            return ['google_search_retrieval' => new \stdClass];
        }

        return ['google_search' => new \stdClass];
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
            if (! $uri || isset($sources[$uri])) {
                continue;
            }
            $sources[$uri] = [
                'title' => (string) ($chunk['web']['title'] ?? $uri),
                'uri' => $uri,
            ];
        }

        return array_values($sources);
    }

    private function isDailyQuotaError(?array $json): bool
    {
        return str_contains($this->quotaId($json), 'PerDay');
    }

    /** Ambil quotaId dari error 429 Gemini (mis. "GenerateRequestsPerDayPerProjectPerModel-FreeTier"). */
    private function quotaId(?array $json): string
    {
        foreach ($json['error']['details'] ?? [] as $detail) {
            foreach ($detail['violations'] ?? [] as $violation) {
                if (! empty($violation['quotaId'])) {
                    return (string) $violation['quotaId'];
                }
            }
        }

        return '';
    }

    /** Terjemahkan status HTTP Gemini jadi pesan Bahasa Indonesia yang aman. */
    private function normalizeError(int $status, ?array $json): string
    {
        $detail = $json['error']['message'] ?? '';

        // Bedakan batas HARIAN (habis sampai reset tengah malam waktu Pasifik) dari batas
        // per-menit, supaya guru tak menunggu sia-sia menekan tombol yang pasti gagal seharian.
        // Jenis kuota hanya ada di error.details[].violations[].quotaId, bukan di message.
        $isDaily = str_contains($this->quotaId($json), 'PerDay');

        return match (true) {
            $status === 429 && $isDaily => 'Kuota AI harian sudah habis untuk semua model gratis. '
                .'Coba lagi besok, atau aktifkan billing di Google AI Studio untuk kuota lebih besar.',
            $status === 429 => 'Permintaan AI terlalu sering. Tunggu sebentar lalu coba lagi.',
            $status === 400 => 'Permintaan ke AI tidak valid.'.($detail ? " ({$detail})" : ''),
            $status === 401,
            $status === 403 => 'Konfigurasi AI bermasalah (kredensial ditolak).',
            $status >= 500 => 'Layanan AI sedang gangguan. Coba lagi nanti.',
            default => 'Terjadi kesalahan saat memproses permintaan AI.',
        };
    }
}
