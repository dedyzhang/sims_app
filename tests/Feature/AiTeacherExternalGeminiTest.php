<?php

namespace Tests\Feature;

use App\Models\AiTeacherHistory;
use App\Models\User;
use App\Services\GeminiService;
use App\Support\SchoolLetterhead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class AiTeacherExternalGeminiTest extends TestCase
{
    use RefreshDatabase;

    private function guru(array $extra = []): User
    {
        return User::create(array_merge([
            'username' => 'guru_ext_'.uniqid(),
            'password' => Hash::make('password'),
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ], $extra));
    }

    public function test_external_prompt_quiz_returns_formatted_prompt_without_calling_gemini(): void
    {
        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldNotReceive('generate');
        });

        $guru = $this->guru();

        $response = $this->actingAs($guru)
            ->postJson(route('ai.teacher.external-prompt'), [
                'tool' => 'quiz',
                'topik' => 'Fotosintesis',
                'jumlah' => 5,
                'jenis_soal' => ['pg'],
                'tingkat' => 'sulit',
                'jenjang' => 'Kelas 7',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('gemini_url', 'https://gemini.google.com/app')
            ->assertJsonPath('tool', 'quiz');

        $prompt = (string) $response->json('prompt');
        $this->assertStringContainsString('SOAL EVALUASI', $prompt);
        $this->assertStringContainsString('Kunci Jawaban', $prompt);
        $this->assertStringContainsString('Fotosintesis', $prompt);
        $this->assertStringContainsString('PERMINTAAN:', $prompt);
        $this->assertStringContainsString('PERAN / INSTRUKSI SISTEM:', $prompt);
    }

    public function test_external_prompt_learning_uses_learning_tool_field(): void
    {
        $guru = $this->guru();

        $response = $this->actingAs($guru)
            ->postJson(route('ai.teacher.external-prompt'), [
                'tool' => 'learning',
                'learning_tool' => 'rpp',
                'topik' => 'Ekosistem',
                'mapel' => 'IPA',
                'jenjang' => 'Kelas 7',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('tool', 'learning');

        $prompt = (string) $response->json('prompt');
        $this->assertStringContainsString('PERENCANAAN PEMBELAJARAN MENDALAM', $prompt);
        $this->assertStringContainsString('Ekosistem', $prompt);
    }

    public function test_external_result_stores_history(): void
    {
        $guru = $this->guru();
        $answer = "YAYASAN BUMI MAITRI
SOAL EVALUASI
1. Contoh?
A. Satu

Kunci Jawaban & Pedoman Penilaian
1. A";

        $response = $this->actingAs($guru)
            ->postJson(route('ai.teacher.external-result'), [
                'tool' => 'quiz',
                'title' => 'Fotosintesis',
                'answer' => $answer,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('history.type', 'quiz')
            ->assertJsonPath('history.type_label', 'Generator Soal');

        $fixed = (string) $response->json('answer');
        $this->assertStringStartsWith(SchoolLetterhead::schoolName(), $fixed);
        $this->assertStringContainsString('SOAL EVALUASI', $fixed);
        $this->assertStringNotContainsString('YAYASAN BUMI MAITRI', $fixed);

        $this->assertDatabaseHas('ai_teacher_histories', [
            'user_uuid' => $guru->uuid,
            'type' => 'quiz',
            'title' => 'Fotosintesis',
        ]);

        $history = AiTeacherHistory::where('user_uuid', $guru->uuid)->first();
        $this->assertSame('gemini_web', $history->metadata['via'] ?? null);
        $this->assertArrayNotHasKey('email', $history->metadata ?? []);
    }

    public function test_asisten_guru_page_keeps_external_prompt_as_backup_route(): void
    {
        $guru = $this->guru();
        $guru->setGeminiApiKey('AIzaSyTestPersonalKeyForFeatureTests01');

        $this->actingAs($guru)
            ->get(route('ai.teacher.index'))
            ->assertOk()
            ->assertSee('Cadangan: buka Gemini web', false)
            ->assertSee(route('ai.teacher.external-prompt'), false)
            ->assertSee(route('ai.teacher.external-result'), false)
            ->assertSee('aistudio.google.com/apikey', false);
    }
}
