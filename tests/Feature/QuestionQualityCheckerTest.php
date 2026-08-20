<?php

namespace Tests\Feature;

use App\Exceptions\AiDailyQuotaExhaustedException;
use App\Models\Classroom;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class QuestionQualityCheckerTest extends TestCase
{
    use RefreshDatabase;

    private User $guru;

    private Classroom $classroom;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ai.api_key' => null,
            'ai.fallback_providers' => [],
            'ai.openrouter.api_key' => null,
            'ai.ninerouter.api_key' => null,
        ]);
        Setting::create(['key' => 'nama_sekolah', 'value' => 'Sekolah Test']);
        Setting::create(['key' => 'cara_absensi_guru', 'value' => 'manual']);

        $this->guru = User::create([
            'username' => 'guru_quality',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        $guruModel = Guru::create([
            'id_login' => $this->guru->uuid,
            'nama' => 'Guru Quality',
            'nik' => '2000000001',
            'jk' => 'L',
            'face_descriptor' => [0.1, 0.2],
        ]);
        $semester = Semester::create(['semester' => 1, 'tahun' => '2026/2027', 'aktif' => true]);
        $kelas = Kelas::create(['tingkat' => 7, 'kelas' => 'A']);
        $pelajaran = Pelajaran::create(['nama' => 'IPA', 'ringkasan' => 'IPA', 'kkm' => 75]);
        Ngajar::create([
            'id_guru' => $guruModel->uuid,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
        ]);
        $this->classroom = Classroom::create([
            'id_semester' => $semester->id,
            'id_kelas' => $kelas->uuid,
            'id_pelajaran' => $pelajaran->uuid,
            'created_by' => $this->guru->uuid,
            'title' => 'IPA 7A',
            'status' => 'published',
            'class_code' => 'QUALITY1',
        ]);
    }

    public function test_guru_pengelola_bisa_membuka_checker_dari_arena(): void
    {
        $this->actingAs($this->guru)
            ->get(route('classroom.arena.quality-checker', $this->classroom))
            ->assertOk()
            ->assertSee('Pemeriksa Kualitas Soal')
            ->assertSee('Mode demo rule-based')
            ->assertSee('Analisis AI')
            ->assertSee('Analisis Dasar')
            ->assertSee('Kelas 7 A')
            ->assertSee('IPA');

        $this->actingAs($this->guru)
            ->get(route('classroom.arena.index', $this->classroom))
            ->assertOk()
            ->assertSee('Cek kualitas soal')
            ->assertSee('?generate=1#generate-soal', false);
    }

    public function test_guru_yang_tidak_mengelola_kelas_ditolak(): void
    {
        $guruLain = User::create([
            'username' => 'guru_quality_lain',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);
        Guru::create([
            'id_login' => $guruLain->uuid,
            'nama' => 'Guru Lain',
            'nik' => '2000000002',
            'jk' => 'P',
            'face_descriptor' => [0.1, 0.2],
        ]);

        $this->actingAs($guruLain)
            ->get(route('classroom.arena.quality-checker', $this->classroom))
            ->assertForbidden();

        $this->actingAs($guruLain)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $this->validPayload())
            ->assertForbidden();
    }

    public function test_tanpa_api_key_checker_mengembalikan_fallback_lengkap(): void
    {
        $response = $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.source', 'fallback')
            ->assertJsonPath('data.status', 'layak')
            ->assertJsonPath('data.fallback_reason', 'API AI belum dikonfigurasi.')
            ->assertJsonStructure(['data' => [
                'score', 'status', 'bloom_level', 'difficulty', 'criteria', 'issues', 'suggestions',
                'improved_question', 'improved_options', 'recommended_answer', 'teacher_note', 'source', 'notice',
            ]]);

        $this->assertIsInt($response->json('data.score'));
        $this->assertGreaterThanOrEqual(0, $response->json('data.score'));
        $this->assertLessThanOrEqual(100, $response->json('data.score'));
    }

    public function test_input_tidak_valid_ditolak_sebelum_service(): void
    {
        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), [
                'grade_level' => 'Kelas 7',
                'subject' => 'IPA',
                'question_type' => 'tipe_rekaan',
                'question_text' => 'Pendek',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['learning_objective', 'question_type', 'question_text']);
    }

    public function test_guest_dan_guru_lain_ditolak_sebelum_validasi_checker(): void
    {
        $this->postJson(route('classroom.arena.quality-checker.check', $this->classroom), [])
            ->assertUnauthorized();

        $guruLain = User::create([
            'username' => 'guru_quality_invalid',
            'password' => Hash::make('password'),
            'access' => 'guru',
        ]);

        $this->actingAs($guruLain)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), [])
            ->assertForbidden();
    }

    public function test_respons_ai_dinormalisasi_dan_dikembalikan_ke_ui(): void
    {
        $this->guru->setGeminiApiKey('AIzaSyQuestionQualityCheckerPersonalKey01');
        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt, array $options) => str_contains($prompt, 'fotosintesis')
                    && $options['api_key'] === 'AIzaSyQuestionQualityCheckerPersonalKey01')
                ->andReturn([
                    'text' => <<<'JSON'
```json
{"score":84,"status":"layak","bloom_level":{"level":"C3","label":"Menerapkan","reason":"Meminta siswa menentukan faktor dari data."},"difficulty":{"level":"sedang","reason":"Membutuhkan penerapan konsep."},"criteria":{"clarity":{"score":88,"note":"Kalimat efektif."},"relevance":{"score":90,"note":"Selaras dengan tujuan."},"answerability":{"score":92,"note":"Kunci dapat ditentukan."},"option_quality":{"score":78,"note":"Satu distraktor perlu diperkuat."}},"issues":[{"code":"WEAK_DISTRACTOR","severity":"medium","message":"Distraktor C terlalu mudah ditebak.","suggestion":"Gunakan miskonsepsi umum sebagai distraktor."}],"improved_question":"Gunakan data percobaan untuk menentukan faktor yang memengaruhi fotosintesis.","improved_options":["Intensitas cahaya","Warna pot","Bentuk meja","Nomor kelompok"],"recommended_answer":"A","teacher_note":"Cocok untuk asesmen formatif setelah praktikum."}
```
JSON,
                    'model' => 'gemini-test',
                    'prompt_tokens' => 100,
                    'completion_tokens' => 80,
                ]);
        });

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('data.source', 'ai')
            ->assertJsonPath('data.score', 84)
            ->assertJsonPath('data.bloom_level.level', 'C3')
            ->assertJsonPath('data.bloom_level.label', 'Menerapkan')
            ->assertJsonPath('data.improved_options.0', 'Intensitas cahaya')
            ->assertJsonPath('data.recommended_answer', 'A')
            ->assertJsonPath('data.criteria.clarity.score', 88)
            ->assertJsonPath('data.issues.0.code', 'WEAK_DISTRACTOR')
            ->assertJsonPath('data.issues.0.message', 'Distraktor C terlalu mudah ditebak.')
            ->assertJsonMissingPath('data._usage');

        $this->assertDatabaseHas('ai_usage_logs', [
            'user_uuid' => $this->guru->uuid,
            'feature' => 'question_quality_checker',
            'model' => 'gemini-test',
            'status' => 'success',
        ]);
    }

    public function test_school_id_dari_request_tidak_dikirim_ke_provider_ai(): void
    {
        $this->guru->setGeminiApiKey('AIzaSyQuestionQualityCheckerTenantKey01');
        $payload = $this->validPayload() + [
            'school_id' => 'sekolah-asing-yang-tidak-boleh-dipercaya',
        ];

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt) => ! str_contains($prompt, 'school_id')
                    && ! str_contains($prompt, 'sekolah-asing-yang-tidak-boleh-dipercaya'))
                ->andReturn([
                    'text' => '{"score":80,"status":"layak","bloom_level":"C2","difficulty":"sedang","criteria":[],"issues":[],"suggestions":[],"improved_question":"Soal yang diperbaiki.","improved_options":[],"recommended_answer":"A","teacher_note":"Layak diuji coba."}',
                    'model' => 'gemini-test',
                ]);
        });

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $payload)
            ->assertOk()
            ->assertJsonPath('data.source', 'ai');
    }

    public function test_status_ai_yang_kontradiktif_diturunkan_dari_score(): void
    {
        $this->guru->setGeminiApiKey('AIzaSyQuestionQualityCheckerPersonalKey03');
        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->once()->andReturn([
                'text' => '{"score":20,"status":"layak","bloom_level":"C1","difficulty":"mudah","criteria":[],"issues":["Soal ambigu."],"suggestions":["Perjelas konteks."],"improved_question":"Soal yang lebih jelas.","improved_options":[],"recommended_answer":"A","teacher_note":"Perlu revisi besar."}',
                'model' => 'gemini-test',
            ]);
        });

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('data.score', 20)
            ->assertJsonPath('data.status', 'tidak_layak');
    }

    public function test_schema_ai_tidak_lengkap_memakai_fallback(): void
    {
        $this->guru->setGeminiApiKey('AIzaSyQuestionQualityCheckerPersonalKey04');
        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->once()->andReturn([
                'text' => '{}',
                'model' => 'gemini-test',
            ]);
        });

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('data.source', 'fallback')
            ->assertJsonPath('data.fallback_reason', 'Respons AI tidak dapat dibaca; analisis rule-based digunakan.');
    }

    public function test_checker_memakai_rate_limit_ai_terpusat(): void
    {
        config(['ai.rate_limit' => 1]);
        $this->guru->setGeminiApiKey('AIzaSyQuestionQualityCheckerPersonalKey05');
        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')->once()->andReturn([
                'text' => '{"score":80,"status":"layak","bloom_level":"C2","difficulty":"sedang","criteria":[],"issues":[],"suggestions":[],"improved_question":"Soal yang diperbaiki.","improved_options":[],"recommended_answer":"A","teacher_note":"Layak diuji coba."}',
                'model' => 'gemini-test',
            ]);
        });

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $this->validPayload())
            ->assertOk();

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $this->validPayload())
            ->assertTooManyRequests();
    }

    public function test_fallback_memvalidasi_struktur_tipe_soal_dan_membuang_opsi_tersembunyi(): void
    {
        $matching = $this->validPayload();
        $matching['question_type'] = 'match';
        $matching['options'] = [];
        $matching['answer_key'] = null;

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $matching)
            ->assertOk()
            ->assertJsonPath('data.status', 'perlu_revisi')
            ->assertJsonFragment(['message' => 'Soal menjodohkan belum memiliki cukup pasangan jawaban.']);

        $trueFalse = $this->validPayload();
        $trueFalse['question_type'] = 'true_false';
        $trueFalse['options'] = ['Opsi lama'];
        $trueFalse['answer_key'] = null;

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $trueFalse)
            ->assertOk()
            ->assertJsonFragment(['message' => 'Kunci soal Benar/Salah belum dicantumkan.'])
            ->assertJsonPath('data.improved_options', []);

        $complex = $this->validPayload();
        $complex['question_type'] = 'mcq_complex';
        $complex['answer_key'] = 'A, B';

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $complex)
            ->assertOk()
            ->assertJsonMissing(['message' => 'Kunci jawaban tidak cocok dengan huruf atau teks opsi yang tersedia.']);
    }

    public function test_batch_memeriksa_semua_soal_dan_mengembalikan_ringkasan_kolektif(): void
    {
        $second = $this->validPayload();
        $second['question_text'] = 'Mengapa tumbuhan membutuhkan cahaya matahari untuk fotosintesis?';
        $second['answer_key'] = 'Untuk menghasilkan makanan';

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.batch', $this->classroom), [
                'questions' => [$this->validPayload(), $second],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonCount(2, 'data.results')
            ->assertJsonPath('data.results.0.index', 0)
            ->assertJsonPath('data.results.1.index', 1)
            ->assertJsonStructure(['data' => [
                'summary' => ['total', 'average_score', 'layak', 'perlu_revisi', 'tidak_layak'],
                'results' => [['index', 'question_text', 'data']],
            ]]);
    }

    public function test_batch_dengan_ai_memakai_satu_panggilan_kolektif_untuk_banyak_soal(): void
    {
        $this->guru->setGeminiApiKey('AIzaSyQuestionQualityCheckerBatchKey01');
        $second = $this->validPayload();
        $second['question_text'] = 'Mengapa tumbuhan membutuhkan cahaya matahari untuk fotosintesis?';
        $second['answer_key'] = 'Untuk menghasilkan makanan';

        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt, array $options) => str_contains($prompt, '<daftar_soal>')
                    && str_contains($prompt, '"index":0')
                    && str_contains($prompt, '"index":1')
                    && $options['api_key'] === 'AIzaSyQuestionQualityCheckerBatchKey01')
                ->andReturn([
                    'text' => <<<'JSON'
{"results":[{"index":0,"score":86,"status":"layak","bloom_level":{"level":"C2","label":"Memahami","reason":"Menentukan konsep dasar."},"difficulty":{"level":"sedang","reason":"Membutuhkan pemahaman."},"criteria":{"clarity":{"score":86,"note":"Jelas."},"relevance":{"score":88,"note":"Selaras."},"answerability":{"score":90,"note":"Kunci tersedia."},"option_quality":{"score":82,"note":"Opsi cukup baik."}},"issues":[],"suggestions":["Uji kembali pada siswa."],"improved_question":"Manakah proses tumbuhan menghasilkan makanan dengan cahaya matahari?","improved_options":["Fotosintesis","Respirasi","Transpirasi","Fermentasi"],"recommended_answer":"A","teacher_note":"Layak dipakai."},{"index":1,"score":72,"status":"perlu_revisi","bloom_level":{"level":"C4","label":"Menganalisis","reason":"Meminta alasan."},"difficulty":{"level":"sulit","reason":"Butuh penalaran sebab-akibat."},"criteria":{"clarity":{"score":76,"note":"Cukup jelas."},"relevance":{"score":80,"note":"Relevan."},"answerability":{"score":70,"note":"Kunci perlu dirapikan."},"option_quality":{"score":70,"note":"Bukan pilihan ganda."}},"issues":[{"code":"ANSWER_KEY_STYLE","severity":"medium","message":"Kunci jawaban masih berupa kalimat panjang.","suggestion":"Ringkas kunci jawaban."}],"suggestions":["Ringkas kunci jawaban."],"improved_question":"Mengapa cahaya matahari diperlukan tumbuhan untuk fotosintesis?","improved_options":[],"recommended_answer":"Cahaya matahari menjadi sumber energi fotosintesis.","teacher_note":"Perlu revisi kecil."}]}
JSON,
                    'model' => 'gemini-test',
                    'prompt_tokens' => 120,
                    'completion_tokens' => 240,
                ]);
        });

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.batch', $this->classroom), [
                'questions' => [$this->validPayload(), $second],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.summary.total', 2)
            ->assertJsonPath('data.summary.layak', 1)
            ->assertJsonPath('data.summary.perlu_revisi', 1)
            ->assertJsonPath('data.summary.average_score', 79)
            ->assertJsonPath('data.results.0.data.source', 'ai')
            ->assertJsonPath('data.results.1.data.issues.0.code', 'ANSWER_KEY_STYLE')
            ->assertJsonMissingPath('data.results.0.data._usage');

        $this->assertDatabaseHas('ai_usage_logs', [
            'user_uuid' => $this->guru->uuid,
            'feature' => 'question_quality_checker',
            'model' => 'gemini-test',
            'status' => 'success',
        ]);
    }

    public function test_generator_asisten_guru_bisa_memeriksa_batch_tanpa_ruang_kelas(): void
    {
        $this->actingAs($this->guru)
            ->postJson(route('ai.teacher.quiz.quality-batch'), [
                'questions' => [$this->validPayload()],
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.summary.total', 1)
            ->assertJsonCount(1, 'data.results');
    }

    public function test_provider_gagal_tetap_mengembalikan_fallback_bukan_error(): void
    {
        $this->guru->setGeminiApiKey('AIzaSyQuestionQualityCheckerPersonalKey02');
        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(new RuntimeException('HTTP 500 provider timeout with key AIzaSyQuestionQualityCheckerPersonalKey02 and raw stack trace'));
        });

        $response = $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.source', 'fallback')
            ->assertJsonPath('data.fallback_reason', 'Layanan AI belum tersedia; analisis rule-based digunakan.');

        $responseText = $response->getContent();
        $this->assertStringNotContainsString('AIzaSyQuestionQualityCheckerPersonalKey02', $responseText);
        $this->assertStringNotContainsString('HTTP 500 provider timeout', $responseText);
        $this->assertStringNotContainsString('raw stack trace', $responseText);
    }

    public function test_kuota_harian_menampilkan_alasan_fallback_yang_dapat_ditindaklanjuti(): void
    {
        $this->guru->setGeminiApiKey('AIzaSyQuestionQualityCheckerDailyQuotaKey');
        $this->mock(GeminiService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('generate')
                ->once()
                ->andThrow(new AiDailyQuotaExhaustedException('provider quota exhausted'));
        });

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('data.source', 'fallback')
            ->assertJsonPath('data.fallback_reason', 'Kuota AI harian habis. Coba lagi setelah kuota reset atau gunakan konfigurasi AI dengan kuota lain.');
    }

    public function test_opsi_nol_tetap_dipertahankan_dan_dapat_menjadi_kunci_jawaban(): void
    {
        $payload = $this->validPayload();
        $payload['subject'] = 'Matematika';
        $payload['learning_objective'] = 'Siswa mampu menentukan hasil pengurangan bilangan bulat.';
        $payload['question_text'] = 'Tentukan hasil pengurangan bilangan bulat dari 5 dikurangi 5.';
        $payload['options'] = "0\n1\n5\n10";
        $payload['answer_key'] = '0';

        $this->actingAs($this->guru)
            ->postJson(route('classroom.arena.quality-checker.check', $this->classroom), $payload)
            ->assertOk()
            ->assertJsonPath('data.improved_options.0', '0')
            ->assertJsonMissing([
                'Kunci jawaban tidak cocok dengan huruf atau teks opsi yang tersedia.',
            ]);
    }

    private function validPayload(): array
    {
        return [
            'grade_level' => 'Kelas 7 SMP',
            'subject' => 'IPA',
            'learning_objective' => 'Siswa mampu menjelaskan proses fotosintesis pada tumbuhan.',
            'question_type' => 'mcq',
            'question_text' => 'Manakah proses yang dilakukan tumbuhan untuk menghasilkan makanan menggunakan cahaya matahari?',
            'options' => ['Fotosintesis', 'Respirasi', 'Transpirasi', 'Fermentasi'],
            'answer_key' => 'A',
        ];
    }
}
