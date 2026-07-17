<?php

namespace App\Support;

use App\Services\GeminiService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Mengubah penanda [GAMBAR: deskripsi] pada teks soal menjadi file gambar AI
 * dan token [[AI_IMG:path|caption]] yang dipahami pratinjau/PDF.
 */
final class QuizImageEnricher
{
    public const MARKER_PATTERN = '/\[GAMBAR:\s*(.+?)\]/iu';

    public const TOKEN_PATTERN = '/\[\[AI_IMG:([^\]|]+)(?:\|([^\]]*))?\]\]/u';

    public function __construct(private readonly GeminiService $gemini) {}

    /**
     * @return array{text:string,images:list<array{path:string,url:string,caption:string,model:?string}>,generated:int,failed:int}
     */
    public function enrich(string $quizText, string $apiKey, ?string $userId = null): array
    {
        $max = max(1, (int) config('ai.image.max_per_quiz', 5));
        $matches = [];
        preg_match_all(self::MARKER_PATTERN, $quizText, $matches, PREG_SET_ORDER);

        if ($matches === []) {
            return [
                'text' => $quizText,
                'images' => [],
                'generated' => 0,
                'failed' => 0,
            ];
        }

        $images = [];
        $generated = 0;
        $failed = 0;
        $text = $quizText;
        $seen = [];

        foreach ($matches as $match) {
            if ($generated >= $max) {
                break;
            }

            $full = $match[0];
            $caption = trim($match[1]);
            if ($caption === '' || isset($seen[$full])) {
                continue;
            }
            $seen[$full] = true;

            try {
                $image = $this->gemini->generateImage(
                    $this->imagePrompt($caption),
                    [
                        'api_key' => $apiKey,
                        'timeout' => (int) config('ai.image.timeout', 90),
                        'retries' => 1,
                    ],
                );

                $stored = $this->store($image['binary'], $image['mime'], $userId);
                $token = '[[AI_IMG:'.$stored['path'].'|'.$this->sanitizeCaption($caption).']]';
                $text = str_replace($full, $token, $text);
                $images[] = [
                    'path' => $stored['path'],
                    'url' => $stored['url'],
                    'caption' => $caption,
                    'model' => $image['model'],
                ];
                $generated++;
            } catch (Throwable) {
                $failed++;
                // Biarkan penanda [GAMBAR: ...] tetap — guru masih bisa lampirkan manual.
            }
        }

        return [
            'text' => $text,
            'images' => $images,
            'generated' => $generated,
            'failed' => $failed,
        ];
    }

    /** @return list<array{path:string,url:string,caption:string}> */
    public static function extractTokens(string $text): array
    {
        $out = [];
        if (! preg_match_all(self::TOKEN_PATTERN, $text, $matches, PREG_SET_ORDER)) {
            return $out;
        }

        foreach ($matches as $match) {
            $path = trim($match[1]);
            $caption = trim((string) ($match[2] ?? ''));
            $out[] = [
                'path' => $path,
                'url' => self::publicUrl($path),
                'caption' => $caption,
            ];
        }

        return $out;
    }

    public static function stripTokens(string $text): string
    {
        $text = preg_replace(self::TOKEN_PATTERN, '', $text) ?? $text;

        return trim(preg_replace('/\s{2,}/u', ' ', $text) ?? $text);
    }

    public static function absolutePath(string $relative): ?string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $disk = (string) config('ai.image.disk', 'public');
        $full = Storage::disk($disk)->path($relative);

        return is_file($full) ? $full : null;
    }

    public static function publicUrl(string $relative): string
    {
        $relative = ltrim(str_replace('\\', '/', $relative), '/');
        $disk = (string) config('ai.image.disk', 'public');

        return Storage::disk($disk)->url($relative);
    }

    private function imagePrompt(string $caption): string
    {
        return "Buat satu gambar edukatif untuk soal sekolah Indonesia.\n"
            ."Deskripsi: {$caption}\n"
            ."Gaya: diagram/sketsa jelas, kontras tinggi, cocok dicetak hitam-putih, "
            ."tanpa watermark, tanpa teks panjang, tanpa logo merek.";
    }

    private function sanitizeCaption(string $caption): string
    {
        $caption = str_replace(['[', ']', '|'], ['(', ')', '/'], $caption);

        return Str::limit($caption, 120, '');
    }

    /** @return array{path:string,url:string} */
    private function store(string $binary, string $mime, ?string $userId): array
    {
        $ext = match (strtolower($mime)) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/webp' => 'webp',
            default => 'png',
        };

        $dir = trim((string) config('ai.image.directory', 'ai-quiz-images'), '/');
        $userPart = $userId ? Str::slug(Str::limit($userId, 36, '')) : 'anon';
        $path = $dir.'/'.$userPart.'/'.now()->format('Ymd').'/'.Str::uuid()->toString().'.'.$ext;
        $disk = (string) config('ai.image.disk', 'public');

        Storage::disk($disk)->put($path, $binary);

        return [
            'path' => $path,
            'url' => Storage::disk($disk)->url($path),
        ];
    }
}
