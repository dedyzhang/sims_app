<?php

namespace App\Services;

use App\Exceptions\AiProviderUnavailableException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GeminiTtsService
{
    private float $lastRequestStartedAt = 0.0;

    public function __construct(
        private readonly GeminiTtsSseParser $sseParser,
        private readonly GeminiService $gemini,
    ) {}

    /** @return array{binary:string,mime:string,extension:string,model:string,size_bytes:int,duration_ms:int,bitrate?:string} */
    public function synthesize(string $text, array $options = []): array
    {
        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Narasi audio wajib diisi.');
        }

        $text = $this->translateForSelectedLanguage($text, $options);
        $chunks = $this->chunks($text, (int) config('ai.tts.chunk_chars', 1000));
        $base = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'sims-tts-'.bin2hex(random_bytes(8));
        $pcmPath = $base.'.pcm';
        $outputPath = $base.'.'.$this->outputFormat($options);
        $pcmHandle = fopen($pcmPath, 'w+b');
        if ($pcmHandle === false) {
            throw new RuntimeException('File sementara audio tidak dapat dibuat.');
        }

        $rate = 24000;
        $bits = 16;
        $channels = 1;
        $totalBytes = 0;

        try {
            foreach ($chunks as $index => $chunk) {
                $part = $this->synthesizeChunk($chunk, $options, $index + 1, count($chunks));
                try {
                    if ($index === 0) {
                        [$rate, $bits, $channels] = [$part['rate'], $part['bits'], $part['channels']];
                    } elseif ([$rate, $bits, $channels] !== [$part['rate'], $part['bits'], $part['channels']]) {
                        throw new RuntimeException('Format audio Gemini tidak konsisten antar bagian narasi.');
                    }

                    if ($index > 0) {
                        $silenceBytes = (int) round($rate * $channels * ($bits / 8) * 0.22);
                        $this->writeAll($pcmHandle, str_repeat("\0", $silenceBytes));
                        $totalBytes += $silenceBytes;
                    }

                    $partHandle = fopen($part['path'], 'rb');
                    if ($partHandle === false) {
                        throw new RuntimeException('Bagian audio sementara tidak dapat dibaca.');
                    }
                    stream_copy_to_stream($partHandle, $pcmHandle);
                    fclose($partHandle);
                    $totalBytes += $part['size_bytes'];
                } finally {
                    @unlink($part['path']);
                }
            }

            fflush($pcmHandle);
            fclose($pcmHandle);
            $pcmHandle = null;

            $durationMs = (int) round($totalBytes / max(1, $rate * $channels * ($bits / 8)) * 1000);
            $model = (string) ($options['model'] ?? config('ai.tts.model'));

            if ($this->outputFormat($options) === 'wav') {
                $this->createWavFile($pcmPath, $outputPath, $rate, $bits, $channels, $totalBytes);
                $binary = $this->readOutput($outputPath, 'WAV');

                return [
                    'binary' => $binary,
                    'mime' => 'audio/wav',
                    'extension' => 'wav',
                    'model' => $model,
                    'size_bytes' => strlen($binary),
                    'duration_ms' => $durationMs,
                ];
            }

            $bitrate = $this->mp3Bitrate($options);
            $this->createMp3File($pcmPath, $outputPath, $rate, $channels, $bitrate);
            $binary = $this->readOutput($outputPath, 'MP3');

            return [
                'binary' => $binary,
                'mime' => 'audio/mpeg',
                'extension' => 'mp3',
                'model' => $model,
                'size_bytes' => strlen($binary),
                'duration_ms' => $durationMs,
                'bitrate' => $bitrate,
            ];
        } finally {
            if (is_resource($pcmHandle)) {
                fclose($pcmHandle);
            }
            @unlink($pcmPath);
            @unlink($outputPath);
        }
    }

    /** @return array{path:string,size_bytes:int,rate:int,bits:int,channels:int} */
    private function synthesizeChunk(string $chunk, array $options, int $part, int $total): array
    {
        $personalKey = trim((string) ($options['api_key'] ?? ''));
        if ($personalKey === '') {
            throw new RuntimeException('API key Gemini guru belum tersedia.');
        }

        $fallbackKey = trim((string) ($options['fallback_api_key'] ?? ''));
        $keys = [['value' => $personalKey, 'type' => 'personal']];
        if ($fallbackKey !== '' && ! hash_equals($personalKey, $fallbackKey)) {
            $keys[] = ['value' => $fallbackKey, 'type' => 'school'];
        }

        $model = trim((string) ($options['model'] ?? config('ai.tts.model')));
        $chunkPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'sims-tts-chunk-'.bin2hex(random_bytes(8)).'.pcm';
        $maxAttempts = max(1, (int) ($options['retries'] ?? config('ai.tts.retries', 2)) + 1);
        $keyIndex = 0;

        while (true) {
            $key = $keys[$keyIndex];
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $startedAt = microtime(true);
                try {
                    $this->throttleRequest($options);
                    $startedAt = microtime(true);
                    $response = $this->requestChunk($chunk, $options, $part, $total, $model, $key['value']);
                    if (! $response->successful()) {
                        $message = $this->errorMessage($response);
                        $status = $response->status();
                        $dailyQuota = $status === 429 && $this->isDailyQuota($response, $message);
                        $this->logAttempt($model, $part, $total, $attempt, $status, null, $startedAt, 'http_error');

                        if ($dailyQuota) {
                            if ($key['type'] === 'personal' && isset($keys[$keyIndex + 1])) {
                                $keyIndex++;

                                continue 2;
                            }

                            $this->throwHttpError($status, $message, true);
                        }

                        if (($status === 429 || $status >= 500) && $attempt < $maxAttempts) {
                            $this->sleepBeforeRetry($response, $attempt, $options);

                            continue;
                        }

                        $this->throwHttpError($status, $message);
                    }

                    $result = $this->writeSuccessfulResponse($response, $chunkPath);
                    [$rate, $bits, $channels] = $this->audioSpec($result['mime']);
                    $this->logAttempt($model, $part, $total, $attempt, 200, $result['finish_reason'], $startedAt, 'success');

                    return [
                        'path' => $chunkPath,
                        'size_bytes' => $result['audio_bytes'],
                        'rate' => $rate,
                        'bits' => $bits,
                        'channels' => $channels,
                    ];
                } catch (ConnectionException $e) {
                    @unlink($chunkPath);
                    $this->logAttempt($model, $part, $total, $attempt, null, null, $startedAt, 'connection_error', $e);
                    if ($attempt < $maxAttempts) {
                        $this->sleepMilliseconds($this->retryDelayMs($attempt, $options));

                        continue;
                    }

                    throw new AiProviderUnavailableException('Server tidak dapat terhubung ke Gemini TTS. Periksa koneksi HTTPS/proxy lalu coba lagi.');
                } catch (AiProviderUnavailableException $e) {
                    @unlink($chunkPath);
                    throw $e;
                } catch (Throwable $e) {
                    @unlink($chunkPath);
                    $this->logAttempt($model, $part, $total, $attempt, 200, null, $startedAt, 'invalid_stream', $e);
                    if ($attempt < $maxAttempts) {
                        $delay = array_key_exists('retry_delay_ms', $options)
                            ? $this->retryDelayMs($attempt, $options)
                            : (int) config('ai.tts.stream_retry_delay_ms', 60000);
                        $this->sleepMilliseconds($delay);

                        continue;
                    }

                    throw $e;
                }
            }
        }
    }

    private function requestChunk(string $chunk, array $options, int $part, int $total, string $model, string $apiKey): Response
    {
        $voice = trim((string) ($options['voice'] ?? 'Kore'));
        $language = trim((string) ($options['language'] ?? 'id-ID'));
        $gender = trim((string) ($options['voice_gender'] ?? 'wanita'));
        $vibe = trim((string) ($options['vibe'] ?? 'ceria'));
        $tempo = max(70, min(130, (int) ($options['tempo_percent'] ?? 100)));
        $vibePrompt = (string) ($options['vibe_prompt'] ?? config('ai.tts.vibes.'.$vibe, 'Hangat dan natural.'));
        $tempoDescription = $tempo < 95 ? 'lebih lambat dan reflektif' : ($tempo > 105 ? 'lebih cepat tetapi tetap mudah dipahami' : 'sedang dan natural');
        $languageLabel = (string) config('ai.tts.languages.'.$language, $language);

        $prompt = <<<PROMPT
Synthesize a single-speaker educational narration using the selected language/accent: {$languageLabel} (kode bahasa: {$language}).

Director notes:
- Analisis konteks narasi dan pilihan pengguna sebelum membacakan.
- Karakter suara: {$gender}. Voice preset: {$voice}.
- Vibe: {$vibePrompt}
- Tempo: {$tempo}% ({$tempoDescription}).
- Gunakan intonasi, penekanan kata, artikulasi, jeda, dan prosodi yang sesuai bahasa/logat terpilih.
- Transcript sudah disiapkan dalam bahasa target. Bacakan natural dalam bahasa tersebut tanpa menerjemahkan ulang.
- Jangan membaca director notes, label, instruksi, atau nomor bagian ini.
- Bacakan isi transcript secara akurat tanpa menambah, mengurangi, atau mengubah fakta.
- Ini bagian {$part} dari {$total}; sambungkan nuansanya secara natural.

Transcript yang harus dibacakan:
{$chunk}
PROMPT;

        $body = [
            'contents' => [['role' => 'user', 'parts' => [['text' => $prompt]]]],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => ['voiceConfig' => ['prebuiltVoiceConfig' => ['voiceName' => $voice]]],
            ],
        ];

        return Http::connectTimeout(10)
            ->timeout((int) ($options['timeout'] ?? config('ai.tts.timeout', 120)))
            ->withOptions(['stream' => true])
            ->withHeaders(['x-goog-api-key' => $apiKey])
            ->accept('text/event-stream')
            ->post(rtrim((string) config('ai.base_url'), '/')."/models/{$model}:streamGenerateContent?alt=sse", $body);
    }

    private function translateForSelectedLanguage(string $text, array $options): string
    {
        $language = trim((string) ($options['language'] ?? 'id-ID'));
        if ($language === 'id-ID' || ($options['translate'] ?? true) === false) {
            return $text;
        }

        $languageLabel = (string) config('ai.tts.languages.'.$language, $language);
        $prompt = <<<PROMPT
Terjemahkan narasi pembelajaran berikut seluruhnya ke {$languageLabel} (kode bahasa {$language}).

Aturan wajib:
- Keluarkan hanya teks hasil terjemahan, tanpa judul tambahan, catatan, markdown, atau penjelasan.
- Gunakan bahasa yang natural, jelas, dan sesuai untuk siswa.
- Pertahankan seluruh fakta, nama diri, istilah penting, angka, urutan, dan struktur paragraf.
- Jangan meringkas, menambah informasi, atau menghilangkan isi.
- Jika teks sudah memakai bahasa target, pertahankan isinya dan rapikan hanya bila diperlukan.
- Terjemahkan bagian berbahasa lain agar hasil akhir konsisten dalam bahasa target, kecuali nama diri atau istilah yang memang harus dipertahankan.

Narasi sumber:
{$text}
PROMPT;

        try {
            $result = $this->gemini->generate($prompt, [
                'api_key' => (string) ($options['api_key'] ?? ''),
                'model' => (string) ($options['translation_model'] ?? config('ai.tts.translation_model', config('ai.model'))),
                'system' => 'Anda adalah penerjemah profesional untuk materi pendidikan. Patuhi bahasa target dan keluarkan hanya hasil terjemahan.',
                'include_global_system_prompt' => false,
                'answer_style' => '',
                'temperature' => 0.1,
                'max_output_tokens' => (int) config('ai.tts.translation_max_output_tokens', 8192),
                'timeout' => (int) config('ai.tts.translation_timeout', 120),
            ]);
        } catch (AiProviderUnavailableException $e) {
            throw new AiProviderUnavailableException('Narasi gagal diterjemahkan ke bahasa yang dipilih. '.$e->getMessage());
        } catch (Throwable $e) {
            throw new RuntimeException('Narasi gagal diterjemahkan ke bahasa yang dipilih: '.$e->getMessage(), 0, $e);
        }

        $translated = trim((string) ($result['text'] ?? ''));
        if ($translated === '') {
            throw new RuntimeException('Gemini tidak mengembalikan hasil terjemahan untuk audio.');
        }

        Log::info('Narasi TTS selesai diterjemahkan.', [
            'language' => $language,
            'source_characters' => mb_strlen($text),
            'translated_characters' => mb_strlen($translated),
            'model' => $result['model'] ?? null,
        ]);

        return $translated;
    }

    /** @return array{audio_bytes:int,finish_reason:string,mime:string} */
    private function writeSuccessfulResponse(Response $response, string $path): array
    {
        $handle = fopen($path, 'w+b');
        if ($handle === false) {
            throw new RuntimeException('File sementara bagian audio tidak dapat dibuat.');
        }

        try {
            $contentType = strtolower($response->header('Content-Type'));
            if (! str_contains($contentType, 'text/event-stream')) {
                return $this->writeBufferedJsonResponse($response, $handle);
            }

            $result = $this->sseParser->parse($response->toPsrResponse()->getBody(), function (string $audio) use ($handle): void {
                $this->writeAll($handle, $audio);
            });

            return [
                'audio_bytes' => $result['audio_bytes'],
                'finish_reason' => $result['finish_reason'],
                'mime' => $result['mime'],
            ];
        } finally {
            fclose($handle);
        }
    }

    /** @return array{audio_bytes:int,finish_reason:string,mime:string} */
    private function writeBufferedJsonResponse(Response $response, $handle): array
    {
        $candidate = $response->json('candidates.0', []);
        $bytes = 0;
        $mime = '';

        foreach ((array) data_get($candidate, 'content.parts', []) as $part) {
            $inline = $part['inlineData'] ?? $part['inline_data'] ?? null;
            if (! is_array($inline) || empty($inline['data'])) {
                continue;
            }
            $raw = base64_decode((string) $inline['data'], true);
            if ($raw === false || $raw === '') {
                throw new RuntimeException('Gemini mengembalikan audio base64 yang tidak valid.');
            }
            $mime = (string) ($inline['mimeType'] ?? $inline['mime_type'] ?? 'audio/L16;rate=24000');
            $this->writeAll($handle, $raw);
            $bytes += strlen($raw);
        }

        if ($bytes === 0) {
            throw new RuntimeException('Gemini tidak mengembalikan data audio.');
        }

        return ['audio_bytes' => $bytes, 'finish_reason' => 'STOP', 'mime' => $mime];
    }

    /** @return list<string> */
    private function chunks(string $text, int $limit): array
    {
        $limit = max(200, $limit);
        $sentences = preg_split('/(?<=[.!?。！？])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [trim($text)];
        $chunks = [];
        $current = '';

        foreach ($sentences as $sentence) {
            if ($current !== '' && mb_strlen($current.' '.$sentence) > $limit) {
                $chunks[] = $current;
                $current = '';
            }
            if (mb_strlen($sentence) <= $limit) {
                $current = $current === '' ? $sentence : $current.' '.$sentence;

                continue;
            }
            if ($current !== '') {
                $chunks[] = $current;
                $current = '';
            }
            while (mb_strlen($sentence) > $limit) {
                $prefix = mb_substr($sentence, 0, $limit + 1);
                $cut = mb_strrpos($prefix, ' ');
                $cut = $cut === false || $cut < (int) floor($limit * 0.6) ? $limit : $cut;
                $chunks[] = trim(mb_substr($sentence, 0, $cut));
                $sentence = trim(mb_substr($sentence, $cut));
            }
            $current = $sentence;
        }

        if ($current !== '') {
            $chunks[] = $current;
        }

        return array_values(array_filter($chunks, static fn (string $chunk) => trim($chunk) !== ''));
    }

    /** @return array{0:int,1:int,2:int} */
    private function audioSpec(string $mime): array
    {
        preg_match('/rate=(\d+)/i', $mime, $rateMatch);

        return [isset($rateMatch[1]) ? (int) $rateMatch[1] : 24000, 16, 1];
    }

    private function outputFormat(array $options): string
    {
        return strtolower(trim((string) ($options['output_format'] ?? config('ai.tts.output_format', 'mp3')))) === 'wav' ? 'wav' : 'mp3';
    }

    private function mp3Bitrate(array $options): string
    {
        $bitrate = strtolower(trim((string) ($options['mp3_bitrate'] ?? config('ai.tts.mp3_bitrate', '128k'))));

        return in_array($bitrate, ['128k', '256k'], true) ? $bitrate : '128k';
    }

    private function createMp3File(string $pcmPath, string $mp3Path, int $rate, int $channels, string $bitrate): void
    {
        $this->runFfmpeg([
            (string) config('ai.tts.ffmpeg_binary', 'ffmpeg'),
            '-hide_banner', '-loglevel', 'error', '-y',
            '-f', 's16le', '-ar', (string) $rate, '-ac', (string) $channels, '-i', $pcmPath,
            '-vn', '-codec:a', 'libmp3lame', '-b:a', $bitrate, $mp3Path,
        ], $mp3Path, 'MP3');
    }

    private function createWavFile(string $pcmPath, string $wavPath, int $rate, int $bits, int $channels, int $pcmBytes): void
    {
        $input = fopen($pcmPath, 'rb');
        $output = fopen($wavPath, 'w+b');
        if ($input === false || $output === false) {
            if (is_resource($input)) {
                fclose($input);
            }
            if (is_resource($output)) {
                fclose($output);
            }
            throw new RuntimeException('File WAV sementara tidak dapat dibuat.');
        }

        $blockAlign = $channels * intdiv($bits, 8);
        $byteRate = $rate * $blockAlign;
        $header = 'RIFF'.pack('V', 36 + $pcmBytes).'WAVEfmt '.pack('VvvVVv', 16, 1, $channels, $rate, $byteRate, $blockAlign)
            .pack('v', $bits).'data'.pack('V', $pcmBytes);
        $this->writeAll($output, $header);
        stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);
    }

    private function runFfmpeg(array $command, string $outputPath, string $label): void
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException("FFmpeg tidak bisa dijalankan untuk membuat {$label}.");
        }

        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || ! is_file($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException("FFmpeg gagal membuat {$label}: ".mb_substr(trim($stderr), 0, 500));
        }
    }

    private function readOutput(string $path, string $label): string
    {
        $binary = file_get_contents($path);
        if ($binary === false || $binary === '') {
            throw new RuntimeException("File {$label} hasil pemrosesan tidak valid.");
        }

        return $binary;
    }

    private function writeAll($handle, string $data): void
    {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $result = fwrite($handle, substr($data, $written));
            if ($result === false || $result === 0) {
                throw new RuntimeException('Gagal menulis file sementara audio.');
            }
            $written += $result;
        }
    }

    private function errorMessage(Response $response): string
    {
        return (string) ($response->json('error.message') ?: mb_substr($response->body(), 0, 1000) ?: 'Gemini TTS gagal memproses permintaan.');
    }

    private function throwHttpError(int $status, string $message, bool $dailyQuota = false): never
    {
        if ($status >= 500) {
            throw new AiProviderUnavailableException('Layanan Gemini TTS sedang gangguan. Bagian audio dapat dicoba lagi nanti.');
        }
        if ($status === 429) {
            if ($dailyQuota) {
                throw new AiProviderUnavailableException('Kuota harian Gemini TTS Free Tier untuk project ini sudah habis. Coba lagi setelah kuota harian direset atau gunakan key AI Studio project lain.');
            }

            throw new AiProviderUnavailableException('Kuota atau batas permintaan Gemini TTS tercapai. Coba lagi setelah kuota tersedia.');
        }
        if (in_array($status, [401, 403], true)) {
            throw new RuntimeException('API key Gemini ditolak oleh layanan TTS. Periksa key AI Studio guru.');
        }
        if ($status === 404) {
            throw new RuntimeException('Model Gemini TTS tidak tersedia untuk API key atau region ini.');
        }

        throw new RuntimeException('Permintaan Gemini TTS ditolak: '.mb_substr($message, 0, 300));
    }

    private function isDailyQuota(Response $response, string $message): bool
    {
        $message = strtolower($message);

        foreach ((array) $response->json('error.details', []) as $detail) {
            foreach ((array) ($detail['violations'] ?? []) as $violation) {
                $quotaId = strtolower((string) ($violation['quotaId'] ?? ''));
                if (str_contains($quotaId, 'perday') || str_contains($quotaId, 'per_day')) {
                    return true;
                }
            }
        }

        return str_contains($message, 'per_day')
            || str_contains($message, 'per day')
            || str_contains($message, 'daily')
            || str_contains($message, 'requestsperday');
    }

    private function sleepBeforeRetry(Response $response, int $attempt, array $options): void
    {
        $retryAfter = trim($response->header('Retry-After'));
        $retryInfo = collect((array) $response->json('error.details', []))
            ->pluck('retryDelay')
            ->filter()
            ->first();

        if (ctype_digit($retryAfter)) {
            $milliseconds = (int) $retryAfter * 1000;
        } elseif (is_string($retryInfo) && preg_match('/^([\d.]+)s$/', $retryInfo, $match)) {
            $milliseconds = (int) ceil((float) $match[1] * 1000);
        } elseif ($response->status() === 429) {
            $milliseconds = (int) config('ai.tts.rate_limit_retry_ms', 60000);
        } else {
            $milliseconds = $this->retryDelayMs($attempt, $options);
        }

        $this->sleepMilliseconds(min($milliseconds, (int) config('ai.tts.max_retry_delay_ms', 65000)));
    }

    private function throttleRequest(array $options): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $minimumMs = max(0, (int) ($options['min_request_interval_ms'] ?? config('ai.tts.min_request_interval_ms', 21000)));
        if ($this->lastRequestStartedAt > 0 && $minimumMs > 0) {
            $elapsedMs = (microtime(true) - $this->lastRequestStartedAt) * 1000;
            $this->sleepMilliseconds((int) max(0, ceil($minimumMs - $elapsedMs)));
        }

        $this->lastRequestStartedAt = microtime(true);
    }

    private function retryDelayMs(int $attempt, array $options): int
    {
        $base = max(0, (int) ($options['retry_delay_ms'] ?? config('ai.retry_delay', 500)));

        return $base * (2 ** max(0, $attempt - 1));
    }

    private function sleepMilliseconds(int $milliseconds): void
    {
        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    private function logAttempt(
        string $model,
        int $part,
        int $total,
        int $attempt,
        ?int $status,
        ?string $finishReason,
        float $startedAt,
        string $outcome,
        ?Throwable $exception = null,
    ): void {
        Log::info('Gemini TTS chunk selesai diproses.', array_filter([
            'model' => $model,
            'chunk' => $part,
            'chunks_total' => $total,
            'attempt' => $attempt,
            'http_status' => $status,
            'finish_reason' => $finishReason,
            'elapsed_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'outcome' => $outcome,
            'exception' => $exception ? $exception::class : null,
            'message' => $exception ? mb_substr($exception->getMessage(), 0, 500) : null,
        ], static fn ($value) => $value !== null));
    }
}
