<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Symfony\Component\Process\Process;

/**
 * Service reusable kompresi/optimasi file SEBELUM disimpan permanen.
 * Dipakai BAIK oleh materi guru MAUPUN submission siswa → aturan identik.
 *
 * - Gambar: intervention/image (GD). Resize bila > maks, konversi WebP q80
 *   (fallback JPEG), strip metadata via re-encode.
 * - PDF: Ghostscript (-dPDFSETTINGS=/ebook) via Symfony Process. Bila gs tidak
 *   tersedia → fallback simpan asli + catat warning (default tetap mencoba kompres).
 *
 * Semua file disimpan di disk 'public' (storage/app/public + symlink).
 */
class FileCompressionService
{
    private const MAX_WIDTH = 1600;
    private const IMAGE_QUALITY = 80;

    /** Auto-deteksi mime → handleImage()/handlePdf(). @return array metadata file. */
    public function handle(UploadedFile $file, string $subdir): array
    {
        $mime = $file->getMimeType();

        if (str_starts_with((string) $mime, 'image/')) {
            return $this->handleImage($file, $subdir);
        }
        if ($mime === 'application/pdf') {
            return $this->handlePdf($file, $subdir);
        }

        // Tipe lain tidak diizinkan (divalidasi juga di FormRequest).
        throw new \InvalidArgumentException("Tipe file tidak didukung: {$mime}");
    }

    public function handleImage(UploadedFile $file, string $subdir): array
    {
        $sizeOriginal = $file->getSize();
        $this->ensureDir($subdir);

        $manager = new ImageManager(new Driver());
        $image = $manager->read($file->getRealPath());

        // Resize hanya jika lebih lebar dari maksimum (jaga rasio).
        if ($image->width() > self::MAX_WIDTH) {
            $image->scaleDown(width: self::MAX_WIDTH);
        }

        // Coba WebP; fallback JPEG bila driver tak mendukung.
        try {
            $encoded = (string) $image->toWebp(self::IMAGE_QUALITY);
            $ext = 'webp';
            $outMime = 'image/webp';
        } catch (\Throwable $e) {
            $encoded = (string) $image->toJpeg(self::IMAGE_QUALITY);
            $ext = 'jpg';
            $outMime = 'image/jpeg';
        }

        $storedName = Str::uuid() . '.' . $ext;
        $rel = $subdir . '/' . $storedName;
        Storage::disk('public')->put($rel, $encoded);

        return $this->meta($file, $storedName, $rel, $outMime, $sizeOriginal, strlen($encoded));
    }

    public function handlePdf(UploadedFile $file, string $subdir): array
    {
        $sizeOriginal = $file->getSize();
        $this->ensureDir($subdir);

        $storedName = Str::uuid() . '.pdf';
        $rel = $subdir . '/' . $storedName;
        $dest = Storage::disk('public')->path($rel);

        $gs = $this->ghostscriptBinary();
        $compressed = false;

        if ($gs) {
            try {
                $process = new Process([
                    $gs, '-sDEVICE=pdfwrite', '-dCompatibilityLevel=1.4',
                    '-dPDFSETTINGS=/ebook', '-dNOPAUSE', '-dQUIET', '-dBATCH',
                    '-sOutputFile=' . $dest, $file->getRealPath(),
                ]);
                $process->setTimeout(120);
                $process->run();
                $compressed = $process->isSuccessful() && is_file($dest) && filesize($dest) > 0;
            } catch (\Throwable $e) {
                Log::warning('Ghostscript gagal mengompres PDF: ' . $e->getMessage());
            }
        }

        // Fallback aman: simpan PDF asli apa adanya.
        if (!$compressed) {
            Log::warning('Ghostscript tidak tersedia / gagal — menyimpan PDF asli tanpa kompresi.');
            Storage::disk('public')->put($rel, file_get_contents($file->getRealPath()));
        }

        $sizeCompressed = Storage::disk('public')->size($rel);

        return $this->meta($file, $storedName, $rel, 'application/pdf', $sizeOriginal, $sizeCompressed);
    }

    private function meta(UploadedFile $file, string $storedName, string $rel, string $mime, int $sizeOriginal, int $sizeCompressed): array
    {
        return [
            'original_name'   => $file->getClientOriginalName(),
            'stored_name'     => $storedName,
            'path'            => $rel,                 // relatif terhadap disk 'public'
            'mime'            => $mime,
            'size_original'   => $sizeOriginal,
            'size_compressed' => $sizeCompressed,
        ];
    }

    private function ensureDir(string $subdir): void
    {
        if (!Storage::disk('public')->exists($subdir)) {
            Storage::disk('public')->makeDirectory($subdir);
        }
    }

    /** Cari binary Ghostscript (lintas OS). Bisa di-override via config('classroom.gs_bin'). */
    private function ghostscriptBinary(): ?string
    {
        $configured = config('classroom.gs_bin');
        if ($configured && @is_executable($configured)) {
            return $configured;
        }
        foreach (['gs', 'gswin64c', 'gswin32c'] as $bin) {
            $which = PHP_OS_FAMILY === 'Windows' ? "where {$bin}" : "command -v {$bin}";
            $out = @shell_exec($which . ' 2>nul');
            if ($out && trim($out) !== '') {
                return trim(strtok($out, "\r\n"));
            }
        }
        return null;
    }
}
