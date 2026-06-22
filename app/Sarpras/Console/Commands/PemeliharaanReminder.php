<?php

namespace App\Sarpras\Console\Commands;

use App\Models\User;
use App\Sarpras\Models\JadwalPemeliharaan;
use App\Sarpras\Notifications\PemeliharaanJatuhTempo;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/*
| Command reminder jadwal pemeliharaan rutin.
| Dijalankan harian oleh scheduler (routes/console.php).
| Mencari jadwal aktif yang jatuh tempo (<= hari ini), memberi notifikasi
| ke pengelola Sarpras (access: superadmin/admin/sapras), lalu MEMAJUKAN
| tgl_berikutnya sesuai interval.
*/
class PemeliharaanReminder extends Command
{
    protected $signature = 'sarpras:pemeliharaan-reminder';

    protected $description = 'Kirim pengingat jadwal pemeliharaan yang jatuh tempo (Sarpras).';

    public function handle(): int
    {
        $hariIni = Carbon::today();

        $jatuhTempo = JadwalPemeliharaan::query()
            ->where('aktif', true)
            ->whereDate('tgl_berikutnya', '<=', $hariIni)
            ->with('aset:id,nama')
            ->get();

        if ($jatuhTempo->isEmpty()) {
            $this->info('Tidak ada jadwal pemeliharaan yang jatuh tempo.');

            return self::SUCCESS;
        }

        foreach ($jatuhTempo as $jadwal) {
            // Notifikasi ke pengelola Sarpras (SIMS: berbasis kolom access).
            $wakas = User::whereIn('access', ['superadmin', 'admin', 'sapras'])->get();

            foreach ($wakas as $waka) {
                $waka->notify(new PemeliharaanJatuhTempo($jadwal));
            }

            // Majukan tanggal berikutnya supaya tidak terus jatuh tempo.
            $jadwal->update([
                'tgl_terakhir' => $hariIni->toDateString(),
                'tgl_berikutnya' => $hariIni->copy()->addDays($jadwal->interval_hari)->toDateString(),
            ]);

            $this->line("Reminder: {$jadwal->nama}");
        }

        $this->info("Selesai. {$jatuhTempo->count()} jadwal diproses.");

        return self::SUCCESS;
    }
}
