<?php

namespace App\Sarpras\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;
use RuntimeException;

/*
|==========================================================================
| FotoCompressor — kompresi SEMUA foto upload hingga MAKSIMAL 2MB.
|==========================================================================
| Dipakai di SEMUA titik upload (kerusakan, ruangan, denah, dokumentasi
| pengadaan, berita acara, dll). JANGAN pernah menyimpan file mentah.
|
| Alur:
|   1. Baca gambar via Intervention Image v3 (driver GD).
|   2. scaleDown ke sisi terpanjang maks 1920px (pertahankan rasio, tak upscale).
|   3. Encode WEBP (default) kualitas awal 85 (JPEG opsional untuk cetak label).
|   4. LOOP turunkan kualitas 85->75->65->55->45->35 sampai <=2MB.
|      Bila di kualitas 35 masih >2MB, kecilkan dimensi x0.8 lalu ulang.
|      Ada BATAS BAWAH + stop paksa supaya TIDAK infinite loop.
|   5. Simpan via Storage disk('public'), return PATH RELATIF.
*/
class FotoCompressor
{
    /** Batas akhir ukuran file: 2MB = 2.097.152 byte. */
    public const MAX_BYTES = 2097152;

    /** Sisi terpanjang maksimum (px). */
    public const MAX_DIMENSION = 1920;

    /** Tahapan kualitas yang dicoba berurutan. */
    private const QUALITY_STEPS = [85, 75, 65, 55, 45, 35];

    /** Dimensi minimum aman — stop paksa supaya tidak loop selamanya. */
    private const MIN_DIMENSION = 320;

    /**
     * Kompres & simpan satu foto. Return path relatif (disk public).
     *
     * @param  string  $format  'webp' (default) atau 'jpeg' (untuk cetak label).
     * @throws RuntimeException bila gagal memproses gambar.
     */
    public function compress(UploadedFile $file, string $dir, string $format = 'webp'): string
    {
        return $this->prosesDanSimpan($file->getRealPath(), $dir, $format);
    }

    /**
     * Kompres & simpan gambar dari BINARY STRING (mis. hasil kanvas base64).
     * Return path relatif (disk public).
     */
    public function compressString(string $binary, string $dir, string $format = 'webp'): string
    {
        return $this->prosesDanSimpan($binary, $dir, $format);
    }

    /**
     * Inti kompresi: baca sumber (path file ATAU binary string), scaleDown,
     * turunkan kualitas sampai <=2MB, lalu simpan. Return path relatif.
     *
     * @param  string  $sumber  Path file atau binary string gambar.
     */
    private function prosesDanSimpan(string $sumber, string $dir, string $format): string
    {
        $format = strtolower($format) === 'jpeg' || strtolower($format) === 'jpg' ? 'jpeg' : 'webp';

        try {
            $manager = new ImageManager(new GdDriver());
            $image = $manager->read($sumber); // Intervention v3 menerima path maupun binary string.
        } catch (\Throwable $e) {
            // Gambar korup / format tak terbaca.
            throw new RuntimeException('Gagal membaca gambar. Pastikan file berupa foto yang valid.', 0, $e);
        }

        // Dimensi awal (untuk penyusutan bertahap bila perlu).
        $targetMax = self::MAX_DIMENSION;

        $binary = null;

        // Loop luar: bila kualitas terendah pun masih >2MB, kecilkan dimensi lalu ulang.
        while (true) {
            // scaleDown: tidak meng-upscale, mempertahankan rasio.
            $work = clone $image;
            $work->scaleDown(width: $targetMax, height: $targetMax);

            // Loop dalam: turunkan kualitas bertahap.
            foreach (self::QUALITY_STEPS as $quality) {
                $encoded = $format === 'jpeg'
                    ? $work->toJpeg(quality: $quality)
                    : $work->toWebp(quality: $quality);

                $binary = (string) $encoded;

                if (strlen($binary) <= self::MAX_BYTES) {
                    // Sudah <=2MB — selesai.
                    return $this->simpan($binary, $dir, $format);
                }
            }

            // Masih >2MB di kualitas terendah: susutkan dimensi x0.8.
            $targetMax = (int) floor($targetMax * 0.8);

            // STOP PAKSA: bila sudah terlalu kecil, terima hasil terakhir (kualitas 35).
            if ($targetMax < self::MIN_DIMENSION) {
                if ($binary === null) {
                    throw new RuntimeException('Gagal mengompres foto.');
                }

                return $this->simpan($binary, $dir, $format);
            }
        }
    }

    /** Simpan binary ke disk public dengan nama hash unik, return path relatif. */
    private function simpan(string $binary, string $dir, string $format): string
    {
        $ext = $format === 'jpeg' ? 'jpg' : 'webp';
        $dir = trim($dir, '/');
        // Nama file hash unik supaya tidak bentrok & tidak bocor nama asli.
        $path = $dir . '/' . Str::uuid()->toString() . '.' . $ext;

        // Tulis ke disk('public'). PATH RELATIF yang dikembalikan.
        Storage::disk('public')->put($path, $binary);

        return $path;
    }

    /**
     * Helper: kompres banyak file sekaligus. Return array path relatif.
     *
     * @param  array<int, UploadedFile>  $files
     * @return array<int, string>
     */
    public function compressMany(array $files, string $dir, string $format = 'webp'): array
    {
        $paths = [];
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $paths[] = $this->compress($file, $dir, $format);
            }
        }

        return $paths;
    }

    /** Hapus file dari disk public bila ada (untuk rollback / pembersihan). */
    public function hapus(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
