<?php

namespace Tests\Unit;

use App\Services\GeminiService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiServiceProbeTest extends TestCase
{
    private GeminiService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(GeminiService::class);
    }

    public function test_probe_api_key_empty_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API key kosong.');

        $this->service->probeApiKey('   ');
    }

    public function test_probe_api_key_openrouter_key_rejected_immediately(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('key OpenRouter');

        $this->service->probeApiKey('sk-or-v1-abcdef1234567890');
    }

    public function test_probe_api_key_openai_key_rejected_immediately(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('key OpenAI');

        $this->service->probeApiKey('sk-abcdef1234567890abcdef');
    }

    public function test_probe_api_key_successful(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    ['content' => ['parts' => [['text' => 'pong']]]],
                ],
            ], 200),
        ]);

        // Tidak boleh melempar exception
        $this->service->probeApiKey('AIzaSyValidDummyKeyForTesting12345678');
        $this->assertTrue(true);
    }

    public function test_probe_api_key_invalid_400_gives_clean_message(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 400,
                    'message' => 'API key not valid. Please pass a valid API key.',
                    'details' => [
                        ['reason' => 'API_KEY_INVALID'],
                    ],
                ],
            ], 400),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API key tidak valid atau belum aktif di Google AI Studio.');

        $this->service->probeApiKey('AIzaSyInvalidKey');
    }

    public function test_probe_api_key_403_gives_clear_message(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'error' => [
                    'code' => 403,
                    'message' => 'The caller does not have permission',
                ],
            ], 403),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('API key ditolak Google (403)');

        $this->service->probeApiKey('AIzaSyBlockedKey');
    }

    public function test_probe_api_key_connection_error_gives_actionable_hint(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => function () {
                throw new ConnectionException('cURL error 28: Operation timed out after 25000 milliseconds');
            },
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Waktu koneksi habis');

        $this->service->probeApiKey('AIzaSyTimeoutKey');
    }
}
