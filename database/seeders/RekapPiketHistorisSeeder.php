<?php

namespace Database\Seeders;

use App\Models\Guru;
use App\Models\Jadwal;
use App\Models\GuruTidakHadir;
use App\Models\PenugasanPengganti;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RekapPiketHistorisSeeder extends Seeder
{
    public function run(): void
    {
        $guruList = Guru::orderBy('nama')->pluck('uuid');
        
        if ($guruList->count() < 2) {
            $this->command->warn('Butuh minimal 2 guru untuk membuat historis piket.');
            return;
        }

        $dibuat = 0;
        
        // Buat data selama 2 bulan ke belakang
        for ($tgl = Carbon::now()->subMonths(2)->startOfWeek(Carbon::MONDAY);
             $tgl <= Carbon::now()->subDay();
             $tgl->addDay()) {
            
            if ($tgl->dayOfWeekIso >= 6) {
                continue; // lewati Sabtu & Minggu
            }
            
            // Randomly 1 atau 2 guru tidak hadir pada hari ini (chance 60%)
            if (rand(1, 100) > 60) {
                continue;
            }
            
            $numTidakHadir = rand(1, 2);
            $guruTidakHadir = $guruList->random($numTidakHadir);
            
            foreach ($guruTidakHadir as $idGuru) {
                $alasanList = ['sakit', 'izin', 'dinas_luar', 'alpa'];
                $alasan = $alasanList[array_rand($alasanList)];
                
                $gth = GuruTidakHadir::firstOrCreate([
                    'id_guru' => $idGuru,
                    'tanggal' => $tgl->toDateString(),
                ], [
                    'sumber' => 'manual_piket',
                    'alasan' => $alasan,
                ]);
                
                if ($gth->wasRecentlyCreated) {
                    $dibuat++;
                    
                    // Ambil jadwal pelajaran untuk guru ini di hari yang sesuai
                    $jadwals = Jadwal::where('id_guru', $idGuru)
                        ->where('hari', $tgl->dayOfWeekIso)
                        ->get();
                        
                    foreach ($jadwals as $jadwal) {
                        // Cari guru pengganti acak yang tidak sama dengan guru tidak hadir
                        $penggantiList = $guruList->reject(fn($id) => $id === $idGuru);
                        $idPengganti = null;
                        $idPiket = null;
                        
                        if (rand(1, 100) <= 80) { // 80% dapat pengganti
                            $idPengganti = $penggantiList->random();
                        } else {
                            $idPiket = $penggantiList->random(); // Piket yang ambil alih
                        }
                        
                        PenugasanPengganti::create([
                            'id_guru_tidak_hadir' => $gth->uuid,
                            'id_jadwal' => $jadwal->uuid,
                            'id_guru_pengganti' => $idPengganti,
                            'id_guru_piket' => $idPiket,
                            'status' => 'selesai'
                        ]);
                    }
                }
            }
        }

        $this->command->info("RekapPiketHistorisSeeder selesai. {$dibuat} catatan ketidakhadiran baru dibuat.");
    }
}
