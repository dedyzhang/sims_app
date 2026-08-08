<?php

namespace App\Console\Commands;

use App\Models\JadwalPiket;
use App\Notifications\PiketH1Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/*
| Pengingat H-1 untuk guru yang dijadwalkan piket besok (skema rotasi per hari ISO).
| Dijalankan harian oleh scheduler (routes/console.php).
*/
class PiketH1Reminder extends Command
{
    protected $signature = 'piket:h1-reminder';

    protected $description = 'Kirim pengingat in-app H-1 ke guru yang dijadwalkan piket besok.';

    public function handle(): int
    {
        $besok = Carbon::tomorrow();
        $hariBesok = $besok->dayOfWeekIso;

        if ($hariBesok > 5) {
            $this->info('Besok akhir pekan — tidak ada jadwal piket sekolah.');

            return self::SUCCESS;
        }

        $tanggalBesok = $besok->toDateString();

        $jadwalBesok = JadwalPiket::with('guru.user')
            ->where('hari', $hariBesok)
            ->get();

        if ($jadwalBesok->isEmpty()) {
            $this->info('Tidak ada guru piket terjadwal besok.');

            return self::SUCCESS;
        }

        $terkirim = 0;
        foreach ($jadwalBesok as $jadwal) {
            $user = $jadwal->guru?->user;
            if (! $user) {
                continue;
            }

            $sudahAda = $user->notifications()
                ->where('type', PiketH1Notification::class)
                ->whereJsonContains('data->jadwal_piket_id', $jadwal->uuid)
                ->whereJsonContains('data->tanggal_piket', $tanggalBesok)
                ->exists();
            if ($sudahAda) {
                continue;
            }

            $user->notify(new PiketH1Notification($jadwal, $tanggalBesok));
            $terkirim++;
        }

        $this->info("Selesai. {$terkirim} pengingat dikirim.");

        return self::SUCCESS;
    }
}
