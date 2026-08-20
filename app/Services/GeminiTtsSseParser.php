<?php

namespace App\Services;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

class GeminiTtsSseParser
{
    /**
     * @param  callable(string, string): void  $onAudio
     * @return array{audio_bytes:int,event_count:int,finish_reason:string,mime:string}
     */
    public function parse(StreamInterface $stream, callable $onAudio): array
    {
        $buffer = '';
        $audioBytes = 0;
        $eventCount = 0;
        $finishReason = '';
        $mime = '';

        while (! $stream->eof()) {
            $buffer .= $stream->read(8192);
            $this->drainEvents($buffer, false, function (string $payload) use ($onAudio, &$audioBytes, &$eventCount, &$finishReason, &$mime): void {
                $this->consumePayload($payload, $onAudio, $audioBytes, $eventCount, $finishReason, $mime);
            });
        }

        $this->drainEvents($buffer, true, function (string $payload) use ($onAudio, &$audioBytes, &$eventCount, &$finishReason, &$mime): void {
            $this->consumePayload($payload, $onAudio, $audioBytes, $eventCount, $finishReason, $mime);
        });

        if ($audioBytes === 0) {
            throw new RuntimeException('Gemini tidak mengembalikan data audio.');
        }

        if ($finishReason !== 'STOP') {
            throw new RuntimeException('Stream audio Gemini tidak lengkap (finish reason: '.($finishReason ?: 'tidak tersedia').').');
        }

        return [
            'audio_bytes' => $audioBytes,
            'event_count' => $eventCount,
            'finish_reason' => $finishReason,
            'mime' => $mime,
        ];
    }

    private function drainEvents(string &$buffer, bool $flush, callable $consume): void
    {
        $buffer = str_replace("\r\n", "\n", $buffer);

        while (($separator = strpos($buffer, "\n\n")) !== false) {
            $event = substr($buffer, 0, $separator);
            $buffer = substr($buffer, $separator + 2);
            $payload = $this->eventData($event);
            if ($payload !== null) {
                $consume($payload);
            }
        }

        if ($flush && trim($buffer) !== '') {
            $payload = $this->eventData($buffer);
            $buffer = '';
            if ($payload !== null) {
                $consume($payload);
            }
        }
    }

    private function eventData(string $event): ?string
    {
        $lines = preg_split('/\n/', $event) ?: [];
        $data = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'data:')) {
                $data[] = ltrim(substr($line, 5));
            }
        }

        if ($data === []) {
            return null;
        }

        $payload = implode("\n", $data);

        return $payload === '[DONE]' ? null : $payload;
    }

    private function consumePayload(
        string $payload,
        callable $onAudio,
        int &$audioBytes,
        int &$eventCount,
        string &$finishReason,
        string &$mime,
    ): void {
        $decoded = json_decode($payload, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Stream Gemini TTS mengandung data JSON yang tidak valid.');
        }

        $eventCount++;
        $candidate = $decoded['candidates'][0] ?? [];
        if (! empty($candidate['finishReason'])) {
            $finishReason = strtoupper((string) $candidate['finishReason']);
        }

        foreach ((array) data_get($candidate, 'content.parts', []) as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline) || empty($inline['data'])) {
                continue;
            }

            $raw = base64_decode((string) $inline['data'], true);
            if ($raw === false || $raw === '') {
                throw new RuntimeException('Gemini mengembalikan audio base64 yang tidak valid.');
            }

            $partMime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'audio/L16;rate=24000');
            if ($mime !== '' && $mime !== $partMime) {
                throw new RuntimeException('Format audio Gemini berubah di tengah stream.');
            }

            $mime = $partMime;
            $audioBytes += strlen($raw);
            $onAudio($raw, $partMime);
        }
    }
}
