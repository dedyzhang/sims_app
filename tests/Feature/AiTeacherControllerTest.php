<?php

namespace Tests\Feature;

use App\Models\AiTeacherHistory;
use App\Models\AiUsageLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\GeminiService;
use App\Support\SchoolLetterhead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery\MockInterface;
use Tests\TestCase;
use ZipArchive;

class AiTeacherControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tes tool default memakai jalur Gemini (mock generate). Tes OpenRouter live meng-override sendiri.
        config()->set('ai.provider', 'gemini');
        config()->set('ai.api_key', 'gemini-test-key');
        config()->set('ai.fallback_providers', []);

        // Asisten Guru wajib API key pribadi; isi otomatis untuk staf yang generate AI.
        User::created(function (User $user) {
            if ($user->hasGeminiApiKey()) {
                return;
            }
            if (in_array($user->access, ['siswa', 'orangtua'], true)) {
                return;
            }
            $user->forceFill([
                'gemini_api_key' => \Illuminate\Support\Facades\Crypt::encryptString('AIzaSyTestPersonalKeyForFeatureTests01'),
                'gemini_api_key_hint' => 'ts01',
            ])->saveQuietly();
        });
    }

    public function test_generator_soal_bisa_memakai_materi_dari_file_docx(): void
    {
        $user = User::create([
            'username' => 'guru-ai',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
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
                        && str_contains($prompt, 'SOAL EVALUASI [MATA PELAJARAN / TOPIK]')
                        && str_contains($prompt, 'Petunjuk Pengerjaan')
                        && str_contains($prompt, 'Kunci Jawaban & Pedoman Penilaian')
                        && ($options['max_output_tokens'] ?? null) === 4096
                        && ($options['thinking_level'] ?? null) === 'low'
                        && str_contains((string) ($options['answer_style'] ?? ''), 'dokumen soal teks polos');
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
            ->assertJsonPath('ok', true);
        $this->assertStringStartsWith(SchoolLetterhead::schoolName(), (string) $response->json('answer'));
        $this->assertStringContainsString("1. Contoh soal\n\nKUNCI JAWABAN: A", (string) $response->json('answer'));

        $this->assertStringContainsString('MATERI FILE:', $capturedPrompt);
        $this->assertDatabaseHas('ai_usage_logs', [
            'user_uuid' => $user->uuid,
            'feature' => 'teacher_quiz',
            'status' => 'success',
        ]);
    }

    public function test_generator_soal_bisa_memilih_beberapa_jenis_soal(): void
    {
        $user = User::create([
            'username' => 'guru-ai-checkbox',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $capturedPrompt = '';
        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt, array $options) use (&$capturedPrompt) {
                    $capturedPrompt = $prompt;

                    return str_contains($prompt, 'Pilihan Ganda Kompleks')
                        && str_contains($prompt, 'Benar/Salah')
                        && str_contains($prompt, 'Bagian A - Pilihan Ganda Kompleks')
                        && str_contains($prompt, 'Bagian B - Benar/Salah')
                        && str_contains($prompt, 'Jenis soal yang dibuat hanya: Pilihan Ganda Kompleks, Benar/Salah')
                        && ! str_contains($prompt, 'Bagian C - Mencocokkan')
                        && ($options['max_output_tokens'] ?? null) === 4096
                        && ($options['thinking_level'] ?? null) === 'low';
                })
                ->andReturn([
                    'text' => 'Soal campuran baru.',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('ai.teacher.quiz'), [
            'topik' => 'Ekosistem',
            'jumlah' => 4,
            'jenis_soal' => ['pg_kompleks', 'benar_salah'],
            'tingkat' => 'sedang',
            'jenjang' => 'Kelas 7 SMP',
        ]);

        $response->assertOk()
            ->assertJsonPath('history.metadata.jenis_soal', ['pg_kompleks', 'benar_salah']);

        $this->assertStringContainsString('Buat 4 soal (Pilihan Ganda Kompleks, Benar/Salah)', $capturedPrompt);
    }

    public function test_generator_soal_tingkat_sulit_memakai_jatah_token_lebih_besar(): void
    {
        $user = User::create([
            'username' => 'guru-ai-sulit',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt, array $options) {
                    // 10 soal sulit: (10 * 480) + 1600 = 6400, thinking ditekan.
                    return ($options['max_output_tokens'] ?? null) === 6400
                        && ($options['thinking_level'] ?? null) === 'low'
                        && str_contains($prompt, 'sulit');
                })
                ->andReturn([
                    'text' => 'Soal sulit siap.',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 20,
                    'completion_tokens' => 40,
                ]);
        });

        $this->actingAs($user)->postJson(route('ai.teacher.quiz'), [
            'topik' => 'Fotosintesis tingkat tinggi',
            'jumlah' => 10,
            'jenis_soal' => ['pg'],
            'tingkat' => 'sulit',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_generator_soal_wajib_memilih_minimal_satu_jenis_soal(): void
    {
        $user = User::create([
            'username' => 'guru-ai-no-type',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.quiz'), [
            'topik' => 'Ekosistem',
            'jumlah' => 4,
            'jenis_soal' => [],
            'tingkat' => 'sedang',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors('jenis_soal');
    }

    public function test_halaman_asisten_guru_hanya_menampilkan_generate_kuota_untuk_guru(): void
    {
        config()->set('ai.provider', 'gemini');
        config()->set('ai.model', 'gemini-3.5-flash');
        config()->set('ai.fallback_models', ['gemini-2.5-flash']);
        config()->set('ai.free_tier_daily_limits', [
            'gemini-3.5-flash' => 20,
            'gemini-2.5-flash' => 250,
        ]);

        $user = User::create([
            'username' => 'guru-quota-page',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        AiUsageLog::create([
            'user_uuid' => $user->uuid,
            'feature' => 'teacher_quiz',
            'model' => 'gemini-3.5-flash',
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'status' => 'success',
        ]);
        AiUsageLog::create([
            'user_uuid' => $user->uuid,
            'feature' => 'teacher_summary',
            'model' => 'gemini-2.5-flash',
            'prompt_tokens' => 7,
            'completion_tokens' => 3,
            'status' => 'success',
        ]);

        $response = $this->actingAs($user)->get(route('ai.teacher.index'));

        $response->assertOk()
            ->assertSee('Generate Kuota')
            ->assertDontSee('Keterangan Kuota Tersisa')
            ->assertDontSee('Angka resmi dihitung Google')
            ->assertDontSee('quota.status_label', false)
            ->assertDontSee('gemini-3.5-flash', false)
            ->assertViewHas('canViewQuotaUsage', false)
            ->assertViewHas('quotaUsage', function (array $quota) {
                return $quota['can_view_usage'] === false
                    && $quota['models'] === []
                    && $quota['remaining'] === 268
                    && $quota['remaining_percent'] === 99
                    && $quota['remaining_label'] === '268 request tersisa'
                    && $quota['total']['remaining'] === 268
                    && $quota['total']['limit'] === 270;
            });
    }

    public function test_admin_tidak_melihat_detail_model_pada_generate_kuota(): void
    {
        config()->set('ai.provider', 'gemini');
        config()->set('ai.model', 'gemini-3.5-flash');
        config()->set('ai.fallback_models', ['gemini-2.5-flash']);
        config()->set('ai.free_tier_daily_limits', [
            'gemini-3.5-flash' => 20,
            'gemini-2.5-flash' => 250,
        ]);

        $user = User::create([
            'username' => 'admin-quota-page',
            'password' => 'password',
            'access' => 'superadmin',
            'gemini_account' => 'admin@belajar.id',
        ]);

        AiUsageLog::create([
            'user_uuid' => $user->uuid,
            'feature' => 'teacher_quiz',
            'model' => 'gemini-3.5-flash',
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'status' => 'success',
        ]);
        AiUsageLog::create([
            'user_uuid' => $user->uuid,
            'feature' => 'teacher_summary',
            'model' => 'gemini-2.5-flash',
            'prompt_tokens' => 7,
            'completion_tokens' => 3,
            'status' => 'success',
        ]);

        $response = $this->actingAs($user)->get(route('ai.teacher.index'));

        $response->assertOk()
            ->assertSee('Generate Kuota')
            ->assertDontSee('Keterangan Kuota Tersisa')
            ->assertDontSee('gemini-3.5-flash', false)
            ->assertDontSee('gemini-2.5-flash', false)
            ->assertViewHas('canViewQuotaUsage', false)
            ->assertViewHas('quotaUsage', function (array $quota) {
                return $quota['can_view_usage'] === false
                    && $quota['models'] === []
                    && $quota['remaining'] === 268
                    && $quota['remaining_percent'] === 99
                    && $quota['total']['limit'] === 270
                    && $quota['total']['remaining'] === 268;
            });
    }

    public function test_respons_generate_guru_hanya_membawa_keterangan_kuota(): void
    {
        config()->set('ai.provider', 'gemini');
        config()->set('ai.model', 'gemini-test');
        config()->set('ai.fallback_models', []);
        config()->set('ai.free_tier_daily_limits', ['gemini-test' => 5]);

        $user = User::create([
            'username' => 'guru-quota-json',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => 'Ringkasan materi',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('ai.teacher.summary'), [
            'materi' => 'Materi ekosistem',
        ]);

        $response->assertOk()
            ->assertJsonPath('quota.can_view_usage', false)
            ->assertJsonPath('quota.models', [])
            ->assertJsonPath('quota.status', 'ok')
            ->assertJsonPath('quota.remaining', 4)
            ->assertJsonPath('quota.remaining_percent', 80)
            ->assertJsonPath('quota.remaining_label', '4 request tersisa')
            ->assertJsonPath('quota.total.remaining', 4)
            ->assertJsonPath('quota.total.limit', 5);
    }

    public function test_respons_generate_admin_tidak_membawa_detail_model(): void
    {
        config()->set('ai.provider', 'gemini');
        config()->set('ai.model', 'gemini-test');
        config()->set('ai.fallback_models', []);
        config()->set('ai.free_tier_daily_limits', ['gemini-test' => 5]);

        $user = User::create([
            'username' => 'admin-quota-json',
            'password' => 'password',
            'access' => 'superadmin',
            'gemini_account' => 'admin@belajar.id',
        ]);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => 'Ringkasan materi',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 12,
                    'completion_tokens' => 8,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('ai.teacher.summary'), [
            'materi' => 'Materi ekosistem',
        ]);

        $response->assertOk()
            ->assertJsonPath('quota.can_view_usage', false)
            ->assertJsonPath('quota.models', [])
            ->assertJsonPath('quota.remaining', 4)
            ->assertJsonPath('quota.remaining_percent', 80)
            ->assertJsonPath('quota.total.limit', 5)
            ->assertJsonPath('quota.total.remaining', 4)
            ->assertJsonMissingPath('quota.models.0.model')
            ->assertJsonMissingPath('quota.models.0.prompt_tokens')
            ->assertJsonMissingPath('quota.models.0.completion_tokens');
    }

    public function test_endpoint_kuota_openrouter_live_dan_key_aktif(): void
    {
        config()->set('ai.provider', 'openrouter');
        config()->set('ai.openrouter.api_key', 'sk-or-test-live');
        config()->set('ai.openrouter.base_url', 'https://openrouter.ai/api/v1');
        config()->set('ai.openrouter.model', 'openrouter/free');
        config()->set('ai.openrouter.fallback_models', []);
        config()->set('ai.openrouter.free_only', true);
        config()->set('ai.openrouter.free_daily_limit', 50);
        config()->set('ai.openrouter.quota_cache_seconds', 1);

        \Illuminate\Support\Facades\Http::fake([
            'https://openrouter.ai/api/v1/key' => \Illuminate\Support\Facades\Http::response([
                'data' => [
                    'label' => 'sk-or-v1-test',
                    'limit' => null,
                    'limit_remaining' => null,
                    'limit_reset' => null,
                    'usage' => 0,
                    'usage_daily' => 0,
                    'usage_weekly' => 0,
                    'usage_monthly' => 0,
                    'is_free_tier' => true,
                ],
            ], 200),
        ]);

        $user = User::create([
            'username' => 'guru-quota-live',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        AiUsageLog::create([
            'user_uuid' => $user->uuid,
            'feature' => 'teacher_quiz',
            'model' => 'openrouter/free',
            'prompt_tokens' => 10,
            'completion_tokens' => 5,
            'status' => 'success',
        ]);

        $this->actingAs($user)
            ->getJson(route('ai.teacher.quota', ['fresh' => 1]))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('quota.live', true)
            ->assertJsonPath('quota.provider', 'openrouter')
            ->assertJsonPath('quota.key_alive', true)
            ->assertJsonPath('quota.total.used', 1)
            ->assertJsonPath('quota.total.limit', 50)
            ->assertJsonPath('quota.remaining', 49)
            ->assertJsonPath('quota.remaining_label', '49 request tersisa');
    }

    public function test_hasil_generator_soal_bisa_dieksport_ke_word(): void
    {
        $user = User::create([
            'username' => 'guru-word',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $content = implode("\n", [
            'YAYASAN BUMI MAITRI',
            'SMP MAITREYAWIRA TANJUNGPINANG',
            'TERAKREDITASI A',
            'Jl. Prof. Ir. Sutami No. 38  Telp (0771) 4505723  Email smpmai.tpi@gmail.com',
            'SOAL EVALUASI IPA',
            'Kelas 5 SD - Tingkat Kesulitan Sedang',
            'Mata Pelajaran : IPA',
            'Kelas / Semester : Kelas 5 SD',
            'Nama : ...............................................................',
            'Nilai : ...............................................................',
            'Petunjuk Pengerjaan',
            'Kerjakan soal pilihan ganda dengan memberi tanda silang (X) pada jawaban yang benar.',
            'Bagian A - Pilihan Ganda',
            '1. Apa itu evaporasi?',
            'A. Penguapan air',
            'B. Pembekuan air',
            'C. Pengendapan air',
            'D. Peresapan air',
            'Kunci Jawaban & Pedoman Penilaian',
            '(Untuk Guru)',
            'Pilihan Ganda',
            '1. A',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.quiz.export-word'), [
            'title' => 'Soal IPA Air',
            'content' => $content,
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $tempPath = tempnam(sys_get_temp_dir(), 'exported-quiz-docx');
        file_put_contents($tempPath, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempPath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($xml);
        $this->assertStringContainsString(SchoolLetterhead::schoolName(), $xml);
        $this->assertStringNotContainsString('YAYASAN BUMI MAITRI', $xml);
        $this->assertStringContainsString('SOAL EVALUASI IPA', $xml);
        $this->assertStringContainsString('Petunjuk Pengerjaan', $xml);
        $this->assertStringContainsString('Apa itu evaporasi?', $xml);
        $this->assertStringContainsString('Kunci Jawaban &amp; Pedoman Penilaian', $xml);
        $this->assertStringNotContainsString('Dibuat dari Asisten Guru', $xml);
    }

    public function test_perangkum_materi_tetap_memakai_prompt_summary(): void
    {
        $user = User::create([
            'username' => 'guru-summary',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt, array $options) => str_contains($prompt, 'Rangkum materi berikut')
                    && str_contains($prompt, 'Materi ekosistem')
                    && str_contains($prompt, 'KOP SURAT WAJIB')
                    && str_contains((string) ($options['answer_style'] ?? ''), 'KOP SURAT WAJIB')
                    && ($options['thinking_level'] ?? null) === 'low'
                    && ! str_contains($prompt, 'PERENCANAAN PEMBELAJARAN MENDALAM'))
                ->andReturn([
                    'text' => 'Ringkasan materi.',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('ai.teacher.summary'), [
            'materi' => 'Materi ekosistem',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertStringStartsWith(SchoolLetterhead::schoolName(), (string) $response->json('answer'));
        $this->assertStringContainsString('Ringkasan materi.', (string) $response->json('answer'));
    }

    public function test_draft_feedback_tetap_memakai_prompt_feedback(): void
    {
        $user = User::create([
            'username' => 'guru-feedback',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt, array $options) => str_contains($prompt, 'Susun draf umpan balik')
                    && str_contains($prompt, 'untuk siswa bernama Budi')
                    && str_contains($prompt, 'Perlu lebih aktif berdiskusi')
                    && str_contains($prompt, 'KOP SURAT WAJIB')
                    && str_contains((string) ($options['answer_style'] ?? ''), 'KOP SURAT WAJIB')
                    && ($options['thinking_level'] ?? null) === 'low'
                    && ! str_contains($prompt, 'PERENCANAAN PEMBELAJARAN MENDALAM'))
                ->andReturn([
                    'text' => 'Draf feedback.',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 12,
                    'completion_tokens' => 6,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('ai.teacher.feedback'), [
            'nama' => 'Budi',
            'konteks' => 'Perlu lebih aktif berdiskusi',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertStringStartsWith(SchoolLetterhead::schoolName(), (string) $response->json('answer'));
        $this->assertStringContainsString('Draf feedback.', (string) $response->json('answer'));
    }

    public function test_generator_rpm_learning_memakai_8_komponen_wajib(): void
    {
        Setting::set('nama_sekolah', 'SMP Uji Kop');
        Setting::set('alamat_sekolah', 'Jl. Uji No. 1');
        Setting::set('telp_sekolah', '0771-123');
        Setting::set('npsn', '12345678');

        $user = User::create([
            'username' => 'guru-learning',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $capturedPrompt = '';
        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt, array $options) use (&$capturedPrompt) {
                    $capturedPrompt = $prompt;

                    return str_contains($prompt, 'Buat RPM Learning')
                        && str_contains($prompt, 'Ekosistem')
                        && str_contains($prompt, 'PERENCANAAN PEMBELAJARAN MENDALAM')
                        && str_contains($prompt, 'IDENTIFIKASI')
                        && str_contains($prompt, 'DESAIN PEMBELAJARAN')
                        && str_contains($prompt, 'PENGALAMAN BELAJAR')
                        && str_contains($prompt, 'ASESMEN PEMBELAJARAN')
                        && str_contains($prompt, 'LAMPIRAN 1')
                        && str_contains($prompt, 'Berkesadaran, Bermakna, dan Menggembirakan')
                        && str_contains($prompt, 'KOP WAJIB')
                        && str_contains($prompt, 'SMP Uji Kop')
                        && str_contains($prompt, 'Jl. Uji No. 1')
                        && ! str_contains($prompt, 'YAYASAN BUMI MAITRI')
                        && str_contains($prompt, 'Kompetensi | Baru Mulai | Berkembang | Cakap | Mahir')
                        // Dokumen RPM utuh butuh jatah token besar + porsi berpikir ditekan,
                        // kalau tidak keluaran terpotong di tengah (finishReason MAX_TOKENS).
                        && ($options['max_output_tokens'] ?? null) === 8192
                        && ($options['thinking_level'] ?? null) === 'low';
                })
                ->andReturn([
                    'text' => "# RPM Learning\n\n1. Identitas Pembelajaran\n2. DPL\n3. Tujuan Pembelajaran\n4. 4 Pilar PM\n5. Kegiatan Pembelajaran\n6. Asesmen\n7. Refleksi\n8. Lampiran dan Sumber Belajar",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 18,
                    'completion_tokens' => 24,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('ai.teacher.learning'), [
            'tool' => 'rpp',
            'topik' => 'Ekosistem',
            'mapel' => 'IPAS',
            'jenjang' => 'Kelas 5 SD',
            'durasi' => '2 x 40 menit',
        ]);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
            ]);

        $this->assertStringStartsWith('SMP Uji Kop', (string) $response->json('answer'));
        $this->assertStringContainsString('Mata pelajaran: IPAS', $capturedPrompt);
        $this->assertDatabaseHas('ai_usage_logs', [
            'user_uuid' => $user->uuid,
            'feature' => 'teacher_learning_rpp',
            'status' => 'success',
        ]);
    }

    public function test_generator_rpm_learning_bisa_memakai_materi_dari_file_docx(): void
    {
        $user = User::create([
            'username' => 'guru-learning-file',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $filePath = tempnam(sys_get_temp_dir(), 'learning-docx');
        $this->makeDocx($filePath, 'Materi energi terbarukan membahas panel surya, turbin angin, biomassa, dan penghematan listrik.');

        $capturedPrompt = '';
        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt, array $options) use (&$capturedPrompt) {
                    $capturedPrompt = $prompt;

                    return str_contains($prompt, 'Buat RPM Learning siap pakai untuk guru berdasarkan materi dari file')
                        && str_contains($prompt, 'Fokus/topik RPM: "Energi Terbarukan"')
                        && str_contains($prompt, 'MATERI FILE:')
                        && str_contains($prompt, 'panel surya, turbin angin, biomassa')
                        && str_contains($prompt, 'JANGAN keluar dari cakupan MATERI FILE')
                        && ($options['max_output_tokens'] ?? null) === 8192
                        && ($options['thinking_level'] ?? null) === 'low';
                })
                ->andReturn([
                    'text' => 'Dokumen RPM energi terbarukan.',
                    'model' => 'gemini-test',
                    'prompt_tokens' => 20,
                    'completion_tokens' => 30,
                ]);
        });

        $response = $this->actingAs($user)->postJson(route('ai.teacher.learning'), [
            'tool' => 'rpp',
            'topik' => 'Energi Terbarukan',
            'mapel' => 'IPAS',
            'jenjang' => 'Kelas 6 SD',
            'durasi' => '2 x 35 menit',
            'file' => new UploadedFile(
                $filePath,
                'materi-energi-terbarukan.docx',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                null,
                true,
            ),
        ]);

        $response->assertOk()
            ->assertJsonPath('history.type', 'rpp')
            ->assertJsonPath('history.metadata.file', 'materi-energi-terbarukan.docx');

        $this->assertStringContainsString('MATERI FILE:', $capturedPrompt);
        $this->assertDatabaseHas('ai_usage_logs', [
            'user_uuid' => $user->uuid,
            'feature' => 'teacher_learning_rpp',
            'status' => 'success',
        ]);
    }

    public function test_lkpd_dan_modul_ajar_learning_ditolak(): void
    {
        $user = User::create([
            'username' => 'guru-learning-disabled',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        foreach (['lkpd', 'modul_ajar'] as $tool) {
            $this->actingAs($user)->postJson(route('ai.teacher.learning'), [
                'tool' => $tool,
                'topik' => 'Ekosistem',
                'mapel' => 'IPAS',
            ])->assertUnprocessable();
        }
    }

    public function test_hasil_rpm_learning_bisa_dieksport_ke_word(): void
    {
        $user = User::create([
            'username' => 'guru-learning-word',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.learning.export-word'), [
            'tool' => 'rpp',
            'title' => 'RPM Ekosistem',
            'content' => "YAYASAN BUMI MAITRI\nSMP MAITREYAWIRA TANJUNGPINANG\nPERENCANAAN PEMBELAJARAN MENDALAM\n\"EKOSISTEM\"\nSEKOLAH : [NAMA SEKOLAH]\nIDENTIFIKASI\nDESAIN PEMBELAJARAN\nPENGALAMAN BELAJAR\nASESMEN PEMBELAJARAN\nLAMPIRAN 1: ASESMEN AWAL PEMBELAJARAN",
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

        $tempPath = tempnam(sys_get_temp_dir(), 'exported-learning-docx');
        file_put_contents($tempPath, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempPath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($xml);
        $this->assertStringNotContainsString('Dibuat dari Asisten Guru', $xml);
        $this->assertStringContainsString('PERENCANAAN PEMBELAJARAN MENDALAM', $xml);
        $this->assertStringContainsString('IDENTIFIKASI', $xml);
        $this->assertStringContainsString('LAMPIRAN 1', $xml);
    }

    public function test_hasil_rpm_learning_bisa_dieksport_ke_pdf(): void
    {
        $user = User::create([
            'username' => 'guru-learning-pdf',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.learning.export-pdf'), [
            'tool' => 'rpp',
            'title' => 'RPM Ekosistem',
            'content' => "YAYASAN BUMI MAITRI\nSMP MAITREYAWIRA TANJUNGPINANG\nPERENCANAAN PEMBELAJARAN MENDALAM\nSEKOLAH : [NAMA SEKOLAH]\nIDENTIFIKASI\nDESAIN PEMBELAJARAN\nPENGALAMAN BELAJAR\nASESMEN PEMBELAJARAN\nLAMPIRAN 1: ASESMEN AWAL PEMBELAJARAN",
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_export_word_konten_rpm_terstruktur_dirender_sebagai_tabel(): void
    {
        $user = User::create([
            'username' => 'guru-learning-tabel',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.learning.export-word'), [
            'tool' => 'rpp',
            'title' => 'RPM Ekosistem',
            'content' => $this->strukturRpm(),
        ]);

        $response->assertOk();

        $tempPath = tempnam(sys_get_temp_dir(), 'exported-learning-tabel');
        file_put_contents($tempPath, $response->streamedContent());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempPath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($xml);
        // Format RPM formal: tabel asli dengan merge kolom label + checkbox DPL.
        $this->assertStringContainsString('<w:tbl>', $xml);
        $this->assertStringContainsString('vMerge', $xml);
        $this->assertStringContainsString('IDENTIFIKASI', $xml);
        $this->assertStringContainsString("\u{2611}", $xml);
        $this->assertStringContainsString("\u{2610}", $xml);
        $this->assertStringContainsString('PENGALAMAN BELAJAR', $xml);
        $this->assertStringContainsString('LAMPIRAN 1', $xml);
        $this->assertStringContainsString('Kriteria', $xml);
        $this->assertStringContainsString('FCE4B6', $xml);
        $this->assertStringContainsString('F2F2F2', $xml);
        $this->assertStringNotContainsString('Dibuat dari Asisten Guru', $xml);
    }

    public function test_pratinjau_learning_merender_dokumen_rpm_sebagai_tabel(): void
    {
        $user = User::create([
            'username' => 'guru-learning-preview',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.learning.preview'), [
            'tool' => 'rpp',
            'content' => $this->strukturRpm(),
        ]);

        $response->assertOk()->assertJson(['ok' => true, 'parsed' => true]);

        $html = $response->json('html');
        $this->assertStringContainsString('rpm-doc', $html);
        $this->assertStringContainsString('<table class="tbl">', $html);
        $this->assertStringContainsString('IDENTIFIKASI', $html);
        $this->assertStringContainsString('PENGALAMAN BELAJAR', $html);
        $this->assertStringContainsString("\u{2611}", $html);
        $this->assertStringContainsString('LAMPIRAN 1', $html);
    }

    public function test_pratinjau_learning_lolos_untuk_konten_non_rpm_tanpa_error(): void
    {
        $user = User::create([
            'username' => 'guru-preview-bebas',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.learning.preview'), [
            'tool' => 'rpp',
            'content' => "Catatan bebas guru.\nBukan format RPM.",
        ]);

        $response->assertOk()->assertJson(['ok' => true, 'parsed' => false]);
        $this->assertStringContainsString('Catatan bebas guru.', $response->json('html'));
    }

    public function test_setiap_generate_asisten_guru_disimpan_ke_history(): void
    {
        $user = User::create([
            'username' => 'guru-history',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $responses = collect([
            'Soal ekosistem dan kunci jawaban.',
            'Dokumen RPM ekosistem.',
            'Ringkasan materi ekosistem.',
            'Draf feedback untuk Budi.',
        ])->map(fn (string $text, int $index) => [
            'text' => $text,
            'model' => 'gemini-test',
            'prompt_tokens' => 10 + $index,
            'completion_tokens' => 5 + $index,
        ])->all();

        $this->mock(GeminiService::class, function (MockInterface $mock) use ($responses) {
            $mock->shouldReceive('generate')
                ->times(4)
                ->andReturn(...$responses);
        });

        $this->actingAs($user)
            ->postJson(route('ai.teacher.quiz'), [
                'topik' => 'Ekosistem',
                'jumlah' => 5,
                'jenis' => 'pg',
                'tingkat' => 'sedang',
            ])
            ->assertOk()
            ->assertJsonPath('history.type_label', 'Generator Soal');

        $this->actingAs($user)
            ->postJson(route('ai.teacher.learning'), [
                'tool' => 'rpp',
                'topik' => 'Ekosistem',
                'mapel' => 'IPAS',
            ])
            ->assertOk()
            ->assertJsonPath('history.type', 'rpp');

        $this->actingAs($user)
            ->postJson(route('ai.teacher.summary'), [
                'materi' => 'Materi panjang tentang ekosistem.',
            ])
            ->assertOk()
            ->assertJsonPath('history.type_label', 'Perangkum Materi');

        $this->actingAs($user)
            ->postJson(route('ai.teacher.feedback'), [
                'nama' => 'Budi',
                'konteks' => 'Budi perlu lebih aktif berdiskusi.',
            ])
            ->assertOk()
            ->assertJsonPath('history.type_label', 'Draft Feedback');

        $this->assertSame(4, AiTeacherHistory::where('user_uuid', $user->uuid)->count());
        foreach (['quiz', 'rpp', 'summary', 'feedback'] as $type) {
            $this->assertDatabaseHas('ai_teacher_histories', [
                'user_uuid' => $user->uuid,
                'type' => $type,
            ]);
        }
    }

    /** Isi dokumen dirender lewat Blade escaping +?G?G? HTML dari guru tak boleh dieksekusi. */
    public function test_pratinjau_learning_meng_escape_html_berbahaya(): void
    {
        $user = User::create([
            'username' => 'guru-preview-xss',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.learning.preview'), [
            'tool' => 'rpp',
            'content' => "Catatan <script>alert('xss')</script> guru.",
        ]);

        $response->assertOk();
        $this->assertStringNotContainsString('<script>alert', $response->json('html'));
        $this->assertStringContainsString('&lt;script&gt;', $response->json('html'));
    }

    public function test_export_pdf_konten_rpm_terstruktur_tetap_valid(): void
    {
        $user = User::create([
            'username' => 'guru-learning-pdf-tabel',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.learning.export-pdf'), [
            'tool' => 'rpp',
            'title' => 'RPM Ekosistem',
            'content' => $this->strukturRpm(),
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_hasil_generator_soal_bisa_dieksport_ke_pdf(): void
    {
        $user = User::create([
            'username' => 'guru-quiz-pdf',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.quiz.export-pdf'), [
            'title' => 'Soal IPA Air',
            'content' => $this->strukturSoal(),
        ]);

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_export_pdf_soal_tak_berformat_tetap_valid(): void
    {
        $user = User::create([
            'username' => 'guru-quiz-pdf-bebas',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.quiz.export-pdf'), [
            'content' => "Catatan bebas guru.\nBukan format soal evaluasi.",
        ]);

        $response->assertOk();
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_guru_bisa_menghapus_history_generate_miliknya(): void
    {
        $user = User::create([
            'username' => 'guru-hapus-history',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $history = AiTeacherHistory::create([
            'user_uuid' => $user->uuid,
            'type' => 'quiz',
            'type_label' => 'Generator Soal',
            'title' => 'Soal Ekosistem',
            'excerpt' => 'Ringkasan soal',
            'metadata' => [],
            'answer' => 'Soal ekosistem.',
        ]);

        $this->actingAs($user)
            ->deleteJson(route('ai.teacher.history.destroy', $history))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseMissing('ai_teacher_histories', ['uuid' => $history->uuid]);
    }

    public function test_guru_tidak_bisa_menghapus_history_milik_guru_lain(): void
    {
        $pemilik = User::create([
            'username' => 'guru-pemilik-history',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);
        $penyusup = User::create([
            'username' => 'guru-penyusup-history',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $history = AiTeacherHistory::create([
            'user_uuid' => $pemilik->uuid,
            'type' => 'quiz',
            'type_label' => 'Generator Soal',
            'title' => 'Soal Ekosistem',
            'excerpt' => 'Ringkasan soal',
            'metadata' => [],
            'answer' => 'Soal ekosistem.',
        ]);

        $this->actingAs($penyusup)
            ->deleteJson(route('ai.teacher.history.destroy', $history))
            ->assertForbidden();

        $this->assertDatabaseHas('ai_teacher_histories', ['uuid' => $history->uuid]);
    }

    public function test_pratinjau_generator_soal_merender_dokumen_berformat(): void
    {
        $user = User::create([
            'username' => 'guru-quiz-preview',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.quiz.preview'), [
            'content' => $this->strukturSoal(),
        ]);

        $response->assertOk()->assertJson(['ok' => true, 'parsed' => true]);

        $html = $response->json('html');
        $this->assertStringContainsString('quiz-doc', $html);
        $this->assertStringContainsString(SchoolLetterhead::schoolName(), $html);
        $this->assertStringNotContainsString('YAYASAN BUMI MAITRI', $html);
        $this->assertStringContainsString('SOAL EVALUASI IPA', $html);
        $this->assertStringContainsString('<table class="identitas">', $html);
        $this->assertStringContainsString('Bagian A - Pilihan Ganda', $html);
        $this->assertStringContainsString('Apa itu evaporasi?', $html);
        $this->assertStringContainsString('<table class="kunci-pg">', $html);
    }

    public function test_pratinjau_generator_soal_lolos_untuk_konten_tak_berformat(): void
    {
        $user = User::create([
            'username' => 'guru-quiz-preview-bebas',
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => 'guru@belajar.id',
        ]);

        $response = $this->actingAs($user)->postJson(route('ai.teacher.quiz.preview'), [
            'content' => "Catatan bebas guru.\nBukan format soal evaluasi.",
        ]);

        $response->assertOk()->assertJson(['ok' => true, 'parsed' => false]);
        $this->assertStringContainsString('Catatan bebas guru.', $response->json('html'));
    }

    /** Konten soal ringkas berformat acuan (kop, identitas, petunjuk, bagian, kunci). */
    private function strukturSoal(): string
    {
        return implode("\n", [
            'YAYASAN BUMI MAITRI',
            'SMP MAITREYAWIRA TANJUNGPINANG',
            'TERAKREDITASI A',
            'Jl. Prof. Ir. Sutami No. 38  Telp (0771) 4505723  Email smpmai.tpi@gmail.com',
            'SOAL EVALUASI IPA',
            'Kelas 5 SD - Tingkat Kesulitan Sedang',
            'Mata Pelajaran : IPA',
            'Kelas / Semester : Kelas 5 SD',
            'Nama : ...............................................................',
            'Nilai : ...............................................................',
            'Petunjuk Pengerjaan',
            'Kerjakan soal pilihan ganda dengan memberi tanda silang (X) pada jawaban yang benar.',
            'Bagian A - Pilihan Ganda',
            '1. Apa itu evaporasi?',
            'A. Penguapan air',
            'B. Pembekuan air',
            'C. Pengendapan air',
            'D. Peresapan air',
            'Bagian B - Esai',
            'Jawablah setiap pertanyaan berikut dengan uraian singkat.',
            '2. Jelaskan siklus air secara singkat.',
            '_______________________________________________________________________',
            'Kunci Jawaban & Pedoman Penilaian',
            '(Untuk Guru)',
            'Pilihan Ganda',
            '1. A',
            'Esai - Poin Jawaban Ideal',
            'Soal 2',
            'Air menguap, mengembun, lalu turun sebagai hujan.',
            'Rubrik Penilaian Esai (masing-masing 4 poin)',
            'Pemahaman konsep (2 poin): memaparkan ide utama dengan benar.',
        ]);
    }

    /** Konten RPM ringkas berformat lengkap (kop, tabel, DPL, tanda tangan, lampiran). */
    private function strukturRpm(): string
    {
        return implode("\n", [
            'YAYASAN CONTOH',
            'SMP CONTOH',
            'PERENCANAAN PEMBELAJARAN MENDALAM',
            '"EKOSISTEM"',
            'SEKOLAH : SMP Contoh',
            'NAMA GURU : Guru Contoh',
            'IDENTIFIKASI',
            'Murid:',
            'Murid memahami materi sebelumnya.',
            'Materi:',
            'Ekosistem',
            'Dimensi Profil Lulusan (DPL):',
            'Dimensi profil lulusan yang akan dicapai dalam pembelajaran:',
            "\u{2611} DPL 1 Keimanan dan ketakwaan terhadap Tuhan Yang Maha Esa",
            "\u{2610} DPL 4 Kreativitas",
            'DESAIN PEMBELAJARAN',
            'Capaian Pembelajaran:',
            'Peserta didik memahami ekosistem.',
            'Tujuan Pembelajaran:',
            '1. Menjelaskan komponen ekosistem.',
            'PENGALAMAN BELAJAR',
            'AWAL (Berkesadaran, Bermakna, dan Menggembirakan)',
            "\u{2713} Guru membuka pelajaran dengan salam",
            '"Apa itu ekosistem?"',
            'PENUTUP (Berkesadaran)',
            "\u{2713} Doa penutup belajar",
            'ASESMEN PEMBELAJARAN',
            'Asesmen pada Awal Pembelajaran:',
            'Kuis singkat.',
            'Tanjungpinang, 11 Juni 2026',
            'Mengetahui, | Guru Mata Pelajaran',
            'Kepala Sekolah |',
            'Nama Kepsek | Nama Guru',
            'NIK. 123 | NIK. 456',
            'LAMPIRAN 1 : ASESMEN AWAL PEMBELAJARAN',
            "\u{2022} Materi : Ekosistem",
            '1. Apa itu ekosistem?',
            'a. lingkungan',
            'b. hewan',
            'LAMPIRAN 2 : ASESMEN PADA PROSES PEMBELAJARAN',
            'Kompetensi | Baru Mulai | Berkembang | Cakap | Mahir',
            'Menjelaskan ekosistem | 1 cara | 2 cara | 3 cara | Semua',
        ]);
    }


    public function test_asisten_guru_terbuka_untuk_kepala_dan_waka_bukan_siswa(): void
    {
        foreach (['kepala', 'kurikulum', 'kesiswaan', 'sapras', 'guru'] as $access) {
            $user = User::create([
                'username' => 'ai_ok_'.$access,
                'password' => 'password',
                'access' => $access,
                'gemini_account' => $access.'@belajar.id',
            ]);

            $this->actingAs($user)
                ->get(route('ai.teacher.index'))
                ->assertOk();
        }

        $siswa = User::create([
            'username' => 'ai_no_siswa',
            'password' => 'password',
            'access' => 'siswa',
        ]);

        $this->actingAs($siswa)
            ->get(route('ai.teacher.index'))
            ->assertForbidden();

        $ortu = User::create([
            'username' => 'ai_no_ortu',
            'password' => 'password',
            'access' => 'orangtua',
        ]);

        $this->actingAs($ortu)
            ->get(route('ai.teacher.index'))
            ->assertForbidden();
    }
    private function makeDocx(string $path, string $body): void
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>'.htmlspecialchars($body, ENT_XML1).'</w:t></w:r></w:p></w:body></w:document>');
        $zip->close();
    }
}
