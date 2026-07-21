<?php

namespace App\Console\Commands;

use App\Models\Siswa;
use App\Services\ClassroomService;
use Illuminate\Console\Command;

/**
 * Perbaikan data: daftarkan siswa yg id_kelas-nya sudah terisi tapi belum jadi
 * classroom_members utk ruang kelas yg sudah ada di kelas itu (mis. siswa dimasukkan ke
 * kelas SETELAH ruang kelasnya dibuat — lihat catatan di ClassroomService::
 * enrollStudentInKelasClassrooms()). Aman dijalankan berkali-kali (idempotent, firstOrCreate).
 */
class ClassroomRepairMembership extends Command
{
    protected $signature = 'classroom:repair-membership';
    protected $description = 'Daftarkan ulang siswa yang belum jadi anggota ruang kelas yang sudah ada utk kelasnya';

    public function handle(ClassroomService $service): int
    {
        $siswas = Siswa::whereNotNull('id_kelas')->whereNotNull('id_login')->get();

        $this->info("Memeriksa {$siswas->count()} siswa...");
        $bar = $this->output->createProgressBar($siswas->count());
        foreach ($siswas as $siswa) {
            $service->enrollStudentInKelasClassrooms($siswa);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info('Selesai.');

        return self::SUCCESS;
    }
}
