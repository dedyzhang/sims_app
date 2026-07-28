<?php

namespace Tests\Unit;

use App\Services\FileCompressionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Bug nyata dilaporkan user: upload dokumen PDF di Ruang Kelas gagal 500 di hosting mereka
 * — "Call to undefined function App\Services\shell_exec()". Root cause: banyak shared hosting
 * mematikan shell_exec() lewat disable_functions php.ini demi keamanan; memanggilnya jadi ERROR
 * FATAL (bukan sekadar warning yg bisa diredam `@`), krn PHP 8 melempar \Error utk fungsi yg
 * benar2 tak ada (beda dari command yg cuma gagal dijalankan). Fix: ghostscriptBinary() cek
 * function_exists('shell_exec') dulu — kalau tak ada, langsung anggap Ghostscript tak tersedia
 * & pakai fallback simpan PDF asli (SUDAH ADA di handlePdf()), bukan bikin upload gagal total.
 */
class FileCompressionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function fakePdf(): UploadedFile
    {
        // create() cuma memalsukan METADATA ukuran (getSize()) tanpa menulis byte sungguhan ke
        // file sementaranya (filesize() aslinya tetap 0) — pakai createWithContent() supaya file
        // fisiknya benar2 berisi, krn tes ini memverifikasi ISI yg TERSIMPAN (size_compressed).
        return UploadedFile::fake()->createWithContent('materi.pdf', str_repeat('%PDF-1.4 dummy ', 500));
    }

    public function test_handle_pdf_tetap_berhasil_simpan_asli_saat_ghostscript_tak_ditemukan(): void
    {
        // Simulasikan "Ghostscript tak tersedia" via config path yg sengaja tak ada — mewakili
        // hasil akhir yg SAMA dgn skenario shell_exec() dimatikan hosting: ghostscriptBinary()
        // balikan null, handlePdf() fallback simpan PDF asli tanpa kompresi, TIDAK error 500.
        config(['classroom.gs_bin' => '/path/tidak/ada/gs-fiktif']);

        $service = new FileCompressionService();
        $meta = $service->handlePdf($this->fakePdf(), 'classroom/materi');

        $this->assertSame('application/pdf', $meta['mime']);
        $this->assertGreaterThan(0, $meta['size_compressed']);
        Storage::disk('public')->assertExists($meta['path']);
    }

    /** Kunci perbaikan ini via inspeksi source — memverifikasi PHP tak bisa "undefine" fungsi
     *  built-in di tengah proses tes utk mensimulasikan disable_functions hosting scr langsung,
     *  jadi guard function_exists('shell_exec') dipastikan ADA lewat pembacaan kode sumbernya. */
    public function test_ghostscript_binary_dijaga_function_exists_shell_exec(): void
    {
        $source = file_get_contents(app_path('Services/FileCompressionService.php'));

        $this->assertStringContainsString("if (!function_exists('shell_exec')) {", $source);
        $this->assertStringContainsString('return null;', $source);
    }
}
