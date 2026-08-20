<?php

namespace Tests\Feature;

use App\Models\AiDocument;
use App\Models\AiDocumentChunk;
use App\Models\User;
use App\Services\RagService;
use App\Services\TeacherMaterialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AiTeacherMaterialCancelTest extends TestCase
{
    use RefreshDatabase;

    private function guru(): User
    {
        return User::create([
            'username' => 'guru_cancel_'.uniqid(),
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
    }

    public function test_guru_can_cancel_owned_material(): void
    {
        $guru = $this->guru();
        Storage::fake('local');
        Storage::disk('local')->put('materials/math.pdf', 'materi test');

        $doc = AiDocument::create([
            'user_uuid' => $guru->uuid,
            'title' => 'Buku Matematika Bab 1',
            'file_path' => 'materials/math.pdf',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PENDING,
        ]);

        AiDocumentChunk::create([
            'document_id' => $doc->uuid,
            'ord' => 0,
            'content' => 'Chunk 1',
            'embedding' => [0.1, 0.2, 0.3],
        ]);

        $this->actingAs($guru)
            ->deleteJson(route('ai.teacher.materials.cancel', $doc->uuid))
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('ai_documents', ['uuid' => $doc->uuid]);
        $this->assertDatabaseMissing('ai_document_chunks', ['document_id' => $doc->uuid]);
        Storage::disk('local')->assertMissing('materials/math.pdf');
    }

    public function test_guru_cannot_cancel_material_owned_by_other(): void
    {
        $guru1 = $this->guru();
        $guru2 = $this->guru();

        $doc = AiDocument::create([
            'user_uuid' => $guru1->uuid,
            'title' => 'Buku Fisika Bab 2',
            'file_path' => 'materials/physics.pdf',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PENDING,
        ]);

        $this->actingAs($guru2)
            ->deleteJson(route('ai.teacher.materials.cancel', $doc->uuid))
            ->assertStatus(404);

        $this->assertDatabaseHas('ai_documents', ['uuid' => $doc->uuid]);
    }

    public function test_rag_ingest_halts_when_document_is_cancelled(): void
    {
        $guru = $this->guru();

        $doc = AiDocument::create([
            'user_uuid' => $guru->uuid,
            'title' => 'Buku Biologi',
            'file_path' => 'materials/bio.pdf',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_CANCELLED,
        ]);

        $rag = app(RagService::class);
        $done = $rag->ingest($doc, __FILE__, 'text/plain');

        $this->assertSame(0, $done);
    }

    public function test_generator_soal_mendaftarkan_handler_hapus_materi(): void
    {
        $guru = $this->guru();

        $html = $this->actingAs($guru)
            ->get(route('ai.teacher.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('async cancelMaterial(uuid)', $html);
        $this->assertStringContainsString("method: 'DELETE'", $html);
        $this->assertStringContainsString('this.urls.materialsCancel', $html);
    }
}
