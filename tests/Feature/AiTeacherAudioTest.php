<?php

namespace Tests\Feature;

use App\Exceptions\AiProviderUnavailableException;
use App\Jobs\GenerateTeacherAudioJob;
use App\Models\AiTeacherAudioAsset;
use App\Models\Classroom;
use App\Models\GameQuiz;
use App\Models\User;
use App\Services\GeminiTtsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiTeacherAudioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.tts.dispatch' => 'queue']);
    }

    private function guru(): User
    {
        $guru = User::create(['username' => 'guru-tts-'.uniqid(), 'password' => Hash::make('password'), 'access' => 'guru']);
        $guru->setGeminiApiKey('AIzaSyTestPersonalKeyForTtsFeature01');

        return $guru->fresh();
    }

    public function test_teacher_can_queue_audio_from_free_text(): void
    {
        Queue::fake();
        $guru = $this->guru();

        $this->actingAs($guru)->postJson(route('ai.teacher.audio.create'), [
            'source_type' => 'free_text', 'text' => 'Fotosintesis adalah proses tumbuhan membuat makanan.',
            'title' => 'Fotosintesis', 'language' => 'id-ID', 'voice' => 'Kore', 'style' => 'tenang',
        ])->assertStatus(202)->assertJsonPath('audio.status', 'queued');

        $this->assertDatabaseHas('ai_teacher_audio_assets', [
            'user_uuid' => $guru->uuid,
            'status' => 'queued',
            'mime' => 'audio/mpeg',
        ]);
        Queue::assertPushed(GenerateTeacherAudioJob::class, fn (GenerateTeacherAudioJob $job) => $job->connection === 'tts');
    }

    public function test_stale_processing_audio_does_not_block_new_generation(): void
    {
        Queue::fake();
        $guru = $this->guru();
        $text = 'Narasi audio yang sebelumnya macet.';
        $stylePrompt = (string) config('ai.tts.vibes.tenang', config('ai.tts.styles.tenang', 'Natural dan jelas.'));

        $stale = AiTeacherAudioAsset::create([
            'user_uuid' => $guru->uuid,
            'source_type' => 'free_text',
            'title' => 'Audio Stale',
            'text_snapshot' => $text,
            'text_hash' => hash('sha256', $text),
            'language' => 'id-ID',
            'voice' => 'Kore',
            'voice_gender' => 'wanita',
            'vibe' => 'tenang',
            'tempo_percent' => 100,
            'style_prompt' => $stylePrompt,
            'model' => config('ai.tts.model'),
            'status' => 'processing',
            'disk' => 'local',
            'mime' => 'audio/mpeg',
        ]);
        $stale->forceFill(['updated_at' => now()->subMinutes(36)])->save();

        $this->actingAs($guru)->postJson(route('ai.teacher.audio.create'), [
            'source_type' => 'free_text',
            'text' => $text,
            'title' => 'Audio Baru',
            'language' => 'id-ID',
            'voice' => 'Kore',
            'style' => 'tenang',
        ])->assertStatus(202)->assertJsonPath('audio.status', 'queued');

        $this->assertDatabaseHas('ai_teacher_audio_assets', [
            'uuid' => $stale->uuid,
            'status' => 'failed',
            'error_message' => 'Proses audio sebelumnya terhenti. Silakan buat ulang audio.',
        ]);
        $this->assertSame(2, AiTeacherAudioAsset::where('user_uuid', $guru->uuid)->count());
        Queue::assertPushed(GenerateTeacherAudioJob::class);
    }

    public function test_stale_queued_audio_reports_inactive_worker_instead_of_waiting_forever(): void
    {
        $guru = $this->guru();
        $audio = AiTeacherAudioAsset::create([
            'user_uuid' => $guru->uuid,
            'source_type' => 'free_text',
            'title' => 'Audio Antrean Macet',
            'text_snapshot' => 'Narasi tertahan karena worker mati.',
            'text_hash' => hash('sha256', 'Narasi tertahan karena worker mati.'),
            'language' => 'id-ID',
            'voice' => 'Kore',
            'voice_gender' => 'wanita',
            'vibe' => 'tenang',
            'tempo_percent' => 100,
            'style_prompt' => 'Natural dan jelas.',
            'model' => config('ai.tts.model'),
            'status' => 'queued',
            'disk' => 'local',
            'mime' => 'audio/mpeg',
        ]);
        $audio->forceFill(['updated_at' => now()->subMinutes(6)])->save();

        $this->actingAs($guru)
            ->getJson(route('ai.teacher.audio.status', $audio))
            ->assertOk()
            ->assertJsonPath('audio.status', 'failed')
            ->assertJsonPath('audio.error_message', 'Antrean audio tidak diproses. Worker queue tidak aktif. Jalankan ulang pembuatan audio setelah worker tersedia.');
    }

    public function test_local_deferred_mode_does_not_require_database_worker(): void
    {
        Queue::fake();
        $guru = $this->guru();
        config(['ai.tts.dispatch' => 'deferred']);

        $this->actingAs($guru)->postJson(route('ai.teacher.audio.create'), [
            'source_type' => 'free_text',
            'text' => 'Narasi lokal diproses setelah respons browser.',
            'title' => 'Audio Deferred',
            'language' => 'id-ID',
            'voice' => 'Kore',
            'style' => 'tenang',
        ])->assertStatus(202)->assertJsonPath('audio.status', 'processing');

        Queue::assertPushed(GenerateTeacherAudioJob::class, fn (GenerateTeacherAudioJob $job) => $job->connection === 'deferred');
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_provider_unavailable_marks_audio_failed_immediately(): void
    {
        $guru = $this->guru();
        $asset = AiTeacherAudioAsset::create([
            'user_uuid' => $guru->uuid,
            'source_type' => 'free_text',
            'title' => 'Audio Rate Limit',
            'text_snapshot' => 'Audio yang terkena rate limit.',
            'text_hash' => hash('sha256', 'Audio yang terkena rate limit.'),
            'language' => 'id-ID',
            'voice' => 'Kore',
            'voice_gender' => 'wanita',
            'vibe' => 'tenang',
            'tempo_percent' => 100,
            'style_prompt' => 'Natural dan jelas.',
            'model' => config('ai.tts.model'),
            'status' => 'queued',
            'disk' => 'local',
            'mime' => 'audio/mpeg',
        ]);

        $this->mock(GeminiTtsService::class, function ($mock): void {
            $mock->shouldReceive('synthesize')
                ->once()
                ->andThrow(new AiProviderUnavailableException('Kuota atau batas permintaan Gemini TTS tercapai. Coba lagi nanti.'));
        });

        GenerateTeacherAudioJob::dispatchSync($asset->uuid);

        $this->assertDatabaseHas('ai_teacher_audio_assets', [
            'uuid' => $asset->uuid,
            'status' => 'failed',
            'error_message' => 'Kuota atau batas permintaan Gemini TTS tercapai. Coba lagi nanti.',
        ]);
    }

    public function test_teacher_can_queue_multilingual_audio_languages(): void
    {
        Queue::fake();
        $guru = $this->guru();

        foreach (['zh-CN', 'ja-JP', 'ar-SA'] as $language) {
            $this->actingAs($guru)->postJson(route('ai.teacher.audio.create'), [
                'source_type' => 'free_text',
                'text' => 'Contoh narasi pembelajaran untuk bahasa '.$language.'.',
                'title' => 'Narasi '.$language,
                'language' => $language,
                'voice' => 'Kore',
                'style' => 'tenang',
            ])->assertStatus(202)->assertJsonPath('audio.language', $language);

            $this->assertDatabaseHas('ai_teacher_audio_assets', [
                'user_uuid' => $guru->uuid,
                'language' => $language,
                'status' => 'queued',
                'mime' => 'audio/mpeg',
            ]);
        }
    }

    public function test_tts_service_encodes_pcm_as_mp3_128_kbps(): void
    {
        Http::fake(fn () => Http::response([
            'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'audio/L16;rate=24000', 'data' => base64_encode(str_repeat("\0", 4800))]]]]]],
        ], 200));

        $result = app(GeminiTtsService::class)->synthesize('Halo siswa.', ['api_key' => 'AIzaSyTestPersonalKeyForTtsFeature01']);

        $this->assertSame('audio/mpeg', $result['mime']);
        $this->assertSame('mp3', $result['extension']);
        $this->assertSame('128k', $result['bitrate']);
        $this->assertTrue(str_starts_with($result['binary'], 'ID3') || ord($result['binary'][0]) === 0xFF);
        $this->assertSame(100, $result['duration_ms']);
    }

    public function test_tts_service_allows_256_kbps_mp3(): void
    {
        Http::fake(fn () => Http::response([
            'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'audio/L16;rate=24000', 'data' => base64_encode(str_repeat("\0", 4800))]]]]]],
        ], 200));

        $result = app(GeminiTtsService::class)->synthesize('Halo siswa.', [
            'api_key' => 'AIzaSyTestPersonalKeyForTtsFeature01',
            'mp3_bitrate' => '256k',
        ]);

        $this->assertSame('audio/mpeg', $result['mime']);
        $this->assertSame('mp3', $result['extension']);
        $this->assertSame('256k', $result['bitrate']);
    }

    public function test_tts_service_can_return_lossless_wav_without_mp3_encoding(): void
    {
        Http::fake(fn () => Http::response([
            'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'audio/L16;rate=24000', 'data' => base64_encode(str_repeat("\0", 4800))]]]]]],
        ], 200));

        $result = app(GeminiTtsService::class)->synthesize('Halo siswa.', [
            'api_key' => 'AIzaSyTestPersonalKeyForTtsFeature01',
            'output_format' => 'wav',
        ]);

        $this->assertSame('audio/wav', $result['mime']);
        $this->assertSame('wav', $result['extension']);
        $this->assertStringStartsWith('RIFF', $result['binary']);
        $this->assertStringContainsString('WAVE', substr($result['binary'], 0, 12));
        $this->assertSame(100, $result['duration_ms']);
    }

    public function test_tts_service_sends_selected_language_to_gemini(): void
    {
        Http::fakeSequence()
            ->push($this->translatedTextResponse('今日は光合成について学びます。'), 200)
            ->push($this->bufferedAudioResponse(), 200);

        app(GeminiTtsService::class)->synthesize('Hari ini kita belajar tentang fotosintesis.', [
            'api_key' => 'AIzaSyTestPersonalKeyForTtsFeature01',
            'language' => 'ja-JP',
        ]);

        Http::assertSent(function ($request) {
            $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text', '');

            return str_contains($request->url(), ':generateContent')
                && str_contains($prompt, 'ja-JP')
                && str_contains($prompt, 'Terjemahkan narasi pembelajaran');
        });
        Http::assertSent(function ($request) {
            $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text', '');

            return str_contains($request->url(), ':streamGenerateContent?alt=sse')
                && str_contains($prompt, 'ja-JP')
                && str_contains($prompt, 'Japanese natural')
                && str_contains($prompt, '今日は光合成について学びます。')
                && ! str_contains($prompt, 'Hari ini kita belajar');
        });
        Http::assertSentCount(2);
    }

    public function test_indonesian_tts_skips_translation_request(): void
    {
        Http::fake(fn () => Http::response($this->bufferedAudioResponse(), 200));

        app(GeminiTtsService::class)->synthesize('Hari ini kita belajar fotosintesis.', [
            'api_key' => 'AIzaSyTestPersonalKeyForTtsFeature01',
            'language' => 'id-ID',
        ]);

        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), ':streamGenerateContent?alt=sse'));
    }

    public function test_non_indonesian_audio_does_not_reuse_pre_translation_asset(): void
    {
        Queue::fake();
        $guru = $this->guru();
        $text = 'Hari ini kita belajar tentang fotosintesis.';
        AiTeacherAudioAsset::create([
            'user_uuid' => $guru->uuid,
            'source_type' => 'free_text',
            'title' => 'Mandarin Lama',
            'text_snapshot' => $text,
            'text_hash' => hash('sha256', $text),
            'language' => 'zh-CN',
            'voice' => 'Kore',
            'voice_gender' => 'wanita',
            'vibe' => 'ceria',
            'tempo_percent' => 100,
            'style_prompt' => config('ai.tts.vibes.ceria'),
            'model' => config('ai.tts.model'),
            'status' => 'ready',
            'disk' => 'local',
            'path' => 'ai-teacher-audio/old.mp3',
            'mime' => 'audio/mpeg',
        ]);

        $this->actingAs($guru)->postJson(route('ai.teacher.audio.create'), [
            'source_type' => 'free_text',
            'text' => $text,
            'title' => 'Mandarin Baru',
            'language' => 'zh-CN',
            'voice_gender' => 'wanita',
            'voice' => 'Kore',
            'vibe' => 'ceria',
            'tempo_percent' => 100,
        ])->assertStatus(202)
            ->assertJsonPath('reused', null)
            ->assertJsonPath('audio.status', 'queued');

        $this->assertDatabaseCount('ai_teacher_audio_assets', 2);
        Queue::assertPushed(GenerateTeacherAudioJob::class);
    }

    public function test_tts_service_uses_latest_tts_specific_model(): void
    {
        Http::fake(fn () => Http::response([
            'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'audio/L16;rate=24000', 'data' => base64_encode(str_repeat("\0", 2400))]]]]]],
        ], 200));

        app(GeminiTtsService::class)->synthesize('Selamat belajar.', [
            'api_key' => 'AIzaSyTestPersonalKeyForTtsFeature01',
        ]);

        Http::assertSent(fn ($request) => str_contains(
            $request->url(),
            '/models/gemini-3.1-flash-tts-preview:streamGenerateContent?alt=sse'
        ));
    }

    public function test_narration_over_ten_minute_limit_is_rejected_before_queueing(): void
    {
        Queue::fake();
        $guru = $this->guru();

        $this->actingAs($guru)->postJson(route('ai.teacher.audio.create'), [
            'source_type' => 'free_text',
            'text' => str_repeat('kata ', 1201),
            'title' => 'Terlalu Panjang',
            'language' => 'id-ID',
            'voice' => 'Kore',
            'style' => 'tenang',
        ])->assertUnprocessable()
            ->assertJsonPath('limits.characters', 8000)
            ->assertJsonPath('limits.words', 1200);

        Queue::assertNothingPushed();
    }

    public function test_2740_character_narration_uses_multiple_streaming_requests(): void
    {
        $response = $this->bufferedAudioResponse();
        Http::fake(fn () => Http::response($response, 200));

        $text = mb_substr(str_repeat('Pembelajaran bermakna dimulai dari pertanyaan yang dekat dengan kehidupan siswa. ', 40), 0, 2740);
        app(GeminiTtsService::class)->synthesize($text, [
            'api_key' => 'AIzaSyTestPersonalKeyForTtsFeature01',
        ]);

        $this->assertGreaterThan(1, count(Http::recorded()));
        $this->assertLessThanOrEqual(3, count(Http::recorded()));
        Http::assertSent(fn ($request) => str_contains($request->url(), ':streamGenerateContent?alt=sse'));
    }

    public function test_transient_server_error_retries_only_failed_chunk(): void
    {
        $audio = $this->bufferedAudioResponse();
        Http::fakeSequence()
            ->push(['error' => ['message' => 'Temporary text token anomaly']], 500)
            ->push($audio, 200);

        $result = app(GeminiTtsService::class)->synthesize('Retry bagian ini.', [
            'api_key' => 'AIzaSyTestPersonalKeyForTtsFeature01',
            'retry_delay_ms' => 0,
        ]);

        $this->assertSame('audio/mpeg', $result['mime']);
        Http::assertSentCount(2);
    }

    public function test_daily_personal_quota_can_fall_back_to_school_key(): void
    {
        $audio = $this->bufferedAudioResponse();
        Http::fakeSequence()
            ->push(['error' => ['message' => 'requestsPerDay quota exhausted']], 429, ['Retry-After' => '0'])
            ->push($audio, 200);

        app(GeminiTtsService::class)->synthesize('Gunakan fallback hanya untuk kuota harian.', [
            'api_key' => 'personal-key',
            'fallback_api_key' => 'school-key',
            'retry_delay_ms' => 0,
        ]);

        $requests = Http::recorded();
        $this->assertSame('personal-key', $requests[0][0]->header('x-goog-api-key')[0]);
        $this->assertSame('school-key', $requests[1][0]->header('x-goog-api-key')[0]);
    }

    public function test_daily_quota_without_fallback_fails_without_wasteful_retries(): void
    {
        Http::fakeSequence()->push([
            'error' => [
                'message' => 'You exceeded your current quota.',
                'details' => [[
                    '@type' => 'type.googleapis.com/google.rpc.QuotaFailure',
                    'violations' => [[
                        'quotaId' => 'GenerateRequestsPerDayPerProjectPerModel-FreeTier',
                    ]],
                ]],
            ],
        ], 429);

        $this->expectException(AiProviderUnavailableException::class);
        $this->expectExceptionMessage('Kuota harian Gemini TTS Free Tier');

        try {
            app(GeminiTtsService::class)->synthesize('Kuota harian habis.', [
                'api_key' => 'personal-key',
                'retry_delay_ms' => 0,
            ]);
        } finally {
            Http::assertSentCount(1);
        }
    }

    public function test_partial_sse_audio_is_discarded_before_chunk_retry(): void
    {
        Http::fakeSequence()
            ->push($this->sseAudioResponse(2400, 'OTHER'), 200, ['Content-Type' => 'text/event-stream'])
            ->push($this->sseAudioResponse(4800, 'STOP'), 200, ['Content-Type' => 'text/event-stream']);

        $result = app(GeminiTtsService::class)->synthesize('Ulangi stream parsial ini.', [
            'api_key' => 'personal-key',
            'retry_delay_ms' => 0,
        ]);

        $this->assertSame(100, $result['duration_ms']);
        Http::assertSentCount(2);
    }

    public function test_rate_limit_retry_after_retries_current_chunk(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['message' => 'Requests per minute exceeded']], 429, ['Retry-After' => '0'])
            ->push($this->bufferedAudioResponse(), 200);

        $result = app(GeminiTtsService::class)->synthesize('Coba ulang chunk rate limit.', [
            'api_key' => 'personal-key',
            'retry_delay_ms' => 0,
        ]);

        $this->assertSame('audio/mpeg', $result['mime']);
        Http::assertSentCount(2);
    }

    public function test_student_cannot_stream_unlinked_teacher_audio(): void
    {
        $guru = $this->guru();
        $student = User::create(['username' => 'student-tts-'.uniqid(), 'password' => Hash::make('password'), 'access' => 'siswa']);
        $path = 'ai-teacher-audio/'.$guru->uuid.'/audio.mp3';
        $audio = AiTeacherAudioAsset::create(['user_uuid' => $guru->uuid, 'source_type' => 'free_text', 'title' => 'Audio', 'text_snapshot' => 'x', 'text_hash' => hash('sha256', 'x'), 'language' => 'id-ID', 'voice' => 'Kore', 'style_prompt' => 'x', 'model' => config('ai.tts.model'), 'status' => 'ready', 'disk' => 'local', 'path' => $path, 'mime' => 'audio/mpeg']);

        $this->actingAs($student)->get(route('ai.teacher.audio.stream', $audio))->assertForbidden();
    }

    public function test_teacher_can_play_and_download_ready_mp3(): void
    {
        Storage::fake('local');
        $guru = $this->guru();
        $path = 'ai-teacher-audio/'.$guru->uuid.'/audio-siap.mp3';
        Storage::disk('local')->put($path, 'ID3'.str_repeat("\0", 128));
        $audio = AiTeacherAudioAsset::create([
            'user_uuid' => $guru->uuid,
            'source_type' => 'free_text',
            'title' => 'Audio Siap',
            'text_snapshot' => 'Narasi siap diputar.',
            'text_hash' => hash('sha256', 'Narasi siap diputar.'),
            'language' => 'id-ID',
            'voice' => 'Kore',
            'style_prompt' => 'Natural dan jelas.',
            'model' => config('ai.tts.model'),
            'status' => 'ready',
            'disk' => 'local',
            'path' => $path,
            'mime' => 'audio/mpeg',
        ]);

        $this->actingAs($guru)
            ->get(route('ai.teacher.audio.stream', $audio))
            ->assertOk()
            ->assertHeader('Content-Type', 'audio/mpeg')
            ->assertHeader('Content-Disposition', 'inline; filename="Audio Siap.mp3"');

        $this->actingAs($guru)
            ->get(route('ai.teacher.audio.download', $audio))
            ->assertOk()
            ->assertDownload('audio-siap.mp3');
    }

    public function test_teacher_audio_history_is_private_and_newest_first(): void
    {
        $guru = $this->guru();
        $guruLain = $this->guru();
        $older = AiTeacherAudioAsset::create([
            'user_uuid' => $guru->uuid, 'source_type' => 'free_text', 'title' => 'Audio Lama',
            'text_snapshot' => 'Narasi lama.', 'text_hash' => hash('sha256', 'Narasi lama.'),
            'language' => 'id-ID', 'voice' => 'Kore', 'style_prompt' => 'Natural dan jelas.',
            'model' => config('ai.tts.model'), 'status' => 'failed', 'disk' => 'local', 'mime' => 'audio/mpeg',
        ]);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer = AiTeacherAudioAsset::create([
            'user_uuid' => $guru->uuid, 'source_type' => 'free_text', 'title' => 'Audio Mandarin',
            'text_snapshot' => 'Narasi baru.', 'text_hash' => hash('sha256', 'Narasi baru.'),
            'language' => 'zh-CN', 'voice' => 'Kore', 'style_prompt' => 'Natural dan jelas.',
            'model' => config('ai.tts.model'), 'status' => 'ready', 'disk' => 'local', 'path' => 'audio.mp3',
            'mime' => 'audio/mpeg', 'duration_ms' => 65000,
        ]);
        $other = AiTeacherAudioAsset::create([
            'user_uuid' => $guruLain->uuid, 'source_type' => 'free_text', 'title' => 'Audio Privat',
            'text_snapshot' => 'Bukan milik guru.', 'text_hash' => hash('sha256', 'Bukan milik guru.'),
            'language' => 'id-ID', 'voice' => 'Kore', 'style_prompt' => 'Natural dan jelas.',
            'model' => config('ai.tts.model'), 'status' => 'ready', 'disk' => 'local', 'path' => 'private.mp3', 'mime' => 'audio/mpeg',
        ]);

        $response = $this->actingAs($guru)
            ->getJson(route('ai.teacher.audio.history'))
            ->assertOk()
            ->assertJsonCount(2, 'audios')
            ->assertJsonPath('audios.0.uuid', $newer->uuid)
            ->assertJsonPath('audios.0.language_label', config('ai.tts.languages.zh-CN'))
            ->assertJsonPath('audios.0.duration_label', '1:05')
            ->assertJsonPath('audios.1.uuid', $older->uuid);

        $this->assertNotNull($response->json('audios.0.download_url'));
        $this->assertNotNull($response->json('audios.0.delete_url'));
        $this->assertNotContains($other->uuid, collect($response->json('audios'))->pluck('uuid')->all());

        $this->actingAs($guru)->deleteJson(route('ai.teacher.audio.destroy', $other))->assertForbidden();
        $this->assertDatabaseHas('ai_teacher_audio_assets', ['uuid' => $other->uuid]);
    }

    public function test_teacher_can_delete_audio_from_history_and_its_file(): void
    {
        Storage::fake('local');
        $guru = $this->guru();
        $path = 'ai-teacher-audio/'.$guru->uuid.'/hapus.mp3';
        Storage::disk('local')->put($path, 'ID3audio');
        $audio = AiTeacherAudioAsset::create([
            'user_uuid' => $guru->uuid, 'source_type' => 'free_text', 'title' => 'Hapus Audio',
            'text_snapshot' => 'Narasi dihapus.', 'text_hash' => hash('sha256', 'Narasi dihapus.'),
            'language' => 'id-ID', 'voice' => 'Kore', 'style_prompt' => 'Natural dan jelas.',
            'model' => config('ai.tts.model'), 'status' => 'ready', 'disk' => 'local', 'path' => $path, 'mime' => 'audio/mpeg',
        ]);

        $this->actingAs($guru)
            ->deleteJson(route('ai.teacher.audio.destroy', $audio))
            ->assertOk()
            ->assertJsonPath('ok', true);

        Storage::disk('local')->assertMissing($path);
        $this->assertDatabaseMissing('ai_teacher_audio_assets', ['uuid' => $audio->uuid]);
    }

    public function test_audio_targets_remove_duplicate_quiz_and_repeated_subject_label(): void
    {
        $guru = $this->guru();
        $classroom = Classroom::create([
            'created_by' => $guru->uuid,
            'title' => 'Pendidikan Agama dan Budi Pekerti — Kelas 8B',
            'status' => 'published',
            'class_code' => 'TTS8B01',
        ]);
        $older = GameQuiz::create([
            'classroom_id' => $classroom->uuid,
            'created_by' => $guru->uuid,
            'title' => 'Arena Belajar — Pendidikan Agama dan Budi Pekerti',
        ]);
        $older->forceFill(['created_at' => now()->subMinute()])->save();
        $newer = GameQuiz::create([
            'classroom_id' => $classroom->uuid,
            'created_by' => $guru->uuid,
            'title' => 'Arena Belajar — Pendidikan Agama dan Budi Pekerti',
        ]);

        $targets = $this->actingAs($guru)
            ->getJson(route('ai.teacher.audio.targets'))
            ->assertOk()
            ->json('targets');

        $this->assertCount(1, $targets);
        $this->assertSame($newer->uuid, $targets[0]['target_uuid']);
        $this->assertSame('Arena Belajar — Pendidikan Agama dan Budi Pekerti — Kelas 8B', $targets[0]['label']);
    }

    public function test_teacher_can_generate_ready_mp3_with_indonesian_controls(): void
    {
        Http::fake(fn () => Http::response([
            'candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'audio/L16;rate=24000', 'data' => base64_encode(str_repeat("\0", 4800))]]]]]],
        ], 200));
        config(['ai.tts.dispatch' => 'sync']);
        $guru = $this->guru();

        $this->actingAs($guru)->postJson(route('ai.teacher.audio.create'), [
            'source_type' => 'free_text', 'text' => 'Selamat datang di pelajaran hari ini.',
            'title' => 'Narasi Indonesia', 'language' => 'id-ID', 'voice_gender' => 'pria',
            'voice' => 'Puck', 'vibe' => 'misterius', 'tempo_percent' => 85,
        ])->assertOk()->assertJsonPath('audio.status', 'ready')->assertJsonPath('audio.vibe', 'misterius');

        $this->assertDatabaseHas('ai_teacher_audio_assets', [
            'user_uuid' => $guru->uuid,
            'voice_gender' => 'pria',
            'vibe' => 'misterius',
            'tempo_percent' => 85,
            'status' => 'ready',
            'mime' => 'audio/mpeg',
        ]);
    }

    public function test_long_narration_is_split_into_multiple_gemini_requests(): void
    {
        $response = ['candidates' => [['content' => ['parts' => [['inlineData' => ['mimeType' => 'audio/L16;rate=24000', 'data' => base64_encode(str_repeat("\0", 2400))]]]]]]];
        Http::fakeSequence()->push($response, 200)->push($response, 200)->push($response, 200);
        config(['ai.tts.chunk_chars' => 500]);

        $result = app(GeminiTtsService::class)->synthesize(str_repeat('Kalimat pembelajaran. ', 60), ['api_key' => 'AIzaSyTestPersonalKeyForTtsFeature01']);

        $this->assertSame('audio/mpeg', $result['mime']);
        $this->assertSame('mp3', $result['extension']);
        Http::assertSentCount(3);
    }

    public function test_asisten_guru_audio_page_keeps_alpine_component_initializable(): void
    {
        $response = $this->actingAs($this->guru())->get(route('ai.teacher.index'));
        $response->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/async pollAudio\(\).*?\},\s*init\(\) \{/s', $html);
        $this->assertStringNotContainsString('</div>            </div>', $html);
        $this->assertStringContainsString('Generator Narasi Audio Multibahasa', $html);
        $this->assertStringContainsString('Buat Audio MP3', $html);
        $this->assertStringContainsString('Riwayat Audio', $html);
        $this->assertStringContainsString(route('ai.teacher.audio.history'), $html);
    }

    private function bufferedAudioResponse(int $bytes = 2400): array
    {
        return [
            'candidates' => [[
                'content' => [
                    'parts' => [[
                        'inlineData' => [
                            'mimeType' => 'audio/L16;rate=24000',
                            'data' => base64_encode(str_repeat("\0", $bytes)),
                        ],
                    ]],
                ],
            ]],
        ];
    }

    private function translatedTextResponse(string $text): array
    {
        return [
            'candidates' => [[
                'content' => ['parts' => [['text' => $text]]],
                'finishReason' => 'STOP',
            ]],
            'usageMetadata' => [
                'promptTokenCount' => 20,
                'candidatesTokenCount' => 10,
            ],
        ];
    }

    private function sseAudioResponse(int $bytes, string $finishReason): string
    {
        $payload = $this->bufferedAudioResponse($bytes);
        $payload['candidates'][0]['finishReason'] = $finishReason;

        return 'data: '.json_encode($payload, JSON_THROW_ON_ERROR)."\n\n";
    }
}
