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

    /** Tombol "Foto buku" utk Generator Soal sempat ada state/JS-nya tapi tak ada tombolnya di
     *  UI (grid Sumber Materi cuma 2 kolom: topik/file) — jadi jalur camera tak pernah bisa dipicu
     *  sama sekali dari halaman. Pastikan tombolnya benar2 ada & sejajar dgn pola RPM Learning. */
    public function test_halaman_generator_soal_punya_tombol_foto_buku(): void
    {
        $user = $this->guruWithKey();

        $html = $this->actingAs($user)->get(route('ai.teacher.index'))->assertOk()->getContent();

        $this->assertStringContainsString("quiz.source = 'camera'", $html);
        $this->assertStringContainsString('Foto buku', $html);
        // Grid Sumber Materi Generator Soal harus 3 kolom (topik/file/foto), sama pola dgn RPM Learning.
        $this->assertMatchesRegularExpression(
            "/grid grid-cols-3 gap-2 rounded-xl bg-slate-100.*?quiz\\.source = 'ai'/s",
            $html
        );
    }

    /** Diminta user: tak perlu langkah manual "Jadikan teks" sebelum bisa "Buat Soal" dari foto —
     *  OCR-nya jalan otomatis SEBAGAI BAGIAN dari proses "Buat Soal". Pastikan submit('quiz') di JS
     *  benar2 memanggil runOcr('quiz') dulu saat sumbernya kamera & teksnya belum ada, dan tombol
     *  manual "Jadikan teks" khusus Generator Soal sudah dihapus (RPM Learning tetap punya tombolnya
     *  sendiri, jadi tak bisa dicek hilang total dari halaman — cek scoped ke blok quiz.source==='camera'). */
    public function test_buat_soal_dari_foto_otomatis_ocr_tanpa_langkah_manual(): void
    {
        $user = $this->guruWithKey();

        $html = $this->actingAs($user)->get(route('ai.teacher.index'))->assertOk()->getContent();

        $this->assertStringContainsString(
            "tool === 'quiz' && this.quiz.source === 'camera' && !(this.ocr.quiz.text || '').trim()",
            $html
        );
        $this->assertStringContainsString('const ok = await this.runOcr(\'quiz\');', $html);

        // Blok "Foto halaman buku" Generator Soal (di antara pembuka blok camera & grid preview foto)
        // tak boleh lagi punya tombol @click="runOcr('quiz')" — itu skrg dipicu otomatis dari submit().
        $blokFotoBuku = preg_match(
            "/Foto halaman buku.*?grid grid-cols-3 gap-2\" x-show=\"ocr\\.quiz\\.images\\.length\"/s",
            $html,
            $m
        ) ? $m[0] : '';
        $this->assertNotSame('', $blokFotoBuku, 'Blok Foto halaman buku Generator Soal tak ditemukan di halaman.');
        $this->assertStringNotContainsString("@click=\"runOcr('quiz')\"", $blokFotoBuku);
        $this->assertStringContainsString('Tak perlu "Jadikan teks" manual', $blokFotoBuku);
    }

    /** Bug nyata dilaporkan user: klik "Jadikan teks" (baik di RPM Learning maupun Generate Soal)
     *  gagal dgn "The route ai/undefined could not be found." — krn key `ocr` di object `urls`
     *  Alpine tak pernah didaftarkan (fetch(this.urls.ocr, ...) jadi fetch(undefined, ...), browser
     *  resolve jadi "/ai/undefined" relatif thd halaman "/ai/teacher"). Pastikan URL-nya benar2 ada. */
    public function test_halaman_ai_teacher_mendaftarkan_url_ocr(): void
    {
        $user = $this->guruWithKey();

        $html = $this->actingAs($user)->get(route('ai.teacher.index'))->assertOk()->getContent();

        $this->assertStringContainsString("ocr: '".route('ai.teacher.ocr')."'", $html);
    }

    public function test_retry_materi_pending_memanggil_submit_dan_bukan_method_yang_tidak_ada(): void
    {
        $user = $this->guruWithKey();

        $html = $this->actingAs($user)->get(route('ai.teacher.index'))->assertOk()->getContent();

        $this->assertStringContainsString('if (d.processing)', $html);
        $this->assertStringContainsString('this.submit(tool);', $html);
        $this->assertStringNotContainsString('this.doGenerate(tool);', $html);
    }

    public function test_retry_materi_pending_menampilkan_status_loading_bukan_error(): void
    {
        $user = $this->guruWithKey();

        $html = $this->actingAs($user)->get(route('ai.teacher.index'))->assertOk()->getContent();

        $this->assertStringContainsString('materialProcessing = true', $html);
        $this->assertStringContainsString("materialProcessingTool === 'quiz'", $html);
        $this->assertStringNotContainsString(
            "this.error = 'Materi sedang diproses (embedding). Menunggu 4 detik",
            $html
        );
    }

    public function test_panel_hasil_menampilkan_loading_saat_ai_generate_soal(): void
    {
        $user = $this->guruWithKey();

        $html = $this->actingAs($user)->get(route('ai.teacher.index'))->assertOk()->getContent();

        $this->assertStringContainsString('loading || materialProcessing || ocr.loading', $html);
        $this->assertStringContainsString('AI sedang generate soal…', $html);
        $this->assertStringContainsString('aria-live="polite"', $html);
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
