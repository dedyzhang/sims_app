<?php

namespace Tests\Unit;

use App\Services\GeminiTtsSseParser;
use GuzzleHttp\Psr7\PumpStream;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GeminiTtsSseParserTest extends TestCase
{
    public function test_it_parses_multi_event_audio_split_across_stream_buffers(): void
    {
        $first = $this->event(str_repeat("\0", 12));
        $second = $this->event(str_repeat("\1", 8), 'STOP');
        $fragments = str_split($first.$second, 7);
        $stream = new PumpStream(function () use (&$fragments): string|false {
            return array_shift($fragments) ?? false;
        });
        $audio = '';

        $result = (new GeminiTtsSseParser)->parse($stream, function (string $chunk) use (&$audio): void {
            $audio .= $chunk;
        });

        $this->assertSame(20, $result['audio_bytes']);
        $this->assertSame(2, $result['event_count']);
        $this->assertSame('STOP', $result['finish_reason']);
        $this->assertSame(str_repeat("\0", 12).str_repeat("\1", 8), $audio);
    }

    #[DataProvider('invalidStreamProvider')]
    public function test_it_rejects_invalid_or_incomplete_streams(string $sse, string $expectedMessage): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        (new GeminiTtsSseParser)->parse(Utils::streamFor($sse), static function (): void {});
    }

    public static function invalidStreamProvider(): array
    {
        return [
            'malformed json' => ["data: {rusak}\n\n", 'JSON yang tidak valid'],
            'invalid base64' => ["data: {\"candidates\":[{\"finishReason\":\"STOP\",\"content\":{\"parts\":[{\"inlineData\":{\"mimeType\":\"audio/L16;rate=24000\",\"data\":\"%%%\"}}]}}]}\n\n", 'base64 yang tidak valid'],
            'no audio' => ["data: {\"candidates\":[{\"finishReason\":\"STOP\",\"content\":{\"parts\":[]}}]}\n\n", 'tidak mengembalikan data audio'],
            'other finish reason' => ["data: {\"candidates\":[{\"finishReason\":\"OTHER\",\"content\":{\"parts\":[{\"inlineData\":{\"mimeType\":\"audio/L16;rate=24000\",\"data\":\"AAE=\"}}]}}]}\n\n", 'finish reason: OTHER'],
        ];
    }

    private function event(string $audio, ?string $finishReason = null): string
    {
        $candidate = ['content' => ['parts' => [['inlineData' => [
            'mimeType' => 'audio/L16;rate=24000',
            'data' => base64_encode($audio),
        ]]]]];
        if ($finishReason !== null) {
            $candidate['finishReason'] = $finishReason;
        }

        return 'data: '.json_encode(['candidates' => [$candidate]], JSON_THROW_ON_ERROR)."\n\n";
    }
}
