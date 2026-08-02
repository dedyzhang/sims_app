<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\JadwalPiket;
use Illuminate\Database\Seeder;

/*
|==========================================================================
| PiketSeeder — IDEMPOTEN (firstOrCreate). Aman dijalankan ulang.
|==========================================================================
 | Data contoh jadwal piket guru: satu ketua untuk setiap hari kerja
 | Senin-Jumat dari guru yang sudah ada.
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
        foreach (range(1, 5) as $hari) {
            $idKetua = $guruList[($hari - 1) % $guruList->count()];
            $slot = JadwalPiket::firstOrCreate(
                ['id_guru' => $idKetua, 'hari' => $hari],
                ['is_ketua' => false]
            );
            if ($slot->wasRecentlyCreated) {
                $dibuat++;
            }

            JadwalPiket::where('hari', $hari)->update(['is_ketua' => false]);
            $slot->update(['is_ketua' => true]);
        }

        $this->command->info("PiketSeeder selesai. {$dibuat} slot rotasi baru dibuat.");
    }
}
