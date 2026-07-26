<?php

namespace App\Providers;

use App\Models\Mission;
use App\Models\Setting;
use App\Models\User;
use App\Policies\MissionPolicy;
use App\Policies\MissionProgressPolicy;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use Ludensa\Contracts\GeneratesAiJson;
use App\Integrations\Ludensa\SimsGeminiAiJsonGenerator;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        if (interface_exists(GeneratesAiJson::class)) {
            $this->app->singleton(GeneratesAiJson::class, SimsGeminiAiJsonGenerator::class);
        }
    }

    public function boot(): void
    {
        Gate::policy(User::class, MissionProgressPolicy::class);
        Gate::policy(Mission::class, MissionPolicy::class);

        // Rate limiter login: cegah brute force username/password & PIN.
        // Di-key per (kredensial + IP) agar penyerang tak bisa men-stuff banyak
        // password ke satu akun, dan satu IP tak bisa menebak banyak akun.
        RateLimiter::for('login', function (Request $request) {
            $credential = Str::lower((string) $request->input('credential'));

            return [
                Limit::perMinute(5)->by($credential . '|' . $request->ip()),
                Limit::perMinute(20)->by($request->ip()),
            ];
        });

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
            $nama = 'Edutive';
            $alamat = null;
            $logoUrl = null;
            $logoExt = null;
            try {
                if (Schema::hasTable('settings')) {
                    $nama   = Setting::get('nama_sekolah', 'Edutive') ?: 'Edutive';
                    $alamat = Setting::get('alamat_sekolah');
                    $logoPath = Setting::get('sekolah_logo');
                    if ($logoPath && file_exists(storage_path('app/public/' . $logoPath))) {
                        $logoUrl = asset('storage/' . $logoPath);
                        $logoExt = strtolower(pathinfo($logoPath, PATHINFO_EXTENSION));
                    }
                }
            } catch (\Throwable $e) {
                // tabel belum ada (mis. saat migrate) — pakai default
            }
            $view->with('namaSekolah', $nama)
                 ->with('alamatSekolah', $alamat)
                 ->with('sekolahLogoUrl', $logoUrl)
                 ->with('sekolahLogoExt', $logoExt);
        });

        // Popup "Apa yang Baru" kini dievaluasi langsung di view layout (via whats-new-modal)
        // memanfaatkan Cache yang jauh lebih ringan daripada mengandalkan flash session
        // yang sering "termakan" oleh middleware redirect.
    }
}
