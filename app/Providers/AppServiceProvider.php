<?php

namespace App\Providers;

use App\Models\Mission;
use App\Models\Setting;
use App\Models\UjianAttempt;
use App\Models\UjianKelas;
use App\Models\User;
use App\Policies\MissionPolicy;
use App\Policies\MissionProgressPolicy;
use App\Policies\UjianPolicy;
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
        // UjianPolicy sudah auto-terpasang ke model Ujian lewat konvensi nama
        // ({Model}Policy). UjianKelas & UjianAttempt TIDAK match konvensi itu (tak ada
        // UjianKelasPolicy/UjianAttemptPolicy terpisah) jadi wajib didaftarkan manual di
        // sini, atau $this->authorize('take', $ujianKelas) di UjianSiswaController akan
        // selalu 403 walau siswa berhak — Gate tak menemukan policy sama sekali.
        Gate::policy(UjianKelas::class, UjianPolicy::class);
        Gate::policy(UjianAttempt::class, UjianPolicy::class);

        // Rate limiter login: cegah brute force password/PIN pada SATU akun.
        // Sengaja HANYA di-key per (kredensial + IP), TANPA batas per-IP terpisah —
        // sekolah ini bisa punya ~2000 user login serentak dari WiFi yang sama (1 IP
        // NAT), jadi batas per-IP akan memblokir massal orang yang kredensialnya benar.
        // Limiter per-akun ini aman dari skenario itu: tiap siswa pakai username
        // sendiri-sendiri, jadi tak pernah kena limit walau ribuan login bersamaan —
        // limiter baru nyala kalau ADA yang berulang kali salah password di SATU akun
        // yang sama (brute force), bukan soal banyaknya user serentak.
        RateLimiter::for('login', function (Request $request) {
            $credential = Str::lower((string) $request->input('credential'));

            return Limit::perMinute(5)->by($credential . '|' . $request->ip())->response(
                fn (Request $request, array $headers) => $request->expectsJson()
                    ? response()->json(['message' => 'Terlalu banyak percobaan login untuk akun ini. Coba lagi sebentar lagi.'], 429, $headers)
                    : back()->withErrors(['credential' => 'Terlalu banyak percobaan login untuk akun ini. Coba lagi sebentar lagi.'])->withHeaders($headers)
            );
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
