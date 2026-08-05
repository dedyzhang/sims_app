<?php

namespace App\Console\Commands;

use App\Models\Jadwal;
use App\Notifications\JadwalDigestHarianNotification;
use App\Services\Jadwal\JadwalReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/*
| Digest pagi: kirim satu ringkasan jadwal mengajar hari ini ke tiap guru.
| Dijalankan harian oleh scheduler (routes/console.php). Idempoten: satu digest
| per guru per hari (guard whereJsonContains data->tanggal).
*/
class JadwalDigestHarian extends Command
{
    protected $signature = 'jadwal:digest-harian';

    protected $description = 'Kirim digest jadwal mengajar hari ini ke tiap guru (in-app + FCM).';

    public function handle(JadwalReminderService $svc): int
    {
        $hari = Carbon::now();
        $tanggal = $hari->toDateString();

        $sesi = $svc->sesiHariIni($hari);
        if ($sesi->isEmpty()) {
            $this->info('Tidak ada sesi mengajar hari ini.');

            return self::SUCCESS;
        }

        $terkirim = 0;
        foreach ($sesi->groupBy('id_guru') as $items) {
            $user = $items->first()->guru?->user;
            if (! $user) {
                continue;
            }

            $sudahAda = $user->notifications()
                ->where('type', JadwalDigestHarianNotification::class)
                ->where('data->tanggal', $tanggal)
                ->exists();
            if ($sudahAda) {
                continue;
            }

            $payload = $items->map(fn (Jadwal $s) => [
                'jam_ke' => $s->jam_ke,
                'jam' => $svc->jamMulai($s) ?? '--:--',
                'pelajaran' => $s->pelajaran?->nama ?? 'Pelajaran',
                'kelas' => $s->kelas?->nama_lengkap ?? 'Kelas',
            ])->values()->all();

            $user->notify(new JadwalDigestHarianNotification($payload, $tanggal));
            $terkirim++;
        }

        $this->info("Selesai. {$terkirim} digest dikirim.");

        return self::SUCCESS;
    }
}
