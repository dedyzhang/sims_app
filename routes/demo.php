<?php

use App\Demo\DemoCatalogController;
use App\Demo\DemoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes Modul Demo (Terisolasi)
|--------------------------------------------------------------------------
| File ini di-include di routes/web.php HANYA jika config('demo.enabled') bernilai true.
| Karena di-include di routes/web.php, rute ini sudah otomatis mewarisi middleware 'web'.
|
*/

Route::prefix('demo-access')->name('demo.')->group(function () {
    // Endpoint auto-login instan & deep-link per fitur
    Route::get('/go/{slug}', [DemoController::class, 'enter'])->name('enter');

    // Endpoint keluar dari sesi demo
    Route::get('/exit', [DemoController::class, 'exit'])->name('exit');

    // Katalog & generator QR code rahasia untuk tim FL
    Route::get('/showcase', [DemoCatalogController::class, 'index'])->name('showcase');
});
