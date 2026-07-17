<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeminiService;
use App\Support\QuizDocument;
use App\Support\QuizImageEnricher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class AiTeacherQuizImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.provider', 'gemini');
        config()->set('ai.api_key', 'gemini-test-key');
        config()->set('ai.fallback_providers', []);
        config()->set('ai.image.max_per_quiz', 3);
        config()->set('ai.image.disk', 'public');
        config()->set('ai.image.directory', 'ai-quiz-images');

        Storage::fake('public');
    }

    public function test_quiz_with_soal_bergambar_generates_images_and_tokens(): void
    {
        $user = User::create([
            'username' => 'guru-img',
            'password' => 'password',
            'access' => 'guru',
            'gemini_api_key' => Crypt::encryptString('AIzaSyTestPersonalKeyForFeatureTests01'),
            'gemini_api_key_hint' => 'ts01',
        ]);

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $this->mock(GeminiService::class, function (MockInterface $mock) use ($png) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => implode("\n", [
                        'YAYASAN BUMI MAITRI',
                        'SMP MAITREYAWIRA TANJUNGPINANG',
                        'TERAKREDITASI A',
                        'Jl. Prof. Ir. Sutami No. 38',
                        'SOAL EVALUASI IPA',
                        'Kelas 7 - Tingkat Kesulitan Sedang',
                        'Mata Pelajaran : IPA',
                        'Kelas / Semester : 7',
                        'Nama : ...',
                        'Nilai : ...',
                        'Petunjuk Pengerjaan',
                        'Kerjakan dengan teliti.',
                        'Bagian A - Pilihan Ganda',
                        '1. Perhatikan gambar sirkulasi darah berikut.',
                        '[GAMBAR: diagram sirkulasi darah manusia]',
                        'A. Atrium',
                        'B. Ventrikel',
                        'C. Aorta',
                        'D. Kapiler',
                        'Kunci Jawaban & Pedoman Penilaian',
                        '(Untuk Guru)',
                        'Pilihan Ganda',
                        '1. A',
                    ]),
                    'model' => 'gemini-test',
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                ]);

            $mock->shouldReceive('generateImage')
                ->once()
                ->andReturn([
                    'binary' => $png,
                    'mime' => 'image/png',
                    'model' => 'gemini-2.5-flash-image',
                    'prompt_tokens' => 5,
                    'completion_tokens' => 1,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('ai.teacher.quiz'), [
            'topik' => 'Sirkulasi darah',
            'jumlah' => 1,
            'jenis_soal' => ['pg'],
            'tingkat' => 'sedang',
            'soal_bergambar' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('image_meta.generated', 1);

        $answer = $response->json('answer');
        $this->assertStringContainsString('[[AI_IMG:', $answer);
        $this->assertStringNotContainsString('[GAMBAR:', $answer);

        $doc = QuizDocument::parse($answer);
        $this->assertTrue($doc['parsed']);
        $this->assertNotEmpty($doc['sections'][0]['questions'][0]['images'] ?? []);
    }

    public function test_quiz_prompt_includes_gambar_marker_when_enabled(): void
    {
        $user = User::create([
            'username' => 'guru-img-prompt',
            'password' => 'password',
            'access' => 'guru',
            'gemini_api_key' => Crypt::encryptString('AIzaSyTestPersonalKeyForFeatureTests01'),
            'gemini_api_key_hint' => 'ts01',
        ]);

        $captured = '';
        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt) use (&$captured) {
                    $captured = $prompt;

                    return true;
                })
                ->andReturn([
                    'text' => "SOAL EVALUASI\nBagian A - Pilihan Ganda\n1. Soal\nA. a\nB. b\nC. c\nD. d\nKunci Jawaban & Pedoman Penilaian\n1. A",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 1,
                    'completion_tokens' => 1,
                ]);
            $mock->shouldReceive('generateImage')->never();
        });

        $this->actingAs($user)->postJson(route('ai.teacher.quiz'), [
            'topik' => 'Fotosintesis',
            'jumlah' => 1,
            'jenis_soal' => ['pg'],
            'tingkat' => 'mudah',
            'soal_bergambar' => true,
        ])->assertOk();

        $this->assertStringContainsString('[GAMBAR:', $captured);
        $this->assertStringContainsString('soal bergambar', mb_strtolower($captured));
    }

    public function test_quiz_document_extracts_ai_img_tokens(): void
    {
        Storage::disk('public')->put('ai-quiz-images/demo.png', 'x');

        $text = implode("\n", [
            'YAYASAN BUMI MAITRI',
            'SMP TEST',
            'TERAKREDITASI A',
            'Jl. Test',
            'SOAL EVALUASI IPA',
            'Kelas 5 - Tingkat Kesulitan Mudah',
            'Mata Pelajaran : IPA',
            'Kelas / Semester : 5',
            'Nama : ...',
            'Nilai : ...',
            'Petunjuk Pengerjaan',
            'Baca soal.',
            'Bagian A - Pilihan Ganda',
            '1. Lihat gambar.',
            '[[AI_IMG:ai-quiz-images/demo.png|diagram sel]]',
            'A. Nucleus',
            'B. Mitokondria',
            'C. Kloroplas',
            'D. Vakuola',
            'Kunci Jawaban & Pedoman Penilaian',
            '(Untuk Guru)',
            'Pilihan Ganda',
            '1. A',
        ]);

        $doc = QuizDocument::parse($text);
        $this->assertTrue($doc['parsed']);
        $q = $doc['sections'][0]['questions'][0];
        $this->assertSame('Lihat gambar.', $q['text']);
        $this->assertCount(1, $q['images']);
        $this->assertSame('ai-quiz-images/demo.png', $q['images'][0]['path']);
        $this->assertSame('diagram sel', $q['images'][0]['caption']);
    }
}
