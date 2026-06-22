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
        // WebAuthn: samakan Relying Party ID dengan host yang sedang diakses
        // (localhost saat dev, atau domain tunnel HTTPS saat uji dari HP). Tanpa ini
        // server memvalidasi RP ID = host APP_URL (localhost), sehingga pendaftaran
        // biometrik dari domain lain selalu gagal "origin not allowed".
        // Dilewati bila WEBAUTHN_ID sudah diset eksplisit (mis. domain produksi tetap).
        if (! $this->app->runningInConsole() && ! config('webauthn.relying_party.id')) {
            $host = request()->getHost();
            // RP ID harus berupa domain, bukan alamat IP (browser menolak IP sebagai RP ID).
            if ($host && ! filter_var($host, FILTER_VALIDATE_IP)) {
                config(['webauthn.relying_party.id' => $host]);
            }
        }

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
