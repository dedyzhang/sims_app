<?php

namespace Tests\Unit;

use App\Integrations\Ludensa\SimsGeminiAiJsonGenerator;
use App\Services\GeminiService;
use Ludensa\Contracts\GeneratesAiJson;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SimsGeminiAiJsonGeneratorTest extends TestCase
{
    public function test_tersedia_mengikuti_gemini_service(): void
    {
        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('enabled')->once()->andReturn(true);
        });

        $generator = app(SimsGeminiAiJsonGenerator::class);

        $this->assertTrue($generator->tersedia());
    }

    public function test_generate_json_memanggil_gemini_service_dan_parse_hasil(): void
    {
        $json = '{"pertanyaan":"2+2?","pilihan":{"A":"3","B":"4","C":"5","D":"6"},"kunci":"B","penjelasan":"4"}';

        $this->mock(GeminiService::class, function (MockInterface $mock) use ($json) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn(['text' => $json, 'model' => 'gemini-test', 'prompt_tokens' => 1, 'completion_tokens' => 2]);
        });

        $hasil = app(SimsGeminiAiJsonGenerator::class)->generateJson('buat soal');

        $this->assertSame('2+2?', $hasil['pertanyaan'] ?? null);
        $this->assertSame('B', $hasil['kunci'] ?? null);
    }

    public function test_binding_generates_ai_json_ke_adapter_sims(): void
    {
        if (! interface_exists(GeneratesAiJson::class)) {
            $this->markTestSkipped('Package Ludensa belum terpasang.');
        }

        $resolved = app(GeneratesAiJson::class);

        $this->assertInstanceOf(SimsGeminiAiJsonGenerator::class, $resolved);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
