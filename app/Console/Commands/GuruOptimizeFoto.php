<?php

namespace App\Console\Commands;

use App\Models\Guru;
use App\Support\FotoKartu;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Kompres+resize ULANG foto guru yang sudah TERLANJUR diunggah mentah (sebelum FotoKartu::resize
 * dipasang di KartuGuruController::fotoStore()). Upload baru sudah otomatis dikompres — perintah
 * ini hanya utk merapikan data lama, supaya "Cetak Semua" Kartu ID Guru tidak lambat.
 */
class GuruOptimizeFoto extends Command
{
    protected $signature = 'kartu-guru:optimize-foto {--force : Proses ulang walau file sudah kecil}';

    protected $description = 'Kompres+resize foto guru yang sudah terlanjur diunggah mentah (mempercepat cetak massal Kartu ID Guru)';

    public function handle(): int
    {
        $gurus = Guru::whereNotNull('foto')->get();
        $this->info("Memeriksa {$gurus->count()} foto guru...");
        $bar = $this->output->createProgressBar($gurus->count());
        $diproses = 0;
        $dilewati = 0;
        $sebelum = 0;
        $sesudah = 0;

        foreach ($gurus as $guru) {
            $path = storage_path('app/public/'.$guru->foto);
            if (! is_file($path)) {
                $bar->advance();

                continue;
            }

            $before = filesize($path);
            // Sudah kecil (≤300KB) — lewati, hindari kompresi ulang berkali-kali tanpa perlu.
            if (! $this->option('force') && $before <= 300 * 1024) {
                $dilewati++;
                $bar->advance();

                continue;
            }

            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $preserveAlpha = in_array($ext, ['png', 'webp'], true);
            $out = FotoKartu::resize((string) file_get_contents($path), $preserveAlpha);
            Storage::disk('public')->put($guru->foto, $out);

            $sebelum += $before;
            $sesudah += strlen($out);
            $diproses++;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info(sprintf(
            'Selesai. %d foto dikompres (%s → %s), %d sudah kecil (dilewati).',
            $diproses, $this->fmtBytes($sebelum), $this->fmtBytes($sesudah), $dilewati
        ));

        return self::SUCCESS;
    }

    private function fmtBytes(int $bytes): string
    {
        return $bytes >= 1048576
            ? round($bytes / 1048576, 2).' MB'
            : round($bytes / 1024, 1).' KB';
    }
}
