<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Bagikan nama & identitas sekolah ke layout & login (dari Pengaturan)
        View::composer(['layouts.app', 'auth.login'], function ($view) {
            $nama = 'Edu Nusantara';
            $alamat = null;
            try {
                if (Schema::hasTable('settings')) {
                    $nama   = Setting::get('nama_sekolah', 'Edu Nusantara') ?: 'Edu Nusantara';
                    $alamat = Setting::get('alamat_sekolah');
                }
            } catch (\Throwable $e) {
                // tabel belum ada (mis. saat migrate) — pakai default
            }
            $view->with('namaSekolah', $nama)->with('alamatSekolah', $alamat);
        });
    }
}
