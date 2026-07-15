<?php

use App\Http\Middleware\CanUseChatbot;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureModulAktif;
use App\Http\Middleware\EnforceLangganan;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UpdateLastSeen;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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

        // Presence Forum: catat last_seen_at tiap request web (auth saja, dithrottle 60 dtk)
        // EnforceLangganan: kunci app (non-superadmin) saat lisensi langganan kadaluarsa.
        $middleware->web(append: [UpdateLastSeen::class, SecurityHeaders::class, EnforceLangganan::class]);

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
        //
    })->create();
