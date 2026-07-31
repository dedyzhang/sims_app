<?php

namespace Database\Seeders;

use App\Models\Agenda;
use App\Models\PenugasanPengganti;
use App\Models\TugasKelas;
use App\Models\User;
use Illuminate\Database\Seeder;

/*
|==========================================================================
| TugasKelasSeeder — IDEMPOTEN (firstOrCreate). Aman dijalankan ulang.
|==========================================================================
| Bergantung pada data penugasan_pengganti (mis. dari PenugasanPenggantiSeeder
| atau RekapPiketHistorisSeeder). Buat contoh tugas_kelas untuk sebagian slot
| yang statusnya sudah 'ditugaskan'/'selesai', termasuk entri agenda terkait,
| supaya rekap & agenda demo punya data yang konsisten.
| Jalankan manual: php artisan db:seed --class=Database\\Seeders\\TugasKelasSeeder
*/
class TugasKelasSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('access', 'admin')->orWhere('access', 'superadmin')->first();
        if (! $admin) {
            $this->command->warn('Tidak ada user admin — jalankan seeder user dulu.');

            return;
        }

        $slots = PenugasanPengganti::with(['guruTidakHadir', 'jadwal'])
            ->whereIn('status', ['ditugaskan', 'selesai'])
            ->whereDoesntHave('tugasKelas')
            ->limit(5)
            ->get();

        if ($slots->isEmpty()) {
            $this->command->warn('Tidak ada slot penugasan_pengganti berstatus ditugaskan/selesai — jalankan PenugasanPenggantiSeeder atau RekapPiketHistorisSeeder dulu.');

            return;
        }

        $dibuat = 0;
        foreach ($slots as $i => $slot) {
            $jenis = $i % 2 === 0 ? 'titip_manual_piket' : 'upload_guru_asli';

            $tugas = TugasKelas::firstOrCreate(
                ['id_penugasan_pengganti' => $slot->uuid],
                [
                    'jenis' => $jenis,
                    'judul' => 'Latihan Mandiri (contoh seeder)',
                    'deskripsi' => 'Baca materi terkait di buku paket, kerjakan latihan soal di halaman berikutnya.',
                    'dibuat_oleh' => $admin->uuid,
                ]
            );

            if (! $tugas->wasRecentlyCreated) {
                continue;
            }
            $dibuat++;

            if ($tugas->id_agenda === null && $slot->guruTidakHadir) {
                $agenda = Agenda::create([
                    'tanggal' => $slot->guruTidakHadir->tanggal,
                    'id_jadwal' => $slot->jadwal?->uuid,
                    'id_guru' => $slot->guruTidakHadir->id_guru,
                    'id_kelas' => $slot->jadwal?->id_kelas,
                    'id_pelajaran' => $slot->jadwal?->id_pelajaran,
                    'pembahasan' => $tugas->judul,
                    'kegiatan' => $tugas->deskripsi,
                    'proses' => 'selesai',
                    'kendala' => 'Data contoh seeder — '.($jenis === 'upload_guru_asli' ? 'dari guru asli.' : 'titipan guru piket.'),
                    'semester' => now()->month >= 7 ? 1 : 2,
                ]);
                $tugas->update(['id_agenda' => $agenda->uuid]);
            }
        }

        $this->command->info("TugasKelasSeeder selesai. {$dibuat} tugas kelas contoh dibuat.");
    }
}
