<?php

namespace Tests\Feature;

use App\Models\AiTeacherHistory;
use App\Models\Setting;
use App\Models\TeacherPresentation;
use App\Models\User;
use App\Services\GeminiService;
use App\Support\SchoolLetterhead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use Tests\TestCase;

class AiTeacherPresentasiIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ai.provider', 'gemini');
        config()->set('ai.api_key', 'gemini-test-key');
        config()->set('ai.fallback_providers', []);
    }

    private function guru(): User
    {
        $guru = User::create([
            'username' => 'guru_gemini_pres',
            'password' => Hash::make('password'),
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);
        $guru->setGeminiApiKey('AIzaSyTestPersonalKeyForFeatureTests01');

        return $guru->fresh();
    }

    public function test_asisten_guru_shows_gemini_studio_and_canva_panel(): void
    {
        $guru = $this->guru();

        $this->actingAs($guru)
            ->get(route('ai.teacher.index'))
            ->assertOk()
            ->assertSee('Tanya Nalar Guru', false)
            ->assertSee('Nalar Guru', false)
            ->assertSee('Asisten Guru', false)
            ->assertSee('Generator Soal', false)
            ->assertSee(route('ai.teacher.chat'), false)
            ->assertSee('Canva Pendidikan', false)
            ->assertSee(route('ai.teacher.presentasi.index'), false)
            ->assertDontSee(route('ai.teacher.presentasi-from-chat'), false)
            ->assertDontSee('Kirim ke Presentasi', false)
            ->assertSee("item.type === 'gemini_chat'", false)
            ->assertSee("this.tab = 'gemini'", false);
    }

    public function test_tab_presentasi_falls_back_to_gemini(): void
    {
        $guru = $this->guru();

        $html = $this->actingAs($guru)
            ->get(route('ai.teacher.index', ['tab' => 'presentasi']))
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression("/tab:\\s*['\"]gemini['\"]/", $html);
        $this->assertDoesNotMatchRegularExpression("/key:\\s*['\"]presentasi['\"]/", $html);
    }

    public function test_gemini_chat_soal_uses_generator_format(): void
    {
        $guru = $this->guru();
        $capturedPrompt = '';

        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt) use (&$capturedPrompt) {
                    $capturedPrompt = $prompt;

                    return true;
                })
                ->andReturn([
                    'text' => "SOAL EVALUASI\n1. Contoh?\nA. 1\n\nKunci Jawaban & Pedoman Penilaian\n1. A",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                ]);
            $mock->shouldIgnoreMissing();
        });

        $this->actingAs($guru)
            ->postJson(route('ai.teacher.chat'), [
                'message' => 'Buatkan 5 soal pilihan ganda fotosintesis',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString('SOAL EVALUASI', $capturedPrompt);

        $history = AiTeacherHistory::where('user_uuid', $guru->uuid)->where('type', 'gemini_chat')->first();
        $this->assertNotNull($history);
        $this->assertSame('Buatkan 5 soal pilihan ganda fotosintesis', $history->metadata['prompt'] ?? null);
    }

    public function test_gemini_chat_umum_memakai_answer_style_kop_dan_prefix(): void
    {
        Setting::set('nama_sekolah', 'SMP Nalar Uji');
        $guru = $this->guru();
        $capturedOptions = [];

        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedOptions) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt, array $options) use (&$capturedOptions) {
                    $capturedOptions = $options;

                    return true;
                })
                ->andReturn([
                    'text' => "MATERI POKOK\n- Fotosintesis",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 8,
                    'completion_tokens' => 12,
                ]);
            $mock->shouldIgnoreMissing();
        });

        $response = $this->actingAs($guru)
            ->postJson(route('ai.teacher.chat'), [
                'message' => 'Jelaskan fotosintesis singkat untuk kelas 7',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertStringContainsString('KOP SURAT WAJIB', (string) ($capturedOptions['answer_style'] ?? ''));
        $this->assertStringContainsString('JANGAN memakai Markdown', (string) ($capturedOptions['answer_style'] ?? ''));
        $this->assertStringStartsWith('SMP Nalar Uji', (string) $response->json('answer'));
        $this->assertStringContainsString('MATERI POKOK', (string) $response->json('answer'));
        $this->assertSame('SMP Nalar Uji', SchoolLetterhead::schoolName());
    }

    public function test_chat_system_prompt_omits_canva(): void
    {
        $chatPrompt = (string) config('ai.teacher.chat');

        $this->assertStringNotContainsString('Canva', $chatPrompt);
        $this->assertStringContainsString('Generator Soal SIMS', $chatPrompt);
        $this->assertStringContainsString('FORMAT WAJIB SETIAP JAWABAN', $chatPrompt);
        $this->assertStringContainsString('JANGAN memakai Markdown', $chatPrompt);
    }

    public function test_presentasi_from_chat_redirects_to_studio(): void
    {
        $guru = $this->guru();

        $response = $this->actingAs($guru)
            ->postJson(route('ai.teacher.presentasi-from-chat'), [
                'title' => 'Presentasi dari Gemini',
                'outline' => "1. Judul\n2. Isi",
            ])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $item = TeacherPresentation::where('user_uuid', $guru->uuid)->first();
        $this->assertNotNull($item);
        $response->assertJsonPath('redirect', route('ai.teacher.presentasi.show', $item));
    }
}
