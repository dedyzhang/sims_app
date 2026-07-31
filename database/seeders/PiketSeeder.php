<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\JadwalPiket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/*
|==========================================================================
| PiketSeeder — IDEMPOTEN (firstOrCreate). Aman dijalankan ulang.
|==========================================================================
| Data contoh rotasi piket guru: hari kerja (Senin-Jumat) sebulan ke belakang
| s.d. sebulan ke depan, guru dirotasi bergiliran dari guru yang sudah ada.
| Jalankan manual: php artisan db:seed --class=Database\\Seeders\\PiketSeeder
*/
class PiketSeeder extends Seeder
{
    public function run(): void
    {
        $guruList = Guru::orderBy('nama')->pluck('uuid');

        if ($guruList->isEmpty()) {
            $this->command->warn('Tidak ada data guru — jalankan seeder guru dulu sebelum PiketSeeder.');

            return;
        }

        $dibuat = 0;
        $i = 0;
        for ($tgl = Carbon::now()->subMonth()->startOfWeek(Carbon::MONDAY);
             $tgl <= Carbon::now()->addMonth()->endOfWeek(Carbon::SUNDAY);
             $tgl->addDay()) {
            if ($tgl->dayOfWeekIso >= 6) {
                continue; // lewati Sabtu & Minggu
            }

            $slot = JadwalPiket::firstOrCreate(
                ['tanggal' => $tgl->toDateString()],
                ['id_guru' => $guruList[$i % $guruList->count()], 'status' => 'aktif']
            );
            if ($slot->wasRecentlyCreated) {
                $dibuat++;
            }
            $i++;
        }

        $this->command->info("PiketSeeder selesai. {$dibuat} slot rotasi baru dibuat.");
    }
}
