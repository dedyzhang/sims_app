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

    public function test_upload_without_school_key_returns_friendly_error(): void
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
            ->assertJsonFragment(['message' => 'Dokumen AI memakai kunci sekolah (GEMINI_API_KEY di server), bukan API key pribadi Asisten Guru. Minta admin mengisi kunci di .env.']);
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
            $mock->shouldReceive('generate')->once()->andReturn([
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
