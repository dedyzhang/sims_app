<?php

namespace Tests\Feature;

use App\Models\AiTeacherHistory;
use App\Models\Setting;
use App\Models\User;
use App\Services\GeminiService;
use App\Support\SchoolLetterhead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Mockery\MockInterface;
use Tests\TestCase;

class AiTeacherOcrTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.provider', 'gemini');
        config()->set('ai.api_key', 'gemini-test-key');
        config()->set('ai.fallback_providers', []);
    }

    private function guruWithKey(): User
    {
        return User::create([
            'username' => 'guru-ocr-'.uniqid(),
            'password' => 'password',
            'access' => 'guru',
            'gemini_api_key' => Crypt::encryptString('AIzaSyTestPersonalKeyForFeatureTests01'),
            'gemini_api_key_hint' => 'ts01',
        ]);
    }

    private function fakeJpeg(): UploadedFile
    {
        return UploadedFile::fake()->image('halaman-buku.jpg', 1200, 1600);
    }

    private function fakePng(): UploadedFile
    {
        return UploadedFile::fake()->image('halaman-buku.png', 800, 1000);
    }

    public function test_ocr_butuh_api_key_pribadi(): void
    {
        $user = User::create([
            'username' => 'guru-no-key',
            'password' => 'password',
            'access' => 'guru',
        ]);

        $this->actingAs($user)
            ->post(route('ai.teacher.ocr'), [
                'images' => [$this->fakeJpeg()],
            ])
            ->assertStatus(422)
            ->assertJsonPath('needs_api_key', true);
    }

    public function test_ocr_membaca_teks_dari_foto(): void
    {
        $user = $this->guruWithKey();
        Setting::set('nama_sekolah', 'SMA Negeri Trademark Test');

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('visionText')
                ->once()
                ->andReturn([
                    'text' => "Bab 5 Ekosistem\nProdusen menghasilkan makanan sendiri.",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 20,
                    'completion_tokens' => 40,
                ]);
        });

        $response = $this->actingAs($user)
            ->post(route('ai.teacher.ocr'), [
                'images' => [$this->fakeJpeg()],
                'scope' => 'quiz',
                'title' => 'Scan buku · Ekosistem',
            ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('history.type', 'ocr_scan')
            ->assertJsonPath('history.type_label', 'Scan Buku')
            ->assertJsonPath('history.title', 'Scan buku · Ekosistem');

        $text = (string) $response->json('text');
        $this->assertStringContainsString(SchoolLetterhead::schoolName(), $text);
        $this->assertStringContainsString('SUMBER DIGITAL', $text);
        $this->assertStringContainsString('SCAN BUKU', $text);
        $this->assertStringContainsString('Bab 5 Ekosistem', $text);
        $this->assertStringContainsString('Produsen menghasilkan makanan sendiri.', $text);
        $this->assertStringContainsString('Asisten Guru SIMS', $text);

        $this->assertDatabaseHas('ai_teacher_histories', [
            'user_uuid' => $user->uuid,
            'type' => 'ocr_scan',
            'type_label' => 'Scan Buku',
        ]);
        $this->assertSame(1, AiTeacherHistory::where('user_uuid', $user->uuid)->where('type', 'ocr_scan')->count());
        $stored = AiTeacherHistory::where('user_uuid', $user->uuid)->where('type', 'ocr_scan')->first();
        $this->assertStringContainsString('SUMBER DIGITAL', (string) $stored->answer);
    }

    public function test_ocr_tolak_bila_tidak_terbaca(): void
    {
        $user = $this->guruWithKey();

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('visionText')
                ->once()
                ->andReturn([
                    'text' => 'TIDAK_TERBACA',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 10,
                    'completion_tokens' => 2,
                ]);
        });

        $this->actingAs($user)
            ->post(route('ai.teacher.ocr'), [
                'images' => [$this->fakeJpeg()],
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 'image_unreadable');
    }

    public function test_ocr_tolak_lebih_dari_batas_foto(): void
    {
        $user = $this->guruWithKey();
        config()->set('ai.ocr.max_images', 2);

        $this->actingAs($user)
            ->postJson(route('ai.teacher.ocr'), [
                'images' => [
                    $this->fakeJpeg(),
                    $this->fakeJpeg(),
                    $this->fakeJpeg(),
                ],
            ])
            ->assertStatus(422);
    }

    public function test_generator_soal_bisa_pakai_material_text_scan_buku(): void
    {
        $user = $this->guruWithKey();
        $captured = '';

        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt) use (&$captured) {
                    $captured = $prompt;

                    return str_contains($prompt, 'MATERI SCAN BUKU')
                        && str_contains($prompt, 'Produsen menghasilkan makanan');
                })
                ->andReturn([
                    'text' => "1. Siapa produsen?\nA. Tumbuhan\n\nKUNCI JAWABAN: A",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                ]);
        });

        $this->actingAs($user)
            ->postJson(route('ai.teacher.quiz'), [
                'topik' => 'Ekosistem',
                'jumlah' => 1,
                'jenis_soal' => ['pg'],
                'tingkat' => 'mudah',
                'material_text' => 'Produsen menghasilkan makanan sendiri lewat fotosintesis.',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString('MATERI SCAN BUKU', $captured);
    }

    public function test_generator_soal_langsung_dari_foto_via_vision(): void
    {
        $user = $this->guruWithKey();
        $capturedPrompt = '';

        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('visionText')
                ->once()
                ->withArgs(function (array $images, array $options = []) use (&$capturedPrompt) {
                    $capturedPrompt = (string) ($options['prompt'] ?? '');

                    return count($images) >= 1
                        && isset($images[0]['binary'], $images[0]['mime'])
                        && str_contains($capturedPrompt, 'foto halaman buku')
                        && str_contains($capturedPrompt, 'Buat 2 soal')
                        && trim((string) ($options['api_key'] ?? '')) !== '';
                })
                ->andReturn([
                    'text' => "1. Apa itu produsen?\nA. Tumbuhan\n\nKUNCI JAWABAN: A",
                    'model' => 'gemini-vision-test',
                    'prompt_tokens' => 80,
                    'completion_tokens' => 40,
                ]);
            $mock->shouldReceive('generate')->never();
        });

        $this->actingAs($user)
            ->post(route('ai.teacher.quiz'), [
                'jumlah' => 2,
                'jenis_soal' => ['pg'],
                'tingkat' => 'sedang',
                'images' => [$this->fakeJpeg()],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('history.metadata.source', 'camera_photo');

        $this->assertStringContainsString('foto halaman buku', $capturedPrompt);
    }

    public function test_generator_soal_bisa_pakai_foto_png(): void
    {
        $user = $this->guruWithKey();
        $mimes = [];

        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$mimes) {
            $mock->shouldReceive('visionText')
                ->once()
                ->withArgs(function (array $images) use (&$mimes) {
                    $mimes = array_map(fn ($img) => $img['mime'] ?? '', $images);

                    return count($images) === 1
                        && ($images[0]['binary'] ?? '') !== ''
                        && in_array($images[0]['mime'] ?? '', ['image/png', 'image/jpeg'], true);
                })
                ->andReturn([
                    'text' => "1. Soal dari PNG?\nA. Ya\n\nKUNCI JAWABAN: A",
                    'model' => 'gemini-vision-test',
                    'prompt_tokens' => 40,
                    'completion_tokens' => 20,
                ]);
        });

        $this->actingAs($user)
            ->post(route('ai.teacher.quiz'), [
                'jumlah' => 1,
                'jenis_soal' => ['pg'],
                'tingkat' => 'mudah',
                'images' => [$this->fakePng()],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('history.metadata.source', 'camera_photo');

        $this->assertNotEmpty($mimes);
    }

    public function test_generator_soal_strip_stempel_ocr_dari_prompt(): void
    {
        $user = $this->guruWithKey();
        Setting::set('nama_sekolah', 'SMA Trademark Strip');
        $captured = '';

        $stamped = SchoolLetterhead::ensureOcrAttribution(
            "Bab 5 Ekosistem\nProdusen menghasilkan makanan sendiri.",
            ['pages' => 1, 'recorded_at' => '01/01/2026 10:00 WIB'],
        );

        $this->assertStringContainsString('SUMBER DIGITAL', $stamped);

        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt) use (&$captured) {
                    $captured = $prompt;

                    return str_contains($prompt, 'MATERI SCAN BUKU')
                        && str_contains($prompt, 'Produsen menghasilkan makanan sendiri')
                        && ! str_contains($prompt, 'SUMBER DIGITAL');
                })
                ->andReturn([
                    'text' => "1. Apa peran produsen?\nA. Membuat makanan\n\nKUNCI JAWABAN: A",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                ]);
        });

        $this->actingAs($user)
            ->postJson(route('ai.teacher.quiz'), [
                'jumlah' => 1,
                'jenis_soal' => ['pg'],
                'tingkat' => 'mudah',
                'material_text' => $stamped,
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString('Produsen menghasilkan makanan sendiri', $captured);
        $this->assertStringNotContainsString('SUMBER DIGITAL', $captured);
        $this->assertStringNotContainsString('trademark sekolah', mb_strtolower($captured));
    }

    public function test_learning_bisa_pakai_material_text_scan_buku(): void
    {
        $user = $this->guruWithKey();
        $captured = '';

        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$captured) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt) use (&$captured) {
                    $captured = $prompt;

                    return str_contains($prompt, 'MATERI SCAN BUKU')
                        && str_contains($prompt, 'Siklus air');
                })
                ->andReturn([
                    'text' => "RENCANA PEMBELAJARAN MENDALAM\nTopik: Siklus air",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 12,
                    'completion_tokens' => 20,
                ]);
        });

        $this->actingAs($user)
            ->postJson(route('ai.teacher.learning'), [
                'tool' => 'rpp',
                'topik' => 'Siklus air',
                'material_text' => 'Siklus air terdiri dari evaporasi, kondensasi, dan presipitasi.',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString('MATERI SCAN BUKU', $captured);
    }
}
