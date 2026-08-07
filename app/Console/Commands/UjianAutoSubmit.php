<?php

namespace App\Console\Commands;

use App\Models\UjianAttempt;
use App\Services\UjianGrader;
use Illuminate\Console\Command;

/**
 * Jaring pengaman server-side: finalisasi paksa attempt ujian yang lewat
 * batas_waktu_pada tapi masih 'in_progress' (mis. tab/browser siswa mati mendadak
 * sebelum auto-submit client-side sempat jalan). Dijadwalkan tiap menit di
 * routes/console.php — deadline ujian adalah batas keras, bukan sekadar pengingat.
 */
class UjianAutoSubmit extends Command
{
    protected $signature = 'ujian:auto-submit';
    protected $description = 'Finalisasi paksa attempt ujian yang sudah lewat batas waktu tapi belum dikumpulkan';

    public function handle(UjianGrader $grader): int
    {
        // Termasuk yg dikunci=true — siswa terkunci yg lewat deadline tetap wajib
        // difinalisasi, jangan dibiarkan menggantung selamanya menunggu guru reset.
        $expired = UjianAttempt::where('status', UjianAttempt::STATUS_IN_PROGRESS)
            ->where('batas_waktu_pada', '<=', now())
            ->get();

        foreach ($expired as $attempt) {
            $grader->autoSubmitKarenaWaktuHabis($attempt);
        }

        $this->info("Auto-submit: {$expired->count()} attempt difinalisasi paksa.");
        return self::SUCCESS;
    }
}
