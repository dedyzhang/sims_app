<?php

namespace App\Console\Commands;

use App\Services\Keuangan\BendaharaAntrianDigest;
use Illuminate\Console\Command;

class BendaharaAntrianDigestCommand extends Command
{
    protected $signature = 'bendahara:antrian-digest';

    protected $description = 'Kirim notifikasi digest antrian verifikasi SPP menumpuk ke bendahara';

    public function handle(BendaharaAntrianDigest $digest): int
    {
        $ringkasan = $digest->ringkasan();

        if (! $ringkasan['menumpuk']) {
            $this->info('Antrian belum melewati ambang — tidak ada notifikasi.');

            return self::SUCCESS;
        }

        $n = $digest->kirimDigest();
        $this->info("Digest terkirim ke {$n} bendahara ({$ringkasan['menunggu']} menunggu, {$ringkasan['terverifikasi']} validasi bank).");

        return self::SUCCESS;
    }
}
