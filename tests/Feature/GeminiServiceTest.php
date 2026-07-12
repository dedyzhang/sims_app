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
        config()->set('ai.free_tier_only', true);
        config()->set('ai.openrouter.api_key', 'openrouter-test-key');
        config()->set('ai.openrouter.base_url', 'https://openrouter.ai/api/v1');
        config()->set('ai.openrouter.model', 'openrouter/free');
        config()->set('ai.openrouter.fallback_models', []);
        config()->set('ai.openrouter.free_only', true);
        config()->set('ai.openrouter.site_url', 'http://localhost');
        config()->set('ai.openrouter.site_name', 'SIMS Test');
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
            $this->assertStringContainsString('tidak akan mencoba Gemini lagi', $e->getMessage());
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
                && ($data['messages'][0]['role'] ?? null) === 'system'
                && ($data['messages'][1]['content'] ?? null) === 'Buat soal';
        });
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

    private function jawabanOpenRouterSukses(string $teks): array
    {
        return [
            'choices' => [[
                'finish_reason' => 'stop',
                'message' => ['content' => $teks],
            ]],
            'usage' => ['prompt_tokens' => 12, 'completion_tokens' => 8],
        ];
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
