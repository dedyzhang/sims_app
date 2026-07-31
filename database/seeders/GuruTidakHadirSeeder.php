<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\GuruTidakHadir;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/*
|==========================================================================
| GuruTidakHadirSeeder — IDEMPOTEN (firstOrCreate). Aman dijalankan ulang.
|==========================================================================
| Data contoh guru tidak hadir campuran manual & otomatis, 5 hari kerja
| terakhir, untuk testing daftar + panel jam kosong.
| Jalankan manual: php artisan db:seed --class=Database\\Seeders\\GuruTidakHadirSeeder
*/
class GuruTidakHadirSeeder extends Seeder
{
    public function run(): void
    {
        $guruList = Guru::inRandomOrder()->limit(3)->pluck('uuid');

        if ($guruList->isEmpty()) {
            $this->command->warn('Tidak ada data guru — jalankan seeder guru dulu.');

            return;
        }

        $alasanList = ['sakit', 'izin', 'dinas_luar'];
        $dibuat = 0;
        $tgl = Carbon::today();
        $hari = 0;

        while ($hari < 3) {
            if ($tgl->dayOfWeekIso < 6) {
                foreach ($guruList as $i => $idGuru) {
                    $entri = GuruTidakHadir::firstOrCreate(
                        ['id_guru' => $idGuru, 'tanggal' => $tgl->toDateString()],
                        [
                            'sumber' => 'manual_piket',
                            'alasan' => $alasanList[$i % count($alasanList)],
                            'keterangan' => 'Data contoh seeder',
                        ]
                    );
                    if ($entri->wasRecentlyCreated) {
                        $dibuat++;
                    }
                }
                $hari++;
            }
            $tgl->subDay();
        }

        $this->command->info("GuruTidakHadirSeeder selesai. {$dibuat} entri baru dibuat.");
    }
}
