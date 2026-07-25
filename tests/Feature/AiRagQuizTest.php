<?php

namespace Tests\Feature;

use App\Exceptions\AiDailyQuotaExhaustedException;
use App\Exceptions\AiRateLimitedException;
use App\Jobs\IngestAiDocumentJob;
use App\Models\AiDocument;
use App\Models\AiDocumentChunk;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;
use ZipArchive;

/*
| Generator Soal berbasis RAG.
|
| Sebelumnya materi file dipotong pada 8.000 karakter PERTAMA, sehingga permintaan
| soal untuk bab di tengah buku dijawab dari kata pengantar. Tes di berkas ini
| mengunci perilaku penggantinya: ambil bagian yang relevan, dan jangan hanguskan
| progres embedding ketika kuota harian Gemini habis.
*/
class AiRagQuizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('ai.provider', 'gemini');
        config()->set('ai.api_key', 'gemini-test-key');
        config()->set('ai.fallback_providers', []);

        // Asisten Guru mewajibkan API key pribadi guru untuk generate.
        User::created(function (User $user) {
            if ($user->hasGeminiApiKey() || in_array($user->access, ['siswa', 'orangtua'], true)) {
                return;
            }
            $user->forceFill([
                'gemini_api_key' => Crypt::encryptString('AIzaSyTestPersonalKeyForFeatureTests01'),
                'gemini_api_key_hint' => 'ts01',
            ])->saveQuietly();
        });
    }

    private function guru(string $username = 'guru-rag'): User
    {
        return User::create([
            'username' => $username,
            'password' => 'password',
            'access' => 'guru',
            'gemini_account' => $username.'@belajar.id',
        ]);
    }

    /** Materi panjang yang bagian relevannya sengaja diletakkan jauh setelah karakter ke-8.000. */
    private function bukuTebal(string $topikTersembunyi): string
    {
        return str_repeat('Kata pengantar dan daftar isi buku ini. ', 400)
            .$topikTersembunyi
            .str_repeat(' Lampiran dan daftar pustaka.', 100);
    }

    private function quizPayload(array $override = []): array
    {
        return array_merge([
            'topik' => 'Fotosintesis',
            'jumlah' => 1,
            'jenis_soal' => ['pg'],
            'tingkat' => 'sedang',
        ], $override);
    }

    /**
     * Unggahan DOCX minimal yang bisa diekstrak DocumentText (bukan PDF palsu).
     * Validasi route quiz: mimes pdf|doc|docx.
     */
    private function docxUpload(string $filename, string $text): UploadedFile
    {
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'rag-quiz-'.uniqid('', true).'.docx';
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->fail('Gagal membuat file DOCX uji coba.');
        }

        $zip->addFromString('[Content_Types].xml', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML);

        $zip->addFromString('_rels/.rels', <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML);

        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $zip->addFromString('word/document.xml', <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body><w:p><w:r><w:t xml:space="preserve">{$escaped}</w:t></w:r></w:p></w:body>
</w:document>
XML);
        $zip->close();

        return new UploadedFile(
            $path,
            $filename,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            null,
            true,
        );
    }

    public function test_materi_relevan_diambil_walau_letaknya_jauh_di_dalam_buku(): void
    {
        $guru = $this->guru();
        $kalimatTarget = 'Fotosintesis mengubah karbon dioksida dan air menjadi glukosa.';

        $doc = AiDocument::create([
            'user_uuid' => $guru->uuid,
            'title' => 'Buku IPA Kelas 5.pdf',
            'file_path' => 'ai_documents/ipa.pdf',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PROCESSED,
            'chunk_count' => 2,
        ]);

        // Chunk 0 = pembuka (yang dulu ikut terkirim), chunk 1 = bagian yang dicari.
        AiDocumentChunk::create([
            'document_id' => $doc->uuid,
            'ord' => 0,
            'content' => 'Kata pengantar dan daftar isi buku ini.',
            'embedding' => [0.0, 1.0],
        ]);
        AiDocumentChunk::create([
            'document_id' => $doc->uuid,
            'ord' => 1,
            'content' => $kalimatTarget,
            'embedding' => [1.0, 0.0],
        ]);

        // Query embedding sejajar dengan chunk 1 → chunk itu yang menang.
        $this->mock(GeminiService::class, function (MockInterface $mock) use ($kalimatTarget) {
            $mock->shouldReceive('embed')->andReturn([1.0, 0.0]);
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt) => str_contains($prompt, $kalimatTarget)
                    && str_contains($prompt, 'Fokus topik: "Fotosintesis"'))
                ->andReturn([
                    'text' => "1. Contoh soal\n\nKUNCI JAWABAN: A",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                ]);
        });

        $this->actingAs($guru)
            ->postJson(route('ai.teacher.quiz'), $this->quizPayload([
                'document_uuid' => $doc->uuid,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_topik_wajib_diisi(): void
    {
        $guru = $this->guru();

        $this->actingAs($guru)
            ->postJson(route('ai.teacher.quiz'), $this->quizPayload(['topik' => '']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('topik');
    }

    public function test_guru_tidak_bisa_memakai_materi_guru_lain(): void
    {
        $pemilik = $this->guru('guru-pemilik');
        $penyusup = $this->guru('guru-penyusup');

        $doc = AiDocument::create([
            'user_uuid' => $pemilik->uuid,
            'title' => 'Buku Rahasia.pdf',
            'file_path' => 'ai_documents/rahasia.pdf',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PROCESSED,
            'chunk_count' => 1,
        ]);

        $this->actingAs($penyusup)
            ->postJson(route('ai.teacher.quiz'), $this->quizPayload([
                'document_uuid' => $doc->uuid,
            ]))
            ->assertStatus(404)
            ->assertJsonPath('ok', false);
    }

    public function test_materi_yang_masih_diproses_memberi_pesan_jelas(): void
    {
        $guru = $this->guru();

        $doc = AiDocument::create([
            'user_uuid' => $guru->uuid,
            'title' => 'Buku Besar.pdf',
            'file_path' => 'ai_documents/besar.pdf',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PENDING,
        ]);

        $this->actingAs($guru)
            ->postJson(route('ai.teacher.quiz'), $this->quizPayload([
                'document_uuid' => $doc->uuid,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('processing', true)
            ->assertJsonPath('document_uuid', $doc->uuid);
    }

    public function test_file_besar_disimpan_dan_diantre_untuk_embedding(): void
    {
        Storage::fake('local');
        Queue::fake();
        config()->set('ai.rag.queue_ingest', true);

        $guru = $this->guru();

        // Pakai DOCX (bukan PDF palsu): validasi mimes hanya pdf/doc/docx, dan
        // PdfParser menolak byte teks polos yang diganti ekstensi .pdf.
        $this->actingAs($guru)
            ->postJson(route('ai.teacher.quiz'), $this->quizPayload([
                'file' => $this->docxUpload(
                    'buku-ipa.docx',
                    $this->bukuTebal('Fotosintesis terjadi di kloroplas.'),
                ),
            ]))
            ->assertStatus(422)
            ->assertJsonPath('processing', true);

        Queue::assertPushed(IngestAiDocumentJob::class);
        $this->assertDatabaseHas('ai_documents', [
            'user_uuid' => $guru->uuid,
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PENDING,
        ]);
    }

    public function test_file_kecil_tetap_dikirim_utuh_tanpa_membakar_kuota_embedding(): void
    {
        Storage::fake('local');
        Queue::fake();

        $guru = $this->guru();
        $isi = 'Fotosintesis terjadi di kloroplas daun.';

        $this->mock(GeminiService::class, function (MockInterface $mock) use ($isi) {
            // Tidak boleh ada embedding sama sekali untuk file sekecil ini.
            $mock->shouldNotReceive('embed');
            $mock->shouldReceive('generate')
                ->once()
                ->withArgs(fn (string $prompt) => str_contains($prompt, $isi))
                ->andReturn([
                    'text' => "1. Contoh soal\n\nKUNCI JAWABAN: A",
                    'model' => 'gemini-test',
                    'prompt_tokens' => 10,
                    'completion_tokens' => 5,
                ]);
        });

        $this->actingAs($guru)
            ->postJson(route('ai.teacher.quiz'), $this->quizPayload([
                'file' => $this->docxUpload('rpp-singkat.docx', $isi),
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true);

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('ai_documents', 0);
    }

    public function test_kuota_harian_habis_tidak_menghanguskan_progres(): void
    {
        Storage::fake('local');
        config()->set('ai.rag.chunk_chars', 200);
        config()->set('ai.rag.chunk_overlap', 0);

        $guru = $this->guru();
        Storage::disk('local')->put('ai_documents/besar.txt', $this->bukuTebal('Fotosintesis.'));

        $doc = AiDocument::create([
            'user_uuid' => $guru->uuid,
            'title' => 'Buku Besar.txt',
            'file_path' => 'ai_documents/besar.txt',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PENDING,
        ]);

        // Mockery: andReturn(...)->andThrow() melempar di SEMUA panggilan.
        // Harus dipisah per-call agar 2 embed sukses, ke-3 melempar kuota.
        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('embed')->once()->andReturn([1.0, 0.0]);
        $gemini->shouldReceive('embed')->once()->andReturn([0.9, 0.1]);
        $gemini->shouldReceive('embed')->once()
            ->andThrow(new AiDailyQuotaExhaustedException('Kuota AI harian sudah habis.'));

        (new RagService($gemini))->ingest(
            $doc,
            Storage::disk('local')->path('ai_documents/besar.txt'),
        );

        $doc->refresh();
        $this->assertSame(AiDocument::STATUS_PARTIAL, $doc->status);
        $this->assertSame(2, $doc->chunk_count, 'Chunk yang sudah ter-embed harus dipertahankan.');
        $this->assertSame(2, $doc->chunks()->count());
    }

    public function test_rate_limit_per_menit_tidak_dianggap_kuota_harian_tanpa_throw(): void
    {
        Storage::fake('local');
        config()->set('ai.rag.chunk_chars', 200);
        config()->set('ai.rag.chunk_overlap', 0);

        $guru = $this->guru('guru-rpm');
        Storage::disk('local')->put('ai_documents/rpm.txt', $this->bukuTebal('Fotosintesis.'));

        $doc = AiDocument::create([
            'user_uuid' => $guru->uuid,
            'title' => 'Buku RPM.txt',
            'file_path' => 'ai_documents/rpm.txt',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PENDING,
        ]);

        $gemini = Mockery::mock(GeminiService::class);
        $gemini->shouldReceive('embed')->once()->andReturn([1.0, 0.0]);
        $gemini->shouldReceive('embed')->once()
            ->andThrow(new AiRateLimitedException('Terlalu banyak permintaan.', 45));

        try {
            (new RagService($gemini))->ingest(
                $doc,
                Storage::disk('local')->path('ai_documents/rpm.txt'),
            );
            $this->fail('AiRateLimitedException harus dilempar agar job menunda singkat.');
        } catch (AiRateLimitedException $e) {
            $this->assertSame(45, $e->retryAfterSeconds);
        }

        $doc->refresh();
        // Progres chunk pertama disimpan; exception di-rethrow (bukan silent partial-only).
        $this->assertSame(1, $doc->chunk_count);
        $this->assertSame(1, $doc->chunks()->count());
    }

    public function test_lanjutan_ingest_tidak_mengulang_chunk_yang_sudah_ada(): void
    {
        Storage::fake('local');
        config()->set('ai.rag.chunk_chars', 200);
        config()->set('ai.rag.chunk_overlap', 0);

        $guru = $this->guru();
        $isi = $this->bukuTebal('Fotosintesis.');
        Storage::disk('local')->put('ai_documents/besar.txt', $isi);
        $abs = Storage::disk('local')->path('ai_documents/besar.txt');

        $doc = AiDocument::create([
            'user_uuid' => $guru->uuid,
            'title' => 'Buku Besar.txt',
            'file_path' => 'ai_documents/besar.txt',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PENDING,
        ]);

        $rag = new RagService(Mockery::mock(GeminiService::class));
        $totalChunk = count($rag->chunk($rag->extractText($abs)));
        $this->assertGreaterThan(3, $totalChunk, 'Butuh dokumen multi-chunk agar tes ini bermakna.');

        // Percobaan pertama: 2 chunk berhasil lalu kuota harian habis.
        $gemini1 = Mockery::mock(GeminiService::class);
        $gemini1->shouldReceive('embed')->once()->andReturn([1.0, 0.0]);
        $gemini1->shouldReceive('embed')->once()->andReturn([0.9, 0.1]);
        $gemini1->shouldReceive('embed')->once()
            ->andThrow(new AiDailyQuotaExhaustedException('Kuota AI harian sudah habis.'));
        (new RagService($gemini1))->ingest($doc, $abs);
        $this->assertSame(2, $doc->refresh()->chunk_count);

        // Percobaan kedua: hanya SISA chunk yang boleh di-embed ulang.
        $sisa = $totalChunk - 2;
        $gemini2 = Mockery::mock(GeminiService::class);
        $gemini2->shouldReceive('embed')->times($sisa)->andReturn([0.5, 0.5]);
        (new RagService($gemini2))->ingest($doc, $abs);

        $doc->refresh();
        $this->assertSame(AiDocument::STATUS_PROCESSED, $doc->status);
        $this->assertSame($totalChunk, $doc->chunk_count);
    }

    public function test_daftar_materi_hanya_milik_guru_yang_login(): void
    {
        $guruA = $this->guru('guru-a');
        $guruB = $this->guru('guru-b');

        AiDocument::create([
            'user_uuid' => $guruA->uuid,
            'title' => 'Buku Guru A.pdf',
            'file_path' => 'ai_documents/a.pdf',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PROCESSED,
            'chunk_count' => 3,
        ]);
        AiDocument::create([
            'user_uuid' => $guruB->uuid,
            'title' => 'Buku Guru B.pdf',
            'file_path' => 'ai_documents/b.pdf',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PROCESSED,
            'chunk_count' => 1,
        ]);
        $adminDoc = AiDocument::create([
            'user_uuid' => $guruA->uuid,
            'title' => 'Dokumen Admin.pdf',
            'file_path' => 'ai_documents/admin.pdf',
            'source' => AiDocument::SOURCE_ADMIN_UPLOAD,
            'status' => AiDocument::STATUS_PROCESSED,
            'chunk_count' => 2,
        ]);

        $this->actingAs($guruA)
            ->getJson(route('ai.teacher.materials'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'materials')
            ->assertJsonPath('materials.0.title', 'Buku Guru A.pdf')
            ->assertJsonPath('materials.0.ready', true);

        // Admin upload milik user yang sama tidak boleh dipakai lewat Generator Soal.
        $this->actingAs($guruA)
            ->postJson(route('ai.teacher.quiz'), $this->quizPayload([
                'document_uuid' => $adminDoc->uuid,
            ]))
            ->assertStatus(404)
            ->assertJsonPath('ok', false);
    }

    public function test_dokumen_partial_tetap_bisa_dipakai_membuat_soal(): void
    {
        $guru = $this->guru();

        $doc = AiDocument::create([
            'user_uuid' => $guru->uuid,
            'title' => 'Buku Setengah Jadi.pdf',
            'file_path' => 'ai_documents/setengah.pdf',
            'source' => AiDocument::SOURCE_TEACHER_MATERIAL,
            'status' => AiDocument::STATUS_PARTIAL,
            'chunk_count' => 1,
        ]);

        AiDocumentChunk::create([
            'document_id' => $doc->uuid,
            'ord' => 0,
            'content' => 'Fotosintesis terjadi di kloroplas.',
            'embedding' => [1.0, 0.0],
        ]);

        $this->mock(GeminiService::class, function (MockInterface $mock) {
            $mock->shouldReceive('embed')->andReturn([1.0, 0.0]);
            $mock->shouldReceive('generate')->once()->andReturn([
                'text' => "1. Contoh soal\n\nKUNCI JAWABAN: A",
                'model' => 'gemini-test',
                'prompt_tokens' => 10,
                'completion_tokens' => 5,
            ]);
        });

        $this->actingAs($guru)
            ->postJson(route('ai.teacher.quiz'), $this->quizPayload([
                'document_uuid' => $doc->uuid,
            ]))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
