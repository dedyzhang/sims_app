<?php

use App\Http\Middleware\CanUseChatbot;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureModulAktif;
use App\Http\Middleware\EnforceLangganan;
use App\Http\Middleware\EnforceModeUjian;
use App\Http\Middleware\RetryOnDatabaseException;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UpdateLastSeen;
use App\Support\DbBusy;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Percayai proxy (mis. tunnel cloudflared/ngrok) agar HTTPS terdeteksi benar
        // → URL form jadi https, tak ada peringatan "not secure".
        $middleware->trustProxies(at: '*');

        // Kiosk absensi: bebaskan CSRF untuk endpoint presensi live (karena diproteksi EnsureKioskOrPermission / token _kiosk dan agar kios berjalan nonstop tanpa terkena 419 Page Expired).
        $middleware->validateCsrfTokens(except: [
            'absensi/match-face',
            'absensi/mark',
            'absensi/mark-barcode',
            'absensi/face-telemetry',
            'absensi/cancel',
            'presensi-guru/mark',
            'presensi-guru/cancel',
        ]);

        // RetryOnDatabaseException: lindungi request dari micro-spike / max_user_connections database.
        // Presence Forum: catat last_seen_at tiap request web (auth saja, dithrottle 60 dtk)
        // EnforceLangganan: kunci app (non-superadmin) saat lisensi langganan kadaluarsa.
        // EnforceModeUjian: kunci siswa ke halaman ujian saat mode ujian aktif.
        // DemoSessionGuard: kunci & proteksi sesi demo (logout seketika jika demo dinonaktifkan).
        $middleware->web(
            prepend: [RetryOnDatabaseException::class],
            append: [UpdateLastSeen::class, SecurityHeaders::class, EnforceLangganan::class, EnforceModeUjian::class, \App\Demo\DemoSessionGuard::class]
        );

        // Alias middleware. Gating role memakai `role:a,b,c` (CheckRole) yang
        // parameterized — superadmin selalu diizinkan. Middleware IsX per-role
        // yang lama sudah dihapus karena otorisasi kini ada di controller/policy
        // (dan modul Sarpras memakai `can:`).
        $middleware->alias([
            'role'         => CheckRole::class,
            'permission'   => \App\Http\Middleware\CheckPermission::class,
            'chatbot.user' => CanUseChatbot::class,
            'modul'        => EnsureModulAktif::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Hosting shared (mis. Hostinger) sering membatasi jumlah koneksi MySQL bersamaan
        // — saat trafik ramai (siswa buka ujian/scan QR serentak), koneksi baru bisa
        // ditolak SESAAT. Ini gangguan sementara, bukan bug — tampilkan pesan "coba lagi"
        // yg ramah alih2 exception mentah. Titik ini menangkap SEMUA jalur, termasuk yg
        // gagal di route-model-binding (sebelum kode controller sempat jalan sama sekali,
        // jadi tak bisa ditangani lewat retry() di dalam controller).
        $exceptions->render(function (QueryException $e, Request $request) {
            if (! DbBusy::terdeteksi($e)) {
                return null; // biarkan Laravel tangani spt biasa — bukan gangguan koneksi ini.
            }

            // Retry-After acak (3–8 dtk): browser yg sopan (gate token, autosave ujian) menunggu
            // selama ini sebelum mencoba lagi — acak supaya ratusan siswa yg gagal bersamaan
            // tak serempak menembak ulang di detik yg sama.
            $headers = ['Retry-After' => (string) random_int(3, 8)];

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => DbBusy::pesan()], 503, $headers);
            }

            return response()->view('errors.db-busy', [], 503, $headers);
        });
    })->create();
