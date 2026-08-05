<?php

namespace App\Console\Commands;

use App\Models\Jadwal;
use App\Notifications\JadwalSesiReminderNotification;
use App\Services\Jadwal\JadwalReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/*
| Pengingat per-sesi: kirim ~10 menit sebelum tiap jam mengajar dimulai.
| Dijalankan tiap 5 menit oleh scheduler (routes/console.php).
|
| Window (now+10, now+15] cocok dgn cadence everyFiveMinutes: tiap sesi jatuh
| tepat di satu run. `now` di-floor ke awal menit supaya jitter detik start cron
| tidak membuat sesi lolos dari celah band. Guard idempoten (type + data->sesi_key)
| mencegah dobel bila window sempat tumpang-tindih / cron restart.
|
| Jam berurutan utk kelas+mapel yg sama (blok double-period) hanya diingatkan
| SEKALI, saat jam pertamanya — sesi lanjutan (yg jam mulainya = jam selesai sesi
| sebelumnya) dilewati agar guru tak dinotif "sebentar lagi mengajar" saat sedang
| mengajar kelas yg sama. Sesi mapel sama yg TIDAK berurutan (ada jeda) tetap
| diingatkan sendiri-sendiri.
*/
class JadwalSesiReminder extends Command
{
    protected $signature = 'jadwal:sesi-reminder';

    protected $description = 'Kirim pengingat 10 menit sebelum tiap sesi mengajar (in-app + FCM).';

    private const LEAD_MENIT = 10;

    private const BAND_MENIT = 5;

    public function handle(JadwalReminderService $svc): int
    {
        $now = Carbon::now()->startOfMinute();
        $tanggal = $now->toDateString();
        $bandBawah = $now->copy()->addMinutes(self::LEAD_MENIT);          // inklusif
        $bandAtas = $bandBawah->copy()->addMinutes(self::BAND_MENIT);     // eksklusif

        $sesiHariIni = $svc->sesiHariIni($now);

        // Himpunan "jam selesai" per (guru|kelas|mapel). Sebuah sesi yg jam mulainya
        // = salah satu jam selesai grup yg sama adalah lanjutan blok berurutan → di-skip.
        $jamSelesaiBlok = $sesiHariIni
            ->map(fn (Jadwal $r) => $r->id_guru.'|'.$r->id_kelas.'|'.$r->id_pelajaran.'|'.(string) ($r->jam_selesai ?: $r->jam?->jam_selesai))
            ->flip();

        $terkirim = 0;
        foreach ($sesiHariIni as $sesi) {
            $waktu = $svc->waktuMulai($sesi, $now);
            if (! $waktu || $waktu->lt($bandBawah) || $waktu->gte($bandAtas)) {
                continue;
            }

            $mulaiRaw = (string) ($sesi->jam_mulai ?: $sesi->jam?->jam_mulai);
            if ($jamSelesaiBlok->has($sesi->id_guru.'|'.$sesi->id_kelas.'|'.$sesi->id_pelajaran.'|'.$mulaiRaw)) {
                continue; // sesi lanjutan blok berurutan — sudah diingatkan di jam pertama
            }

            $user = $sesi->guru?->user;
            if (! $user) {
                continue;
            }

            $sesiKey = $sesi->uuid.':'.$tanggal;
            $sudahAda = $user->notifications()
                ->where('type', JadwalSesiReminderNotification::class)
                ->where('data->sesi_key', $sesiKey)
                ->exists();
            if ($sudahAda) {
                continue;
            }

            $user->notify(new JadwalSesiReminderNotification(
                $sesiKey,
                $sesi->pelajaran?->nama ?? 'Pelajaran',
                $sesi->kelas?->nama_lengkap ?? 'Kelas',
                $svc->jamMulai($sesi) ?? $waktu->format('H:i'),
            ));
            $terkirim++;
        }

        $this->info("Selesai. {$terkirim} pengingat sesi dikirim.");

        return self::SUCCESS;
    }
}
