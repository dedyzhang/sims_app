<?php

namespace App\Jobs;

use App\Exceptions\AiProviderUnavailableException;
use App\Models\AiTeacherAudioAsset;
use App\Services\GeminiTtsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GenerateTeacherAudioJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 1800;

    public bool $failOnTimeout = true;

    public array $backoff = [15, 45];

    public function __construct(public string $audioUuid) {}

    public function handle(GeminiTtsService $tts): void
    {
        $asset = AiTeacherAudioAsset::find($this->audioUuid);
        if (! $asset || $asset->status === 'ready') {
            return;
        }

        $asset->update(['status' => 'processing', 'error_message' => null]);
        try {
            $result = $tts->synthesize($asset->text_snapshot, [
                'api_key' => $asset->user?->plainGeminiApiKey(),
                'fallback_api_key' => config('ai.api_key'),
                'model' => $asset->model,
                'voice' => $asset->voice,
                'voice_gender' => $asset->voice_gender,
                'vibe' => $asset->vibe,
                'vibe_prompt' => $asset->style_prompt,
                'tempo_percent' => $asset->tempo_percent,
                'language' => $asset->language,
            ]);
            $extension = $result['extension'] ?? ($result['mime'] === 'audio/mpeg' ? 'mp3' : 'wav');
            $path = 'ai-teacher-audio/'.$asset->user_uuid.'/'.$asset->uuid.'.'.$extension;
            Storage::disk($asset->disk)->put($path, $result['binary']);
            $asset->update([
                'status' => 'ready', 'path' => $path, 'mime' => $result['mime'],
                'size_bytes' => $result['size_bytes'], 'duration_ms' => $result['duration_ms'],
                'error_message' => null,
            ]);
        } catch (AiProviderUnavailableException $e) {
            $asset->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000)]);
        } catch (Throwable $e) {
            $asset->update(['status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000)]);
        }
    }

    public function failed(Throwable $e): void
    {
        AiTeacherAudioAsset::where('uuid', $this->audioUuid)->update([
            'status' => 'failed', 'error_message' => mb_substr($e->getMessage(), 0, 1000),
        ]);
    }
}
