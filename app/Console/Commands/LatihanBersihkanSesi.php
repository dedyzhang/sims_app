<?php

namespace App\Console\Commands;

use App\Models\GamePracticeSession;
use Illuminate\Console\Command;

/**
 * Sesi "Latihan" Arena Belajar & seluruh data turunannya (peserta/attempt/jawaban tamu,
 * lewat cascadeOnDelete) tak punya nilai retensi jangka panjang — disposable by design.
 * Bersihkan sesi yg dibuat >48 jam lalu, satu bulk DELETE (bukan loop per baris).
 */
class LatihanBersihkanSesi extends Command
{
    protected $signature = 'latihan:bersihkan-sesi';

    protected $description = 'Hapus sesi latihan Arena Belajar (+ peserta/skor/jawaban tamu) yang dibuat lebih dari 48 jam lalu';

    public function handle(): int
    {
        $count = GamePracticeSession::where('created_at', '<', now()->subHours(48))->delete();

        $this->info("Sesi latihan dihapus: {$count}");

        return self::SUCCESS;
    }
}
