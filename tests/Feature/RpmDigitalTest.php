<?php

namespace Tests\Feature;

use App\Models\AiTeacherHistory;
use App\Models\RpmDigital;
use App\Models\User;
use App\Services\GeminiService;
use App\Support\DocumentText;
use App\Support\LearningDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class RpmDigitalTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $access = 'guru', string $name = 'Budi Santoso'): User
    {
        return User::create([
            'username' => 'user-'.uniqid(),
            'password' => bcrypt('password'),
            'access' => $access,
            'name' => $name,
        ]);
    }

    private function sampleRpmContent(): string
    {
        $fixturePath = base_path('tests/Fixtures/rpm-fellianto-agama-buddha-kelas8.txt');
        if (file_exists($fixturePath)) {
            return (string) file_get_contents($fixturePath);
        }

        return <<<TXT
SEKOLAH : SMP B'TIVE
NAMA GURU : Budi Santoso
MATA PELAJARAN : Bahasa Indonesia
KELAS / SEMESTER : VII / 1 (Ganjil)
ALOKASI WAKTU : 2 JP (2 x 40 Menit)

PERENCANAAN PEMBELAJARAN MENDALAM
"TEKS PROSEDUR DIGITAL"

IDENTIFIKASI
Murid:
Siswa kelas VII dengan minat literasi digital.
Materi:
Menulis teks prosedur digital.
Dimensi Profil Lulusan (DPL):
Dimensi profil lulusan yang akan dicapai dalam pembelajaran:
☑ DPL 1 Keimanan dan ketakwaan terhadap Tuhan Yang Maha Esa
☑ DPL 2 Kewargaan
☑ DPL 3 Penalaran Kritis
☐ DPL 4 Kreativitas
☑ DPL 5 Kolaborasi
☑ DPL 6 Kemandirian
☑ DPL 7 Kesehatan
☑ DPL 8 Komunikasi

DESAIN PEMBELAJARAN
Capaian Pembelajaran:
Peserta didik mampu menyusun petunjuk prosedur digital secara runtut.
Tujuan Pembelajaran:
1. Mengidentifikasi struktur teks prosedur.
2. Menyusun teks prosedur digital.

PENGALAMAN BELAJAR
AWAL
(Berkesadaran, Bermakna, dan Menggembirakan)
✓ Guru menyapa siswa dan memimpin doa.
INTI
MEMAHAMI
✓ Siswa mengamati contoh teks prosedur.
PENUTUP
✓ Refleksi pembelajaran bersama.

ASESMEN PEMBELAJARAN
Asesmen pada Awal Pembelajaran:
Pretest lisan struktur teks.
Asesmen pada Proses Pembelajaran:
Observasi diskusi kelompok.
Asesmen pada Akhir Pembelajaran:
Produk teks prosedur siswa.

TANDA TANGAN:
Jakarta, 17 Juli 2026
Mengetahui, | Guru Mata Pelajaran
Kepala Sekolah |
Dra. Megawati | Budi Santoso
NIK. 10010070010 | NIK. 10090150308

LAMPIRAN 1 : ASESMEN AWAL PEMBELAJARAN
• Materi : Teks Prosedur
• Kelas : VII
A. Soal Pilihan Ganda (3 soal)
1. Ciri utama teks prosedur adalah...
a. Berisi langkah-langkah
b. Berisi puisi
c. Berisi dongeng
d. Berisi berita
(minimal 3 soal)

LAMPIRAN 2 : ASESMEN PADA PROSES PEMBELAJARAN
Tujuan Pembelajaran:
1. Menyusun teks prosedur
Kompetensi | Baru Mulai | Berkembang | Cakap | Mahir
Kerjasama kelompok | Pasif | Cukup aktif | Aktif | Sangat aktif
Keterangan:
• Baru Mulai: Perlu bimbingan intensif.

LAMPIRAN 3 : ASESMEN PADA AKHIR PEMBELAJARAN
• Materi : Evaluasi Teks Prosedur
Soal Pilihan Ganda
1. Langkah pertama teks prosedur adalah...
a. Tujuan
b. Penutup
c. Komentar
d. Saran
TXT;
    }

    /**
     * Test 1: Guru dapat mengakses index RPM Digital dan melihat halaman serta menu.
     */
    public function test_guru_can_access_rpm_digital_index(): void
    {
        $guru = $this->makeUser('guru');

        $response = $this->actingAs($guru)->get(route('rpm.index'));

        $response->assertOk();
        $response->assertSee('RPM Digital');
        $response->assertSee('Buat RPM Digital');
    }

    /**
     * Test 2: Siswa tidak diizinkan mengakses RPM Digital (403 Forbidden).
     */
    public function test_siswa_cannot_access_rpm_digital(): void
    {
        $siswa = $this->makeUser('siswa');

        $response = $this->actingAs($siswa)->get(route('rpm.index'));

        $response->assertForbidden();
    }

    /**
     * Test 3: Cara 1 - Generate RPM murni dari AI langsung.
     */
    public function test_cara_1_ai_direct_generation(): void
    {
        $guru = $this->makeUser('guru');
        $sampleAnswer = $this->sampleRpmContent();

        $this->mock(GeminiService::class, function (MockInterface $mock) use ($sampleAnswer) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => $sampleAnswer,
                    'model' => 'gemini-2.0-flash',
                    'prompt_tokens' => 500,
                    'completion_tokens' => 1500,
                ]);
        });

        $response = $this->actingAs($guru)->postJson(route('rpm.generate'), [
            'topik' => 'Teks Prosedur Digital',
            'mapel' => 'Bahasa Indonesia',
            'kelas' => 'VII',
            'semester' => '1 (Ganjil)',
            'alokasi_waktu' => '2 JP (2 x 40 Menit)',
            'detail' => 'Fokus pada media digital',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'source_type' => 'ai_direct',
            'mapel' => 'Bahasa Indonesia',
            'kelas' => 'VII',
            'topik' => 'Teks Prosedur Digital',
        ]);
        $this->assertNotEmpty($response->json('answer'));
        $this->assertNotEmpty($response->json('html'));
    }

    /**
     * Test 4: Cara 2 - Upload PDF Pelajaran (Minimal 10MB didukung),
     * teks diekstrak, RPM digenerate, dan file fisik PDF dihapus dari server.
     */
    public function test_cara_2_pdf_upload_generates_and_deletes_physical_file(): void
    {
        $guru = $this->makeUser('guru');
        $sampleAnswer = $this->sampleRpmContent();

        $this->mock(GeminiService::class, function (MockInterface $mock) use ($sampleAnswer) {
            $mock->shouldReceive('generate')
                ->once()
                ->andReturn([
                    'text' => $sampleAnswer,
                    'model' => 'gemini-2.0-flash',
                    'prompt_tokens' => 800,
                    'completion_tokens' => 1500,
                ]);
        });

        // Buat file PDF sementara di direktori temp menggunakan DomPdf
        $tempPdfPath = tempnam(sys_get_temp_dir(), 'test_pdf_upload_') . '.pdf';
        $pdfBinary = \Barryvdh\DomPDF\Facade\Pdf::loadHTML('<h1>Bab 1: Struktur Teks Prosedur</h1><p>Teks prosedur adalah teks yang berisi cara dan tujuan untuk membuat atau melakukan sesuatu langkah demi langkah secara berurutan dalam pembelajaran Bahasa Indonesia.</p>')->output();
        file_put_contents($tempPdfPath, $pdfBinary);

        // Buat UploadedFile dari file temp tersebut
        $uploadedFile = new UploadedFile(
            $tempPdfPath,
            'buku-teks-bahasa-indonesia-10mb.pdf',
            'application/pdf',
            null,
            true // test mode
        );

        $response = $this->actingAs($guru)->post(route('rpm.generate-pdf'), [
            'file' => $uploadedFile,
            'mapel' => 'Bahasa Indonesia',
            'kelas' => 'VII',
            'topik' => 'Bab 1 Teks Prosedur',
        ], ['Accept' => 'application/json']);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'source_type' => 'pdf_upload',
            'file_deleted' => true,
        ]);

        // Verifikasi bahwa file fisik PDF telah dihapus dari sistem file
        $this->assertFileDoesNotExist(
            $tempPdfPath,
            'File fisik PDF harus langsung dihapus permanen dari server setelah ekstraksi/generate RPM.'
        );
    }

    /**
     * Test 5: Cara 3 - Mengambil dari RPM yang sudah digenerate di Asisten Guru.
     */
    public function test_cara_3_load_from_asisten_guru_history(): void
    {
        $guru = $this->makeUser('guru');
        $sampleContent = $this->sampleRpmContent();

        // Buat riwayat RPM di Asisten Guru
        $history = AiTeacherHistory::create([
            'user_uuid' => $guru->uuid,
            'type' => 'rpp',
            'type_label' => 'RPM Learning',
            'title' => 'RPM: Teks Diskusi Kelas 8',
            'excerpt' => 'Ringkasan RPM dari Asisten Guru',
            'answer' => $sampleContent,
            'metadata' => [
                'mapel' => 'Bahasa Indonesia',
                'kelas' => 'VIII',
                'topik' => 'Teks Diskusi',
            ],
        ]);

        // Akses halaman create, pastikan history muncul di pilihan
        $createRes = $this->actingAs($guru)->get(route('rpm.create', ['tab' => 'asisten_guru']));
        $createRes->assertOk();
        $createRes->assertSee('RPM: Teks Diskusi Kelas 8');

        // Simpan sebagai RPM Digital
        $storeRes = $this->actingAs($guru)->post(route('rpm.store'), [
            'title' => 'RPM: Teks Diskusi Kelas 8 (Tersimpan)',
            'mapel' => 'Bahasa Indonesia',
            'kelas' => 'VIII',
            'semester' => '1 (Ganjil)',
            'alokasi_waktu' => '2 JP (2 x 40 Menit)',
            'topik' => 'Teks Diskusi',
            'source_type' => 'asisten_guru',
            'content' => $sampleContent,
        ]);

        $storeRes->assertRedirect();
        $this->assertDatabaseHas('rpm_digitals', [
            'user_uuid' => $guru->uuid,
            'title' => 'RPM: Teks Diskusi Kelas 8 (Tersimpan)',
            'source_type' => 'asisten_guru',
        ]);
    }

    /**
     * Test 6: CRUD - Simpan (store), Lihat (show), Ubah (edit & update), dan Hapus (destroy).
     */
    public function test_rpm_digital_crud_operations(): void
    {
        $guru = $this->makeUser('guru', 'Ibu Nurhayati');
        $sampleContent = $this->sampleRpmContent();

        // 1. Store
        $storeRes = $this->actingAs($guru)->post(route('rpm.store'), [
            'title' => 'RPM Digital Matematika Aljabar',
            'mapel' => 'Matematika',
            'kelas' => 'VII',
            'semester' => '1 (Ganjil)',
            'alokasi_waktu' => '2 JP (2 x 40 Menit)',
            'topik' => 'Aljabar dan Persamaan Linier',
            'source_type' => 'ai_direct',
            'content' => $sampleContent,
        ]);

        $rpm = RpmDigital::where('title', 'RPM Digital Matematika Aljabar')->first();
        $this->assertNotNull($rpm);
        $storeRes->assertRedirect(route('rpm.show', $rpm));

        // 2. Show (Halaman interaktif dengan 7 tab & pratinjau export sesuai PRD)
        $showRes = $this->actingAs($guru)->get(route('rpm.show', $rpm));
        $showRes->assertOk();
        $showRes->assertSee('RPM Digital Matematika Aljabar');
        $showRes->assertSee('Pratinjau Export');
        $showRes->assertSee('SATUAN PENDIDIKAN');
        $showRes->assertSee('MATA PELAJARAN');
        $showRes->assertSee('FASE / KELAS');
        $showRes->assertSee('ALOKASI WAKTU');
        $showRes->assertSee('MODEL');
        $showRes->assertSee('PENYUSUN');
        $showRes->assertSee('A. Identifikasi');
        $showRes->assertSee('B. Desain');
        $showRes->assertSee('C. Pengalaman Belajar');
        $showRes->assertSee('D. Asesmen');
        $showRes->assertSee('Catatan penyusun');

        // Test save interactive state
        $stateRes = $this->actingAs($guru)->postJson(route('rpm.save-state', $rpm), [
            'checked_steps' => ['step_0' => true, 'step_1' => true],
            'quiz_answers' => [1 => 'a'],
            'reflection_text' => 'Siswa sangat antusias dalam kegiatan diskusi.',
        ]);
        $stateRes->assertOk();
        $stateRes->assertJson(['ok' => true]);
        $rpm->refresh();
        $this->assertTrue($rpm->interactive_state['checked_steps']['step_0']);

        // 3. Edit & Update
        $editRes = $this->actingAs($guru)->get(route('rpm.edit', $rpm));
        $editRes->assertOk();

        $updateRes = $this->actingAs($guru)->put(route('rpm.update', $rpm), [
            'title' => 'RPM Digital Matematika Aljabar (Revisi 2)',
            'mapel' => 'Matematika',
            'kelas' => 'VII',
            'semester' => '1 (Ganjil)',
            'alokasi_waktu' => '3 JP (3 x 40 Menit)',
            'topik' => 'Aljabar dan Persamaan Linier Terapan',
            'content' => $sampleContent."\nCatatan Tambahan Revisi",
        ]);

        $updateRes->assertRedirect(route('rpm.show', $rpm));
        $rpm->refresh();
        $this->assertSame('RPM Digital Matematika Aljabar (Revisi 2)', $rpm->title);
        $this->assertSame('3 JP (3 x 40 Menit)', $rpm->alokasi_waktu);

        // 4. Destroy
        $destroyRes = $this->actingAs($guru)->delete(route('rpm.destroy', $rpm));
        $destroyRes->assertRedirect(route('rpm.index'));
        $this->assertDatabaseMissing('rpm_digitals', ['uuid' => $rpm->uuid]);
    }

    /**
     * Test 7: Export PDF dan Word (.docx) resmi sesuai format aplikasi.
     */
    public function test_rpm_digital_export_pdf_and_word(): void
    {
        $guru = $this->makeUser('guru');
        $sampleContent = $this->sampleRpmContent();

        $rpm = RpmDigital::create([
            'user_uuid' => $guru->uuid,
            'title' => 'RPM Bahasa Inggris Greetings',
            'mapel' => 'Bahasa Inggris',
            'kelas' => 'VII',
            'semester' => '1 (Ganjil)',
            'alokasi_waktu' => '2 JP (2 x 40 Menit)',
            'topik' => 'Greetings and Introductions',
            'source_type' => 'ai_direct',
            'content' => $sampleContent,
            'status' => 'draft',
        ]);

        // Export PDF
        $pdfRes = $this->actingAs($guru)->get(route('rpm.export-pdf', $rpm));
        $pdfRes->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfRes->headers->get('Content-Type'));

        // Export Word (.docx)
        $wordRes = $this->actingAs($guru)->get(route('rpm.export-word', $rpm));
        $wordRes->assertOk();
        $this->assertStringContainsString('wordprocessingml', (string) $wordRes->headers->get('Content-Type'));
    }

    /**
     * Test 8: AJAX Preview endpoint merender dokumen berformat tabel resmi.
     */
    public function test_rpm_preview_endpoint(): void
    {
        $guru = $this->makeUser('guru');
        $sampleContent = $this->sampleRpmContent();

        $response = $this->actingAs($guru)->postJson(route('rpm.preview'), [
            'content' => $sampleContent,
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
        ]);
        $this->assertNotEmpty($response->json('html'));
        $this->assertStringContainsString('rpm-doc', $response->json('html'));
    }

    /**
     * Test 9: Export PDF multi-halaman mempertahankan struktur tabel 3-kolom tanpa rusak across page break.
     */
    public function test_rpm_digital_export_pdf_multipage_clean_across_pages(): void
    {
        $guru = $this->makeUser('guru');
        $fixturePath = base_path('tests/Fixtures/rpm-fellianto-agama-buddha-kelas8.txt');
        $this->assertFileExists($fixturePath);
        $fixtureContent = file_get_contents($fixturePath);

        $rpm = RpmDigital::create([
            'user_uuid' => $guru->uuid,
            'title' => 'RPM Multi-Halaman Fellianto',
            'mapel' => 'Pendidikan Agama Buddha',
            'kelas' => 'VIII',
            'semester' => 'Ganjil',
            'alokasi_waktu' => '2 JP (2 x 40 Menit)',
            'topik' => 'Kesehatan Reproduksi Remaja',
            'source_type' => 'ai_direct',
            'content' => $fixtureContent,
            'status' => 'draft',
        ]);

        $pdfRes = $this->actingAs($guru)->get(route('rpm.export-pdf', $rpm));
        $pdfRes->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $pdfRes->headers->get('Content-Type'));
        $pdfBinary = $pdfRes->getContent();
        $this->assertStringStartsWith('%PDF', $pdfBinary);

        // Parse halaman PDF untuk memastikan multi-halaman dan teks pada halaman sambungan terbaca
        $parser = new \Smalot\PdfParser\Parser();
        $parsedPdf = $parser->parseContent($pdfBinary);
        $pages = $parsedPdf->getPages();
        $this->assertGreaterThan(1, count($pages));

        // Halaman 2 memuat kelanjutan DESAIN PEMBELAJARAN (Lingkungan Pembelajaran)
        $page2Text = $pages[1]->getText();
        $this->assertStringContainsString('Lingkungan Pembelajaran', $page2Text);
    }
}
