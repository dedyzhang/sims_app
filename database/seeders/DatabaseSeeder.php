<?php

namespace Database\Seeders;

use App\Models\Semester;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── SUPERADMIN ───────────────────────────────────────────────────────
        // Superadmin TIDAK tampil di UI. Password hanya muncul di terminal saat seeder.
        $superPassword = env('SUPERADMIN_PASSWORD', 'Sup3r@' . Str::random(8));

        User::updateOrCreate(
            ['username' => 'superadmin'],
            [
                'uuid'       => '00000000-0000-0000-0000-000000000001',
                'identifier' => null,
                'password'   => $superPassword,
                'access'     => 'superadmin',
            ]
        );

        $this->command->newLine();
        $this->command->line('<fg=yellow>╔══════════════════════════════════════════╗</>');
        $this->command->line('<fg=yellow>║  SUPERADMIN CREDENTIALS                  ║</>');
        $this->command->line('<fg=yellow>║  Username : superadmin                   ║</>');
        $this->command->line("<fg=yellow>║  Password : {$superPassword}</>  ");
        $this->command->line('<fg=yellow>╚══════════════════════════════════════════╝</>');
        $this->command->line('<fg=red>  ⚠ Simpan password ini! Tidak akan muncul lagi.</>');
        $this->command->newLine();

        // ─── ADMIN DEFAULT ────────────────────────────────────────────────────
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'identifier' => null,
                'password'   => 'admin123',
                'access'     => 'admin',
            ]
        );

        // ─── SEMESTER ─────────────────────────────────────────────────────────
        Semester::updateOrCreate(
            ['semester' => 1, 'tahun' => '2024/2025'],
            ['aktif' => true]
        );
        Semester::updateOrCreate(
            ['semester' => 2, 'tahun' => '2024/2025'],
            ['aktif' => false]
        );

        // ─── SETTING DEFAULT ──────────────────────────────────────────────────
        $defaults = [
            'nama_sekolah'      => 'SMP Edutive',
            'npsn'              => '00000000',
            'alamat_sekolah'    => 'Jl. Pendidikan No. 1',
            'kepala_sekolah'    => 'Nama Kepala Sekolah',
            'cara_absensi_guru' => 'wajah',
            'poin_terlambat'    => '5',
            'waktu_terlambat'   => '07:30',
            'bobot_harian'      => '60',
            'bobot_pts'         => '20',
            'bobot_pas'         => '20',
        ];
        foreach ($defaults as $key => $val) {
            Setting::firstOrCreate(['key' => $key], ['value' => $val]);
        }

        $this->command->info("✓ Seeder selesai. Login dengan admin / admin123");
    }
}
