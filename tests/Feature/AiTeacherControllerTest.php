<?php

namespace Tests\Feature;

use App\Models\AiTeacherHistory;
use App\Models\AiUsageLog;
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

    public function test_halaman_asisten_guru_hanya_menampilkan_keterangan_kuota_untuk_guru(): void
    {
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
            ->assertSee('Keterangan Kuota Tersisa')
            ->assertDontSee('Angka resmi dihitung Google')
            ->assertDontSee('quota.status_label', false)
            ->assertDontSee('gemini-3.5-flash', false)
            ->assertViewHas('canViewQuotaUsage', false)
            ->assertViewHas('quotaUsage', function (array $quota) {
                return $quota['can_view_usage'] === false
                    && $quota['total'] === null
                    && $quota['models'] === []
                    && $quota['remaining'] === 268
                    && $quota['remaining_percent'] === 99
                    && $quota['remaining_label'] === '268 request tersisa';
            });
    }

    public function test_admin_melihat_detail_penggunaan_free_tier_harian(): void
    {
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
            ->assertSee('Keterangan Kuota Tersisa')
            ->assertSee('gemini-3.5-flash', false)
            ->assertViewHas('canViewQuotaUsage', true)
            ->assertViewHas('quotaUsage', function (array $quota) {
                return $quota['can_view_usage'] === true
                    && $quota['total']['used'] === 2
                    && $quota['total']['limit'] === 270
                    && $quota['models'][0]['model'] === 'gemini-3.5-flash'
                    && $quota['models'][0]['used'] === 1
                    && $quota['models'][0]['remaining'] === 19;
            });
    }

    public function test_respons_generate_guru_hanya_membawa_keterangan_kuota(): void
    {
        config()->set('ai.model', 'gemini-test');
        config()->set('ai.fallback_models', []);
        config()->set('ai.free_tier_daily_limits', ['gemini-test' => 5]);

        $user = User::create([
            'username' => 'guru-quota-json',
            'password' => 'password',
            'access' => 'guru',
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
            ->assertJsonPath('quota.total', null)
            ->assertJsonPath('quota.models', [])
            ->assertJsonPath('quota.status', 'ok')
            ->assertJsonPath('quota.remaining', 4)
            ->assertJsonPath('quota.remaining_percent', 80)
            ->assertJsonPath('quota.remaining_label', '4 request tersisa');
    }

    public function test_respons_generate_admin_membawa_penggunaan_free_tier_terbaru(): void
    {
        config()->set('ai.model', 'gemini-test');
        config()->set('ai.fallback_models', []);
        config()->set('ai.free_tier_daily_limits', ['gemini-test' => 5]);

        $user = User::create([
            'username' => 'admin-quota-json',
            'password' => 'password',
            'access' => 'superadmin',
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
            ->assertJsonPath('quota.can_view_usage', true)
            ->assertJsonPath('quota.total.used', 1)
            ->assertJsonPath('quota.total.limit', 5)
            ->assertJsonPath('quota.models.0.remaining', 4)
            ->assertJsonPath('quota.models.0.prompt_tokens', 12)
            ->assertJsonPath('quota.models.0.completion_tokens', 8);
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

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tempPath));
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        $this->assertIsString($xml);
        $this->assertStringContainsString('Soal IPA Air', $xml);
        $this->assertStringContainsString('Apa itu evaporasi?', $xml);
        $this->assertStringContainsString('Kunci Jawaban: A', $xml);
    }

    public function test_perangkum_materi_tetap_memakai_prompt_summary(): void
    {
        $user = User::create([
            'username' => 'guru-summary',
            'password' => 'password',
            'access' => 'guru',
        ]);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt, array $options) => str_contains($prompt, 'Rangkum materi berikut')
                    && str_contains($prompt, 'Materi ekosistem')
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

        $response->assertOk()->assertJson([
            'ok' => true,
            'answer' => 'Ringkasan materi.',
        ]);
    }

    public function test_draft_feedback_tetap_memakai_prompt_feedback(): void
    {
        $user = User::create([
            'username' => 'guru-feedback',
            'password' => 'password',
            'access' => 'guru',
        ]);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt, array $options) => str_contains($prompt, 'Susun draf umpan balik')
                    && str_contains($prompt, 'untuk siswa bernama Budi')
                    && str_contains($prompt, 'Perlu lebih aktif berdiskusi')
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

        $response->assertOk()->assertJson([
            'ok' => true,
            'answer' => 'Draf feedback.',
        ]);
    }

    public function test_generator_rpm_learning_memakai_8_komponen_wajib(): void
    {
        $user = User::create([
            'username' => 'guru-learning',
            'password' => 'password',
            'access' => 'guru',
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
                        && str_contains($prompt, '4 Pilar PM')
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

        $this->assertStringContainsString('Mata pelajaran: IPAS', $capturedPrompt);
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
        $this->assertStringNotContainsString('Dibuat dari Asisten Guru', $xml);
    }

    public function test_pratinjau_learning_merender_dokumen_rpm_sebagai_tabel(): void
    {
        $user = User::create([
            'username' => 'guru-learning-preview',
            'password' => 'password',
            'access' => 'guru',
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

    /** Isi dokumen dirender lewat Blade escaping â€” HTML dari guru tak boleh dieksekusi. */
    public function test_pratinjau_learning_meng_escape_html_berbahaya(): void
    {
        $user = User::create([
            'username' => 'guru-preview-xss',
            'password' => 'password',
            'access' => 'guru',
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
