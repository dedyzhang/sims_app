<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\TestCase;
use ZipArchive;

class AiTeacherControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_generator_soal_bisa_memakai_materi_dari_file_docx(): void
    {
        $user = User::create([
            'username' => 'guru-ai',
            'password' => 'password',
            'access' => 'guru',
        ]);

        $filePath = tempnam(sys_get_temp_dir(), 'quiz-docx');
        $this->makeDocx($filePath, 'Siklus air terdiri dari evaporasi, kondensasi, presipitasi, dan infiltrasi.');

        $capturedPrompt = '';
        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt, array $options) use (&$capturedPrompt) {
                    $capturedPrompt = $prompt;

                    return str_contains($prompt, 'Siklus air terdiri dari evaporasi')
                        && str_contains($prompt, 'Fokus topik: "Daur air"')
                        && ($options['max_output_tokens'] ?? null) === 2048;
                })
                ->andReturn([
                    'text' => "1. Contoh soal\n\nKUNCI JAWABAN: A",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('ai.teacher.quiz'), [
            'topik' => 'Daur air',
            'jumlah' => 1,
            'jenis' => 'pg',
            'tingkat' => 'sedang',
            'jenjang' => 'Kelas 5 SD',
            'file' => new UploadedFile(
                $filePath,
                'materi-daur-air.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                null,
                true,
            ),
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'answer' => "1. Contoh soal\n\nKUNCI JAWABAN: A",
            ]);

        $this->assertStringContainsString('MATERI FILE:', $capturedPrompt);
        $this->assertDatabaseHas('ai_usage_logs', [
            'user_uuid' => $user->uuid,
            'feature' => 'teacher_quiz',
            'status' => 'success',
        ]);
    }

    public function test_hasil_generator_soal_bisa_dieksport_ke_word(): void
    {
        $user = User::create([
            'username' => 'guru-word',
            'password' => 'password',
            'access' => 'guru',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.quiz.export-word'), [
            'title' => 'Soal IPA Air',
            'content' => "1. Apa itu evaporasi?\nA. Penguapan air\n\nKunci Jawaban: A",
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $tempPath = tempnam(sys_get_temp_dir(), 'exported-quiz-docx');
        file_put_contents($tempPath, $response->streamedContent());

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($tempPath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($xml);
        $this->assertStringContainsString('Soal IPA Air', $xml);
        $this->assertStringContainsString('Apa itu evaporasi?', $xml);
        $this->assertStringContainsString('Kunci Jawaban: A', $xml);
    }
    private function makeDocx(string $path, string $body): void
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($body, ENT_XML1).'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
    }
}