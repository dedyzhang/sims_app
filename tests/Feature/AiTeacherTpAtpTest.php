<?php

namespace Tests\Feature;

use App\Models\AiTeacherHistory;
use App\Models\Pelajaran;
use App\Models\User;
use App\Services\GeminiService;
use App\Support\SchoolLetterhead;
use App\Support\StandardCurriculumCp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AiTeacherTpAtpTest extends TestCase
{
    use RefreshDatabase;

    private string $sampleTpAtpContent = <<<'TXT'
YAYASAN BUMI MAITRI
SMP MAITREYAWIRA TANJUNGPINANG
TERAKREDITASI A
Komp. Gedung Pendidikan dan Pelatihan Buddhis Bumi Maitreya
Jl. Prof. Ir. Sutami No. 38 Telp (0771) 4505723 Email smpmai.tpi@gmail.com
Tanjungpinang – Kepulauan Riau

TUJUAN PEMBELAJARAN (TP)
MATA PELAJARAN : Ilmu Pengetahuan Sosial
FASE / KELAS : D/VII-IX
TAHUN PELAJARAN : 2026-2027

TABEL TUJUAN PEMBELAJARAN
ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Pemahaman Konsep | Pada akhir Fase D, murid memiliki kemampuan menjelaskan kondisi geografis | Diri dan Keluarga | Menjelaskan asal-usul keluarga | PK.7.1 | 27 | Ganjil
Pemahaman Konsep | Pada akhir Fase D, murid memiliki kemampuan menjelaskan kondisi geografis | Diri dan Keluarga | Memanfaatkan peta lokasi | PK.7.2 | 27 | Ganjil

ALUR TUJUAN PEMBELAJARAN (ATP)
MATA PELAJARAN : Ilmu Pengetahuan Sosial
FASE / KELAS : D/VII-IX
TAHUN PELAJARAN : 2026-2027

DIAGRAM ALUR TUJUAN PEMBELAJARAN
TAHAP | KELAS | SEMESTER | KODE | JUDUL ALUR | TIPE
Awal | 7 | Ganjil | - | Awal fase D Kelas 7 Semester Ganjil | start
TP 1 | 7 | Ganjil | PK.7.1 | Kesadaran akan keberadaan diri | tp
Transisi | 7 | Genap | - | Menuju fase D kelas 8 | transition
Akhir | 9 | Genap | - | Capaian Pembelajaran Akhir Fase D Kelas 9 | end

TANDA TANGAN:
Tanjungpinang, 17 Juli 2026
Guru Mata Pelajaran

Desmalini, S.E., Gr.
NIK. 10090150308
TXT;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.provider', 'gemini');
        config()->set('ai.api_key', 'gemini-test-key');
        config()->set('ai.fallback_providers', []);

        User::created(function (User $user) {
            if ($user->hasGeminiApiKey()) {
                return;
            }
            if (in_array($user->access, ['siswa', 'orangtua'], true)) {
                return;
            }
            $user->forceFill([
                'gemini_api_key' => \Illuminate\Support\Facades\Crypt::encryptString('AIzaSyTestPersonalKeyForTpAtpFeatureTests01'),
                'gemini_api_key_hint' => 'ts01',
            ])->saveQuietly();
        });
    }

    public function test_guru_can_access_ai_teacher_index_with_tpatp_data(): void
    {
        $guru = User::create([
            'username' => 'guru-tpatp',
            'password' => 'password',
            'access' => 'guru',
            'name' => 'Guru Mata Pelajaran',
        ]);

        $response = $this->actingAs($guru)->get(route('ai.teacher.index'));

        $response->assertOk();
        $response->assertSee('TP &amp; ATP', false);
        $response->assertViewHas('standardCps');
        $response->assertViewHas('pelajarans');
    }

    public function test_guru_can_generate_tpatp_with_gemini_service(): void
    {
        $guru = User::create([
            'username' => 'guru-ips',
            'password' => 'password',
            'access' => 'guru',
            'name' => 'Desmalini, S.E., Gr.',
        ]);

        $capturedPrompt = '';
        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt, array $options) use (&$capturedPrompt) {
                    $capturedPrompt = $prompt;

                    return str_contains($prompt, 'Ilmu Pengetahuan Sosial')
                        && str_contains($prompt, 'TABEL TUJUAN PEMBELAJARAN')
                        && str_contains($prompt, 'DIAGRAM ALUR TUJUAN PEMBELAJARAN')
                        && ($options['max_output_tokens'] ?? null) === 8192;
                })
                ->andReturn([
                    'text' => $this->sampleTpAtpContent,
                    'model' => 'gemini-test',
                    'prompt_tokens' => 120,
                    'completion_tokens' => 450,
                ]);
        });

        $response = $this->actingAs($guru)->postJson(route('ai.teacher.tpatp'), [
            'mapel' => 'Ilmu Pengetahuan Sosial',
            'mode' => 'both',
            'fase' => 'D',
            'kelas' => '7-9',
            'tahun_ajaran' => '2026-2027',
            'nama_guru' => 'Desmalini, S.E., Gr.',
            'nip_guru' => '10090150308',
            'alokasi_jp_per_minggu' => 4,
            'catatan' => 'Fokus pada keberagaman kondisi geografis',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
        ]);
        $response->assertJsonStructure([
            'ok',
            'answer',
            'history' => [
                'uuid',
                'type',
                'title',
            ],
        ]);

        $this->assertDatabaseHas('ai_teacher_histories', [
            'user_uuid' => $guru->uuid,
            'type' => 'tpatp',
            'title' => 'TP & ATP - Ilmu Pengetahuan Sosial',
        ]);
    }

    public function test_preview_tpatp_returns_rendered_html(): void
    {
        $guru = User::create([
            'username' => 'guru-preview',
            'password' => 'password',
            'access' => 'guru',
        ]);

        $response = $this->actingAs($guru)->postJson(route('ai.teacher.tpatp.preview'), [
            'content' => $this->sampleTpAtpContent,
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'parsed' => true,
        ]);
        $html = $response->json('html');
        $this->assertStringContainsString('TUJUAN PEMBELAJARAN', $html);
        $this->assertStringContainsString('PK.7.1', $html);
        $this->assertStringContainsString('Ilmu Pengetahuan Sosial', $html);
        $this->assertStringContainsString('Pemahaman Konsep', $html);
        $this->assertStringContainsString('Desmalini, S.E., Gr.', $html);
    }

    public function test_export_tpatp_word_downloads_valid_docx(): void
    {
        $guru = User::create([
            'username' => 'guru-word',
            'password' => 'password',
            'access' => 'guru',
        ]);

        $response = $this->actingAs($guru)->post(route('ai.teacher.tpatp.export-word'), [
            'text' => $this->sampleTpAtpContent,
            'title' => 'TP-ATP-IPS',
        ]);

        $response->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            $response->headers->get('Content-Type')
        );
        $this->assertStringContainsString(
            'tp-atp-ips',
            strtolower((string) $response->headers->get('Content-Disposition'))
        );
        $this->assertStringContainsString(
            '.docx',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_export_tpatp_pdf_downloads_landscape_pdf(): void
    {
        $guru = User::create([
            'username' => 'guru-pdf',
            'password' => 'password',
            'access' => 'guru',
        ]);

        $response = $this->actingAs($guru)->post(route('ai.teacher.tpatp.export-pdf'), [
            'text' => $this->sampleTpAtpContent,
            'title' => 'TP-ATP-IPS',
        ]);

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString(
            'tp-atp-ips',
            strtolower((string) $response->headers->get('Content-Disposition'))
        );
        $this->assertStringContainsString(
            '.pdf',
            (string) $response->headers->get('Content-Disposition')
        );
    }

    public function test_custom_cp_is_injected_into_prompt(): void
    {
        $guru = User::create([
            'username' => 'guru-custom-cp',
            'password' => 'password',
            'access' => 'guru',
        ]);

        $customCp = 'CP Khusus: Murid mampu merancang aplikasi robotika berbasis Arduino di tingkat SMP.';

        $capturedPrompt = '';
        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt, $customCp) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt) use (&$capturedPrompt, $customCp) {
                    $capturedPrompt = $prompt;

                    return str_contains($prompt, $customCp);
                })
                ->andReturn([
                    'text' => $this->sampleTpAtpContent,
                    'model' => 'gemini-test',
                    'prompt_tokens' => 50,
                    'completion_tokens' => 200,
                ]);
        });

        $response = $this->actingAs($guru)->postJson(route('ai.teacher.tpatp'), [
            'mapel' => 'Informatika',
            'mode' => 'tp',
            'cp_custom' => $customCp,
        ]);

        $response->assertOk();
        $this->assertStringContainsString($customCp, $capturedPrompt);
    }

    public function test_kop_surat_mengikuti_setting_aplikasi_bukan_kop_luar(): void
    {
        \App\Models\Setting::set('kop_teks', "<p>YAYASAN PENDIDIKAN NUSANTARA</p>\n<p><strong>SMP NUSANTARA CERDAS</strong></p>\n<p>Jl. Jenderal Sudirman No. 88, Tanjungpinang</p>");

        $guru = User::create([
            'username' => 'guru-kop-app',
            'password' => 'password',
            'access' => 'guru',
        ]);

        // Input berisi kop asing yang berbeda (misalnya dari dokumen luar / template lain)
        $alienContent = <<<TXT
YAYASAN ASING DARI DOKUMEN LUAR
SMP LAIN YANG TIDAK TERDAFTAR
Alamat antah berantah

TUJUAN PEMBELAJARAN (TP)
MATA PELAJARAN : IPA
FASE / KELAS : D/VII
TAHUN PELAJARAN : 2026-2027

TABEL TUJUAN PEMBELAJARAN
ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
Pemahaman IPA | Memahami ekosistem | Komponen Ekosistem | Mengidentifikasi rantai makanan | IPA.7.1 | 20 | Ganjil
TXT;

        $response = $this->actingAs($guru)->postJson(route('ai.teacher.tpatp.preview'), [
            'content' => $alienContent,
        ]);

        $response->assertOk();
        $html = $response->json('html');

        // Harus menampilkan kop resmi dari Setting aplikasi
        $this->assertStringContainsString('YAYASAN PENDIDIKAN NUSANTARA', $html);
        $this->assertStringContainsString('SMP NUSANTARA CERDAS', $html);
        $this->assertStringContainsString('Jl. Jenderal Sudirman No. 88', $html);

        // Tidak boleh memakai kop asing dari dokumen luar
        $this->assertStringNotContainsString('YAYASAN ASING DARI DOKUMEN LUAR', $html);
        $this->assertStringNotContainsString('SMP LAIN YANG TIDAK TERDAFTAR', $html);
    }

    public function test_manual_lingkup_materi_is_injected_into_prompt_and_saved_in_metadata(): void
    {
        $guru = User::create([
            'username' => 'guru-lingkup-manual',
            'password' => 'password',
            'access' => 'guru',
            'name' => 'Guru IPS Hebat',
        ]);

        $customLingkup = "1. Keberagaman Kondisi Geografis Nusantara\n2. Dinamika Pemanfaatan Sumber Daya Maritim";
        $capturedPrompt = '';

        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt) use (&$capturedPrompt) {
                    $capturedPrompt = $prompt;

                    return str_contains($prompt, 'LINGKUP MATERI YANG DITENTUKAN GURU')
                        && str_contains($prompt, 'Keberagaman Kondisi Geografis Nusantara');
                })
                ->andReturn([
                    'text' => $this->sampleTpAtpContent,
                    'model' => 'gemini-test',
                    'prompt_tokens' => 80,
                    'completion_tokens' => 300,
                ]);
        });

        $response = $this->actingAs($guru)->postJson(route('ai.teacher.tpatp'), [
            'mapel' => 'Ilmu Pengetahuan Sosial',
            'mode' => 'both',
            'cp' => 'Peserta didik memahami kondisi geografis nusantara.',
            'lingkup_materi' => $customLingkup,
        ]);

        $response->assertOk();
        $this->assertStringContainsString('Keberagaman Kondisi Geografis Nusantara', $capturedPrompt);
        $this->assertStringContainsString('Dinamika Pemanfaatan Sumber Daya Maritim', $capturedPrompt);

        // Periksa riwayat history tersimpan dengan metadata lingkup_materi
        $this->assertDatabaseHas('ai_teacher_histories', [
            'user_uuid' => $guru->uuid,
            'type' => 'tpatp',
        ]);

        $history = \App\Models\AiTeacherHistory::where('user_uuid', $guru->uuid)->latest('created_at')->first();
        $this->assertNotNull($history);
        $this->assertSame($customLingkup, $history->metadata['lingkup_materi'] ?? null);
    }

    public function test_tpatp_prompt_enforces_min_2_max_4_tps_per_materi(): void
    {
        $guru = User::create([
            'username' => 'guru-3tingkat',
            'password' => 'password',
            'access' => 'guru',
            'name' => 'Guru IPA 3 Tingkat',
        ]);

        $capturedPrompt = '';
        $this->mock(GeminiService::class, function (MockInterface $mock) use (&$capturedPrompt) {
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(function (string $prompt) use (&$capturedPrompt) {
                    $capturedPrompt = $prompt;

                    return str_contains($prompt, 'ATURAN JUMLAH DAN VARIASI TP PER LINGKUP MATERI');
                })
                ->andReturn([
                    'text' => $this->sampleTpAtpContent,
                    'model' => 'gemini-test',
                    'prompt_tokens' => 60,
                    'completion_tokens' => 200,
                ]);
        });

        $response = $this->actingAs($guru)->postJson(route('ai.teacher.tpatp'), [
            'mapel' => 'Ilmu Pengetahuan Alam',
            'mode' => 'both',
            'fase_kelas' => 'D/VII-IX',
            'cp' => 'Peserta didik memahami pengukuran dan ekosistem.',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('ATURAN JUMLAH DAN VARIASI TP PER LINGKUP MATERI', $capturedPrompt);
        $this->assertStringContainsString('DILARANG KERAS membuat semua materi seragam hanya 2 TP', $capturedPrompt);
        $this->assertStringContainsString('WAJIB DIBUAT MENJADI 3 SAMPAI 4 BUTIR TP', $capturedPrompt);
        $this->assertStringContainsString('DILARANG membuat hanya 1 butir TP', $capturedPrompt);
        $this->assertStringContainsString('DILARANG membuat lebih dari 4 butir TP', $capturedPrompt);
    }
}
