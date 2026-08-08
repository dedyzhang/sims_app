<?php

namespace Tests\Feature;

use App\Jobs\IngestAiDocumentJob;
use App\Models\AiDocument;
use App\Models\AiDocumentChunk;
use App\Models\Setting;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\RagService;
use App\Support\ModulAktif;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class AiRagTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'username' => 'admin_rag',
            'password' => Hash::make('password'),
            'access' => 'admin',
        ]);
    }

    public function test_modul_off_blocks_rag(): void
    {
        Setting::set(ModulAktif::settingKey('analisis_ai'), '0');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('ai.rag.index'))
            ->assertForbidden();
    }

    public function test_upload_without_any_ai_key_returns_friendly_error(): void
    {
        config()->set('ai.api_key', '');
        config()->set('ai.provider', 'gemini');
        config()->set('ai.fallback_providers', []);

        Storage::fake('local');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('ai.rag.store'), [
                'title' => 'Tata Tertib',
                'file' => UploadedFile::fake()->createWithContent('tata.txt', 'Siswa wajib hadir tepat waktu.'),
            ])
            ->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonFragment(['message' => 'Fitur dokumen belum siap. Lengkapi pengaturan akun atau minta admin mengaktifkan konfigurasi sekolah sebelum mengunggah dokumen.']);
    }

    public function test_upload_queues_ingest_job(): void
    {
        config()->set('ai.api_key', 'test-gemini-key');
        config()->set('ai.rag.queue_ingest', true);
        Storage::fake('local');
        Queue::fake();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson(route('ai.rag.store'), [
                'title' => 'Tata Tertib',
                'file' => UploadedFile::fake()->createWithContent('tata.txt', 'Siswa wajib hadir tepat waktu setiap hari.'),
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('queued', true)
            ->assertJsonPath('document.status', 'pending');

        Queue::assertPushed(IngestAiDocumentJob::class);
        $this->assertDatabaseHas('ai_documents', [
            'title' => 'Tata Tertib',
            'status' => 'pending',
        ]);
    }

    public function test_ask_with_processed_document_returns_answer_and_sources(): void
    {
        config()->set('ai.api_key', 'test-gemini-key');
        config()->set('ai.provider', 'gemini');
        config()->set('ai.fallback_providers', []);

        $admin = $this->admin();
        $doc = AiDocument::create([
            'user_uuid' => $admin->uuid,
            'title' => 'Tata Tertib',
            'file_path' => 'ai_documents/x.txt',
            'status' => AiDocument::STATUS_PROCESSED,
            'chunk_count' => 1,
        ]);

        AiDocumentChunk::create([
            'document_id' => $doc->uuid,
            'ord' => 0,
            'content' => 'Sanksi terlambat adalah teguran tertulis.',
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $this->mock(RagService::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')
                ->once()
                ->withArgs(fn (
                    string $question,
                    ?int $k,
                    ?string $documentId,
                    ?string $ownerUuid,
                    array $embedOptions,
                ) => $question === 'Apa sanksi terlambat?'
                    && $k === null
                    && $documentId === null
                    && $ownerUuid === null
                    && $embedOptions === [])
                ->andReturn([
                    [
                        'content' => 'Sanksi terlambat adalah teguran tertulis.',
                        'title' => 'Tata Tertib',
                        'score' => 0.91,
                    ],
                ]);
        });

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt, array $options) => str_contains($prompt, 'Apa sanksi terlambat?')
                    && ! array_key_exists('api_key', $options))
                ->andReturn([
                'text' => 'Sanksi terlambat adalah teguran tertulis.',
                'model' => 'gemini-test',
                'prompt_tokens' => 10,
                'completion_tokens' => 8,
            ]);
        });

        $this->actingAs($admin)
            ->postJson(route('ai.rag.ask'), [
                'question' => 'Apa sanksi terlambat?',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('answer', 'Sanksi terlambat adalah teguran tertulis.')
            ->assertJsonPath('sources.0.title', 'Tata Tertib');
    }

    public function test_asisten_admin_sekolah_memakai_mode_prompt_operasional(): void
    {
        config()->set('ai.api_key', 'test-gemini-key');
        config()->set('ai.provider', 'gemini');
        config()->set('ai.fallback_providers', []);

        $admin = $this->admin();
        $doc = AiDocument::create([
            'user_uuid' => $admin->uuid,
            'title' => 'Tata Tertib',
            'file_path' => 'ai_documents/x.txt',
            'status' => AiDocument::STATUS_PROCESSED,
            'chunk_count' => 1,
        ]);

        AiDocumentChunk::create([
            'document_id' => $doc->uuid,
            'ord' => 0,
            'content' => 'Siswa terlambat lebih dari tiga kali dipanggil wali kelas.',
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $this->mock(RagService::class, function (MockInterface $mock) {
            $mock->shouldReceive('search')
                ->once()
                ->withArgs(fn (
                    string $question,
                    ?int $k,
                    ?string $documentId,
                    ?string $ownerUuid,
                    array $embedOptions,
                ) => str_contains($question, 'Buat draf pengumuman')
                    && $k === null
                    && $documentId === null
                    && $ownerUuid === null
                    && $embedOptions === [])
                ->andReturn([[
                    'content' => 'Siswa terlambat lebih dari tiga kali dipanggil wali kelas.',
                    'title' => 'Tata Tertib',
                    'score' => 0.93,
                ]]);
        });

        $capturedPrompt = '';
        $capturedSystem = '';
        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt, &$capturedSystem) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt, array $options) use (&$capturedPrompt, &$capturedSystem) {
                    $capturedPrompt = $prompt;
                    $capturedSystem = (string) ($options['system'] ?? '');

                    return str_contains($prompt, 'PERMINTAAN ADMIN SEKOLAH')
                        && str_contains($capturedSystem, 'Asisten Admin Sekolah')
                        && str_contains($capturedSystem, 'checklist')
                        && ! array_key_exists('api_key', $options);
                })
                ->andReturn([
                    'text' => 'Draf pengumuman: siswa yang terlambat berulang akan dipanggil wali kelas.',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                ]);
        });

        $this->actingAs($admin)
            ->postJson(route('ai.rag.ask'), [
                'question' => 'Buat draf pengumuman untuk orang tua tentang keterlambatan.',
                'mode' => 'admin',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('sources.0.title', 'Tata Tertib');

        $this->assertStringContainsString('KONTEKS DOKUMEN SEKOLAH', $capturedPrompt);
        $this->assertStringContainsString('Asisten Admin Sekolah', $capturedSystem);
    }

    public function test_ask_uses_personal_key_before_school_key(): void
    {
        config()->set('ai.api_key', 'school-gemini-key');
        config()->set('ai.provider', 'gemini');
        config()->set('ai.fallback_providers', []);

        $plain = 'AIzaSyPersonalKeyForRagFeatureTest01';
        $admin = $this->admin();
        $admin->forceFill([
            'gemini_api_key' => Crypt::encryptString($plain),
            'gemini_api_key_hint' => 'st01',
        ])->save();

        $doc = AiDocument::create([
            'user_uuid' => $admin->uuid,
            'title' => 'Tata Tertib',
            'file_path' => 'ai_documents/x.txt',
            'status' => AiDocument::STATUS_PROCESSED,
            'chunk_count' => 1,
        ]);

        AiDocumentChunk::create([
            'document_id' => $doc->uuid,
            'ord' => 0,
            'content' => 'Sanksi terlambat adalah pembinaan wali kelas.',
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $this->mock(RagService::class, function (MockInterface $mock) use ($plain) {
            $mock->shouldReceive('search')
                ->once()
                ->withArgs(fn (
                    string $question,
                    ?int $k,
                    ?string $documentId,
                    ?string $ownerUuid,
                    array $embedOptions,
                ) => $question === 'Apa sanksi terlambat?'
                    && $k === null
                    && $documentId === null
                    && $ownerUuid === null
                    && ($embedOptions['api_key'] ?? null) === $plain)
                ->andReturn([[
                    'content' => 'Sanksi terlambat adalah pembinaan wali kelas.',
                    'title' => 'Tata Tertib',
                    'score' => 0.91,
                ]]);
        });

        $this->mock(GeminiService::class, function (MockInterface $mock) use ($plain) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt, array $options) => str_contains($prompt, 'Apa sanksi terlambat?')
                    && ($options['api_key'] ?? null) === $plain)
                ->andReturn([
                    'text' => 'Sanksi terlambat adalah pembinaan wali kelas.',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 10,
                    'completion_tokens' => 8,
                ]);
        });

        $this->actingAs($admin)
            ->postJson(route('ai.rag.ask'), [
                'question' => 'Apa sanksi terlambat?',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_search_respects_candidate_limit(): void
    {
        config()->set('ai.api_key', 'test-key');
        config()->set('ai.rag.search_candidate_limit', 2);
        config()->set('ai.rag.top_k', 2);

        Http::fake([
            '*embedContent*' => Http::response([
                'embedding' => ['values' => [1.0, 0.0, 0.0]],
            ], 200),
        ]);

        $admin = $this->admin();
        $doc = AiDocument::create([
            'user_uuid' => $admin->uuid,
            'title' => 'Doc',
            'file_path' => 'x.txt',
            'status' => AiDocument::STATUS_PROCESSED,
            'chunk_count' => 3,
        ]);

        foreach ([[1, 0, 0], [0, 1, 0], [0, 0, 1]] as $i => $vec) {
            AiDocumentChunk::create([
                'document_id' => $doc->uuid,
                'ord' => $i,
                'content' => "chunk-{$i}",
                'embedding' => $vec,
            ]);
        }

        $hits = app(RagService::class)->search('query');
        $this->assertLessThanOrEqual(2, count($hits));
    }
}
