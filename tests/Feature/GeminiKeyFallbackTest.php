<?php

namespace Tests\Feature;

use App\Exceptions\AiDailyQuotaExhaustedException;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
| Alur kuota key:
|   1. Key guru (options.api_key)
|   2. Hanya jika kuota HARIAN guru habis → key sekolah (config ai.api_key)
|   3. Key invalid / error non-kuota → JANGAN pindah ke sekolah
*/
class GeminiKeyFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.provider', 'gemini');
        config()->set('ai.api_key', 'school-key-sekolahtest01');
        config()->set('ai.fallback_providers', []);
        config()->set('ai.fallback_models', []);
        config()->set('ai.model', 'gemini-test-model');
        config()->set('ai.free_tier_only', true);
        config()->set('ai.rag.embed_model', 'gemini-embedding-001');
        Cache::flush();
    }

    public function test_generate_memakai_key_guru_dulu(): void
    {
        Http::fake([
            '*generateContent*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'ok dari guru']]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
            ], 200),
        ]);

        $result = app(GeminiService::class)->generate('halo', [
            'api_key' => 'teacher-key-gurutes01',
            'system' => 'test',
            'answer_style' => '',
        ]);

        $this->assertStringContainsString('ok dari guru', $result['text']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'key=teacher-key-gurutes01');
        });
        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'key=school-key-sekolahtest01');
        });
    }

    public function test_generate_pindah_ke_sekolah_saat_kuota_harian_guru_habis(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (str_contains($url, 'key=teacher-key-gurutes01')) {
                return Http::response($this->dailyQuotaExhaustedBody(), 429);
            }

            return Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => 'ok dari sekolah']]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
            ], 200);
        });

        $result = app(GeminiService::class)->generate('halo', [
            'api_key' => 'teacher-key-gurutes01',
            'system' => 'test',
            'answer_style' => '',
        ]);

        $this->assertStringContainsString('ok dari sekolah', $result['text']);
        $this->assertTrue($result['used_school_key'] ?? false);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'key=teacher-key-gurutes01'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'key=school-key-sekolahtest01'));
    }

    public function test_generate_tidak_pindah_sekolah_bila_key_guru_invalid(): void
    {
        Http::fake([
            '*generateContent*' => Http::response([
                'error' => [
                    'code' => 400,
                    'status' => 'INVALID_ARGUMENT',
                    'message' => 'API key not valid.',
                ],
            ], 400),
        ]);

        try {
            app(GeminiService::class)->generate('halo', [
                'api_key' => 'teacher-key-invalidxx01',
                'system' => 'test',
                'answer_style' => '',
            ]);
            $this->fail('Key invalid harus melempar, bukan diam-diam ke sekolah.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsStringIgnoringCase('key', $e->getMessage());
        }

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'key=school-key-sekolahtest01');
        });
    }

    public function test_embed_pindah_ke_sekolah_saat_kuota_guru_habis(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'key=teacher-key-gurutes01')) {
                return Http::response($this->dailyQuotaExhaustedBody(), 429);
            }

            return Http::response([
                'embedding' => ['values' => [0.1, 0.2, 0.3]],
            ], 200);
        });

        $vec = app(GeminiService::class)->embed('teks', [
            'api_key' => 'teacher-key-gurutes01',
        ]);

        $this->assertSame([0.1, 0.2, 0.3], $vec);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'key=teacher-key-gurutes01'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'key=school-key-sekolahtest01'));
    }

    public function test_embed_gagal_bila_guru_dan_sekolah_sama_sama_habis(): void
    {
        Http::fake([
            '*embedContent*' => Http::response($this->dailyQuotaExhaustedBody(), 429),
        ]);

        $this->expectException(AiDailyQuotaExhaustedException::class);

        app(GeminiService::class)->embed('teks', [
            'api_key' => 'teacher-key-gurutes01',
        ]);
    }

    /** Body 429 harian Gemini: quotaId harus mengandung "PerDay" (lihat isDailyQuotaError). */
    private function dailyQuotaExhaustedBody(): array
    {
        return [
            'error' => [
                'code' => 429,
                'status' => 'RESOURCE_EXHAUSTED',
                'message' => 'Resource exhausted',
                'details' => [[
                    'violations' => [[
                        'quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier',
                    ]],
                ]],
            ],
        ];
    }
}
