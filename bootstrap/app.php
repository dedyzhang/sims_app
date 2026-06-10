<?php

use App\Http\Middleware\CheckRole;
use App\Http\Middleware\IsAdmin;
use App\Http\Middleware\IsAdminKesiswaan;
use App\Http\Middleware\IsAdminKurikulum;
use App\Http\Middleware\IsAdminKurikulumKepala;
use App\Http\Middleware\IsAdminSapras;
use App\Http\Middleware\IsGuru;
use App\Http\Middleware\IsNgajar;
use App\Http\Middleware\IsPenilaianController;
use App\Http\Middleware\IsSekretaris;
use App\Http\Middleware\IsSiswa;
use App\Http\Middleware\IsSiswaOrangtua;
use App\Http\Middleware\IsWalidanSekre;
use App\Http\Middleware\IsWalikelas;
use App\Http\Middleware\IsWaliSekredanGuru;
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
        // Alias middleware role
        $middleware->alias([
            'role'                   => CheckRole::class,
            'isAdmin'                => IsAdmin::class,
            'isAdminKurikulum'       => IsAdminKurikulum::class,
            'isAdminKesiswaan'       => IsAdminKesiswaan::class,
            'isAdminSapras'          => IsAdminSapras::class,
            'isAdminKurikulumKepala' => IsAdminKurikulumKepala::class,
            'isGuru'                 => IsGuru::class,
            'isNgajar'               => IsNgajar::class,
            'isPenilaian'            => IsPenilaianController::class,
            'isWalikelas'            => IsWalikelas::class,
            'isSekretaris'           => IsSekretaris::class,
            'isWaliSekre'            => IsWaliSekredanGuru::class,
            'isSiswa'                => IsSiswa::class,
            'isSiswaOrtu'            => IsSiswaOrangtua::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
