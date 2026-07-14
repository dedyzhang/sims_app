<?php

namespace Tests\Feature;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GeminiServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        Cache::flush();
        config()->set('ai.provider', 'gemini');
        config()->set('ai.api_key', 'test-key');
        config()->set('ai.model', 'gemini-test');
        config()->set('ai.fallback_models', []);
        // Router provider dimatikan secara default agar tiap skenario menguji satu provider;
        // perilaku failover diuji terpisah di test router di bawah.
        config()->set('ai.fallback_providers', []);
        config()->set('ai.free_tier_only', true);
        config()->set('ai.openrouter.api_key', 'openrouter-test-key');
        config()->set('ai.openrouter.base_url', 'https://openrouter.ai/api/v1');
        config()->set('ai.openrouter.model', 'openrouter/free');
        config()->set('ai.openrouter.fallback_models', []);
        config()->set('ai.openrouter.free_only', true);
        config()->set('ai.openrouter.site_url', 'http://localhost');
        config()->set('ai.openrouter.site_name', 'SIMS Test');
        config()->set('ai.ninerouter.api_key', 'ninerouter-test-key');
        config()->set('ai.ninerouter.base_url', 'http://127.0.0.1:20128/v1');
        config()->set('ai.ninerouter.model', 'FL-OpenCode');
        config()->set('ai.ninerouter.fallback_models', []);
    }

    /**
     * Kuota free tier Gemini = jumlah request per model per hari. Saat model utama
     * habis, fitur harus pindah ke model cadangan (ember kuota terpisah), bukan gagal.
     */
    public function test_kuota_model_utama_habis_pindah_ke_model_cadangan(): void
    {
        config()->set('ai.model', 'gemini-3.5-flash');
        config()->set('ai.fallback_models', ['gemini-3.1-flash-lite']);

        Http::fake([
            '*gemini-3.5-flash*' => Http::response($this->kuotaHarianHabis(), 429),
            '*gemini-3.1-flash-lite*' => Http::response($this->jawabanSukses('RPM cadangan')),
        ]);

        $result = app(GeminiService::class)->generate('Buat RPM');

        $this->assertSame('RPM cadangan', $result['text']);
        // Model yang dilaporkan harus model yang BENAR-BENAR dipakai (untuk audit ai_usage_logs).
        $this->assertSame('gemini-3.1-flash-lite', $result['model']);
    }

    /** Retry pada 429 justru memotong kuota harian lagi — permintaan yang ditolak jangan diulang. */
    public function test_permintaan_yang_kena_kuota_tidak_diulang(): void
    {
        config()->set('ai.retries', 3);

        Http::fake(['*' => Http::response($this->kuotaHarianHabis(), 429)]);

        try {
            app(GeminiService::class)->generate('Buat RPM');
        } catch (RuntimeException $e) {
            // diharapkan gagal
        }

        $terkirim = 0;
        Http::assertSent(function () use (&$terkirim) {
            $terkirim++;

            return true;
        });
        $this->assertSame(1, $terkirim, 'Permintaan 429 tidak boleh diulang: tiap percobaan memotong kuota.');
    }

    public function test_semua_model_habis_memberi_pesan_kuota_harian(): void
    {
        config()->set('ai.fallback_models', ['gemini-cadangan']);

        Http::fake(['*' => Http::response($this->kuotaHarianHabis(), 429)]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Kuota gratis');

        app(GeminiService::class)->generate('Buat RPM');
    }

    public function test_semua_model_free_habis_mengunci_panggilan_berikutnya_sampai_reset(): void
    {
        config()->set('ai.model', 'gemini-3.5-flash');
        config()->set('ai.fallback_models', ['gemini-3.1-flash-lite']);

        Http::fake(['*' => Http::response($this->kuotaHarianHabis(), 429)]);

        try {
            app(GeminiService::class)->generate('Buat RPM');
            $this->fail('Permintaan pertama seharusnya gagal karena semua model free habis.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Kuota gratis', $e->getMessage());
        }

        try {
            app(GeminiService::class)->generate('Buat RPM lagi');
            $this->fail('Permintaan kedua seharusnya ditahan sampai reset kuota free tier.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('tidak akan mencoba lagi sampai kuota free tier reset', $e->getMessage());
            $this->assertStringContainsString('Perkiraan reset', $e->getMessage());
        }

        $terkirim = 0;
        Http::assertSent(function () use (&$terkirim) {
            $terkirim++;

            return true;
        });

        $this->assertSame(2, $terkirim, 'Hanya request pertama yang boleh mencoba model utama dan cadangan.');
    }

    /** Gemini 3 memakai thinkingLevel; 2.5 memakai thinkingBudget. Salah kunci = HTTP 400. */
    public function test_konfigurasi_berpikir_menyesuaikan_keluarga_model(): void
    {
        config()->set('ai.model', 'gemini-2.5-flash');

        Http::fake(['*' => Http::response($this->jawabanSukses('ok'))]);

        app(GeminiService::class)->generate('Buat RPM', ['thinking_level' => 'low']);

        Http::assertSent(function ($request) {
            $cfg = $request->data()['generationConfig']['thinkingConfig'] ?? [];

            return array_key_exists('thinkingBudget', $cfg)
                && $cfg['thinkingBudget'] === 0
                && ! array_key_exists('thinkingLevel', $cfg);
        });
    }

    public function test_openrouter_free_model_menggunakan_endpoint_chat_completion(): void
    {
        config()->set('ai.provider', 'openrouter');
        config()->set('ai.openrouter.model', 'openrouter/free');

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response($this->jawabanOpenRouterSukses('Soal berhasil dibuat')),
        ]);

        $result = app(GeminiService::class)->generate('Buat soal', [
            'system' => 'Instruksi khusus guru.',
            'max_output_tokens' => 512,
        ]);

        $this->assertSame('Soal berhasil dibuat', $result['text']);
        $this->assertSame('openrouter/free', $result['model']);
        $this->assertSame(12, $result['prompt_tokens']);
        $this->assertSame(8, $result['completion_tokens']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer openrouter-test-key')
                && ($data['model'] ?? null) === 'openrouter/free'
                && ($data['max_tokens'] ?? null) === 512
                && ($data['stream'] ?? null) === false
                && ($data['messages'][0]['role'] ?? null) === 'system'
                && ($data['messages'][1]['content'] ?? null) === 'Buat soal';
        });
    }

    public function test_ninerouter_menggunakan_endpoint_chat_completion(): void
    {
        config()->set('ai.provider', 'ninerouter');

        Http::fake([
            'http://127.0.0.1:20128/v1/chat/completions' => Http::response($this->jawabanOpenRouterSukses('Jawaban 9Router', 'FL-OpenCode')),
        ]);

        $result = app(GeminiService::class)->generate('Halo');

        $this->assertSame('Jawaban 9Router', $result['text']);
        $this->assertSame('FL-OpenCode', $result['model']);

        Http::assertSent(function ($request) {
            $data = $request->data();

            return $request->url() === 'http://127.0.0.1:20128/v1/chat/completions'
                && $request->hasHeader('Authorization', 'Bearer ninerouter-test-key')
                && ($data['model'] ?? null) === 'FL-OpenCode'
                && ($data['stream'] ?? null) === false;
        });
    }

    public function test_ninerouter_kuota_habis_dialihkan_ke_openrouter(): void
    {
        config()->set('ai.provider', 'ninerouter');
        config()->set('ai.fallback_providers', ['openrouter']);

        Http::fake([
            'http://127.0.0.1:20128/v1/chat/completions' => Http::response(['error' => ['message' => 'rate limit']], 429),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response($this->jawabanOpenRouterSukses('Cadangan OpenRouter')),
        ]);

        $result = app(GeminiService::class)->generate('Halo');

        $this->assertSame('Cadangan OpenRouter', $result['text']);
        $this->assertSame('openrouter/free', $result['model']);
    }

    public function test_openrouter_free_only_menolak_model_berbayar_sebelum_request_terkirim(): void
    {
        config()->set('ai.provider', 'openrouter');
        config()->set('ai.openrouter.model', 'google/gemini-3.1-flash');
        Http::fake();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('free-only aktif');

        try {
            app(GeminiService::class)->generate('Buat soal');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_openrouter_429_pindah_ke_model_free_cadangan(): void
    {
        config()->set('ai.provider', 'openrouter');
        config()->set('ai.openrouter.model', 'qwen/qwen3-coder:free');
        config()->set('ai.openrouter.fallback_models', ['openrouter/free']);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::sequence()
                ->push(['error' => ['message' => 'Rate limit exceeded']], 429)
                ->push($this->jawabanOpenRouterSukses('Jawaban cadangan'), 200),
        ]);

        $result = app(GeminiService::class)->generate('Buat soal');

        $this->assertSame('Jawaban cadangan', $result['text']);
        $this->assertSame('openrouter/free', $result['model']);

        $models = [];
        Http::assertSent(function ($request) use (&$models) {
            $models[] = $request->data()['model'] ?? null;

            return true;
        });

        $this->assertSame(['qwen/qwen3-coder:free', 'openrouter/free'], $models);
    }

    /**
     * Router provider: saat kuota Gemini habis, guru tidak boleh melihat error —
     * permintaan dialihkan ke provider cadangan yang punya jatah sendiri.
     */
    public function test_kuota_provider_utama_habis_dialihkan_ke_provider_cadangan(): void
    {
        config()->set('ai.fallback_providers', ['openrouter']);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response($this->kuotaHarianHabis(), 429),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response($this->jawabanOpenRouterSukses('Jawaban dari cadangan')),
        ]);

        $result = app(GeminiService::class)->generate('Buat soal');

        $this->assertSame('Jawaban dari cadangan', $result['text']);
        $this->assertSame('openrouter/free', $result['model']);
    }

    /** OpenRouter utama: bila kuota free habis (429), langsung dialihkan ke Gemini. */
    public function test_kuota_openrouter_habis_dialihkan_ke_gemini(): void
    {
        config()->set('ai.provider', 'openrouter');
        config()->set('ai.fallback_providers', ['gemini']);
        config()->set('ai.openrouter.free_daily_limit', 50);

        Http::fake([
            'https://openrouter.ai/api/v1/chat/completions' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429),
            'https://generativelanguage.googleapis.com/*' => Http::response($this->jawabanSukses('Jawaban dari Gemini')),
        ]);

        $result = app(GeminiService::class)->generate('Buat soal');

        $this->assertSame('Jawaban dari Gemini', $result['text']);
        $this->assertSame('gemini-test', $result['model']);
    }

    /** Provider utama down (5xx) juga layak dialihkan, bukan dilempar ke guru. */
    public function test_provider_utama_error_server_dialihkan_ke_provider_cadangan(): void
    {
        config()->set('ai.fallback_providers', ['openrouter']);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Service unavailable']], 503),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response($this->jawabanOpenRouterSukses('Jawaban dari cadangan')),
        ]);

        $this->assertSame('Jawaban dari cadangan', app(GeminiService::class)->generate('Buat soal')['text']);
    }

    /** Kesalahan permintaan (4xx selain 429) bukan urusan kuota — jangan buang kuota cadangan. */
    public function test_kesalahan_permintaan_tidak_dialihkan_ke_provider_cadangan(): void
    {
        config()->set('ai.fallback_providers', ['openrouter']);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => 'Invalid argument']], 400),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response($this->jawabanOpenRouterSukses('Tidak boleh terpakai')),
        ]);

        try {
            app(GeminiService::class)->generate('Buat soal');
            $this->fail('Kesalahan permintaan seharusnya dilempar, bukan dialihkan.');
        } catch (RuntimeException) {
            Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openrouter.ai'));
        }
    }

    /** Provider cadangan tanpa API key dilewati, bukan dicoba lalu gagal. */
    public function test_provider_cadangan_tanpa_api_key_dilewati(): void
    {
        config()->set('ai.fallback_providers', ['openrouter']);
        config()->set('ai.openrouter.api_key', null);

        Http::fake(['*' => Http::response($this->kuotaHarianHabis(), 429)]);

        $this->expectException(RuntimeException::class);

        try {
            app(GeminiService::class)->generate('Buat soal');
        } finally {
            Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openrouter.ai'));
        }
    }

    /** Bila semua provider gagal, pesan harus menyebut kegagalan cadangan juga. */
    public function test_semua_provider_gagal_pesannya_menyebut_cadangan(): void
    {
        config()->set('ai.fallback_providers', ['openrouter']);

        Http::fake([
            'https://generativelanguage.googleapis.com/*' => Http::response($this->kuotaHarianHabis(), 429),
            'https://openrouter.ai/api/v1/chat/completions' => Http::response(['error' => ['message' => 'Rate limit exceeded']], 429),
        ]);

        try {
            app(GeminiService::class)->generate('Buat soal');
            $this->fail('Semua provider gagal seharusnya melempar exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Layanan cadangan AI Asisten SIMS juga gagal', $e->getMessage());
        }
    }

    private function jawabanOpenRouterSukses(string $teks, ?string $model = null): array
    {
        $payload = [
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['content' => $teks],
            ]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8],
        ];

        if ($model !== null) {
            $payload['model'] = $model;
        }

        return $payload;
    }

    private function jawabanSukses(string $teks): array
    {
        return [
            'candidates' => [[
                'finishReason' => 'STOP',
                'content' => ['parts' => [['text' => $teks]]],
            ]],
            'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
        ];
    }

    private function kuotaHarianHabis(): array
    {
        return [
            'error' => [
                'code' => 429,
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'You exceeded your current quota.',
                'details' => [[
                    '@type' => 'type.googleapis.com/google.rpc.QuotaFailure',
                    'violations' => [[
                        'quotaMetric' => 'generativelanguage.googleapis.com/generate_content_free_tier_requests',
                        'quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier',
                        'quotaValue' => '20',
                    ]],
                ]],
            ],
        ];
    }

    public function test_catatan_berpikir_model_tidak_ikut_ke_jawaban(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'finishReason' => 'STOP',
                    'content' => ['parts' => [
                        ['text' => 'Need to double-check the format here.', 'thought' => true],
                        ['text' => 'PERENCANAAN PEMBELAJARAN MENDALAM'],
                    ]],
                ]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5],
            ]),
        ]);

        $result = app(GeminiService::class)->generate('Buat RPM');

        $this->assertSame('PERENCANAAN PEMBELAJARAN MENDALAM', $result['text']);
        $this->assertStringNotContainsString('Need to double-check', $result['text']);
    }

    public function test_jawaban_terpotong_max_tokens_dilaporkan_sebagai_error(): void
    {
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'finishReason' => 'MAX_TOKENS',
                    'content' => ['parts' => [['text' => 'IDENTIFIKASI Murid: Fase C, kelas 5 SD yang']]],
                ]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 4096],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('terpotong');

        app(GeminiService::class)->generate('Buat RPM');
    }

    public function test_thinking_level_dan_batas_token_diteruskan_ke_gemini(): void
    {
        config()->set('ai.model', 'gemini-3.5-flash');

        Http::fake(['*' => Http::response($this->jawabanSukses('ok'))]);

        app(GeminiService::class)->generate('Buat RPM', [
            'thinking_level' => 'low',
            'max_output_tokens' => 8192,
        ]);

        Http::assertSent(function ($request) {
            $cfg = $request->data()['generationConfig'] ?? [];

            return ($cfg['thinkingConfig']['thinkingLevel'] ?? null) === 'low'
                && ($cfg['maxOutputTokens'] ?? null) === 8192;
        });
    }
}
