<?php

namespace App\Sarpras;

use App\Sarpras\Console\Commands\PemeliharaanReminder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/*
|--------------------------------------------------------------------------
| Service Provider modul Sarpras (terintegrasi SIMS).
|--------------------------------------------------------------------------
| - Memuat rute modul (routes/sarpras.php).
| - Mendaftarkan command scheduler.
| - Mendaftarkan Gate untuk tiap izin 'sarpras.*' yang dipetakan ke kolom
|   users.access milik SIMS (menggantikan spatie/laravel-permission).
|
| Terdaftar di bootstrap/providers.php.
*/
class SarprasServiceProvider extends ServiceProvider
{
    /**
     * MANAGE = pengelola Sarpras (approver/operator).
     * STAFF  = seluruh staf sekolah (boleh melihat & mengajukan).
     */
    private const MANAGE = ['superadmin', 'admin', 'sapras', 'sarpras'];

    private const STAFF = [
        'superadmin', 'admin', 'sapras', 'sarpras', 'kepala',
        'kurikulum', 'kesiswaan', 'sekretaris', 'walikelas', 'guru',
    ];

    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Rute modul Sarpras (group prefix 'sarpras', name 'sarpras.').
        $this->loadRoutesFrom(base_path('routes/sarpras.php'));

        // Command Sarpras (namespace di luar app/Console default).
        if ($this->app->runningInConsole()) {
            $this->commands([
                PemeliharaanReminder::class,
            ]);
        }

        $this->registerGates();
    }

    /**
     * Daftarkan Gate untuk semua izin Sarpras berbasis users.access.
     * Dipakai oleh middleware route 'can:sarpras.*' dan direktif @can di view.
     */
    private function registerGates(): void
    {
        $manage = self::MANAGE;
        $staff = self::STAFF;

        $map = [
            // Dashboard
            'sarpras.dashboard.lihat'     => $staff,

            // Pelaporan kerusakan
            'sarpras.kerusakan.lihat'     => $staff,
            'sarpras.kerusakan.lapor'     => $staff,
            'sarpras.kerusakan.kelola'    => $manage,

            // Denah gedung
            'sarpras.denah.lihat'         => $staff,
            'sarpras.denah.kelola'        => $manage,

            // Pengadaan + supplier
            'sarpras.pengadaan.lihat'     => $staff,
            'sarpras.pengadaan.ajukan'    => $staff,
            'sarpras.pengadaan.setujui'   => $manage,
            'sarpras.pengadaan.kelola'    => $manage,
            'sarpras.supplier.kelola'     => $manage,

            // Katalog & aset
            'sarpras.aset.lihat'          => $staff,
            'sarpras.aset.label'          => $manage,
            'sarpras.aset.kelola'         => $manage,

            // Peminjaman + booking
            'sarpras.peminjaman.lihat'    => $staff,
            'sarpras.peminjaman.ajukan'   => $staff,
            'sarpras.peminjaman.setujui'  => $manage,
            'sarpras.peminjaman.kelola'   => $manage,
            'sarpras.booking.kelola'      => $manage,

            // Perbaikan + teknisi + jadwal
            'sarpras.perbaikan.lihat'     => $staff,
            'sarpras.perbaikan.kelola'    => $manage,
            'sarpras.teknisi.kelola'      => $manage,
            'sarpras.jadwal.kelola'       => $manage,

            // Penghapusan + mutasi
            'sarpras.penghapusan.lihat'   => $staff,
            'sarpras.penghapusan.ajukan'  => $manage,
            'sarpras.penghapusan.setujui' => $manage,
            'sarpras.mutasi.kelola'       => $manage,

            // Laporan + pengaturan
            'sarpras.laporan.lihat'       => $staff,
            'sarpras.laporan.export'      => $manage,
            'sarpras.pengaturan.kelola'   => $manage,
        ];

        foreach ($map as $permission => $roles) {
            Gate::define($permission, fn ($user) => in_array($user->access, $roles, true));
        }
    }
}
