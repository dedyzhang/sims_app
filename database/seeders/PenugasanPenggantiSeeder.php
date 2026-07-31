<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\GuruTidakHadir;
use App\Models\PenugasanPengganti;
use App\Services\Piket\JamKosongService;
use Illuminate\Database\Seeder;

/*
|==========================================================================
| PenugasanPenggantiSeeder — IDEMPOTEN (firstOrCreate). Aman dijalankan ulang.
|==========================================================================
| Bergantung pada GuruTidakHadirSeeder (butuh baris guru_tidak_hadir dulu).
| Buat baris penugasan_pengganti utk tiap jam kosong guru_tidak_hadir yang
| ada, lalu variasikan beberapa jadi ditugaskan/selesai untuk demo status.
| Jalankan manual: php artisan db:seed --class=Database\\Seeders\\PenugasanPenggantiSeeder
*/
class PenugasanPenggantiSeeder extends Seeder
{
    public function run(): void
    {
        $jamKosong = app(JamKosongService::class);
        $guruTidakHadir = GuruTidakHadir::all();

        if ($guruTidakHadir->isEmpty()) {
            $this->command->warn('Tidak ada data guru_tidak_hadir — jalankan GuruTidakHadirSeeder dulu.');

            return;
        }

        $dibuat = 0;
        $dibuatSlot = [];

        foreach ($guruTidakHadir as $g) {
            $slots = $jamKosong->untukGuru($g->id_guru, $g->tanggal->toDateString());

            foreach ($slots as $slot) {
                $penugasan = PenugasanPengganti::firstOrCreate(
                    ['id_guru_tidak_hadir' => $g->uuid, 'id_jadwal' => $slot->uuid],
                    ['status' => 'menunggu']
                );
                if ($penugasan->wasRecentlyCreated) {
                    $dibuat++;
                    $dibuatSlot[] = $penugasan;
                }
            }
        }

        // Variasikan status sebagian baris baru untuk demo (ditugaskan / selesai).
        $guruPengganti = Guru::inRandomOrder()->value('uuid');
        foreach (array_slice($dibuatSlot, 0, 2) as $i => $penugasan) {
            if (! $guruPengganti) {
                break;
            }
            $penugasan->update([
                'id_guru_pengganti' => $guruPengganti,
                'status' => $i === 0 ? 'selesai' : 'ditugaskan',
            ]);
        }

        $this->command->info("PenugasanPenggantiSeeder selesai. {$dibuat} slot penugasan baru dibuat.");
    }
}
