<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PelajaranController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SiswaController;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Route;
use Laragear\WebAuthn\Http\Routes as WebAuthnRoutes;

// ─── Publik ───────────────────────────────────────────────────────────────────
Route::get('/', fn() => redirect()->route('login'));

// ─── Auth ─────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});
Route::post('/login/pin', [LoginController::class, 'loginPin'])->name('login.pin');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
Route::post('/password/request', [LoginController::class, 'requestResetPassword'])->name('password.request');

// WebAuthn (Fingerprint / Face ID)
WebAuthnRoutes::register('webauthn');

// ─── Authenticated ────────────────────────────────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/home', [LoginController::class, 'home'])->name('auth.home');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Ganti password & PIN
    Route::get('/ganti-password', [LoginController::class, 'changePasswordPage'])->name('ganti.password');
    Route::post('/ganti-password', [LoginController::class, 'changePassword']);
    Route::get('/ganti-pin', [LoginController::class, 'changePinPage'])->name('ganti.pin');
    Route::post('/ganti-pin', [LoginController::class, 'changePin']);

    // Profil & Preferensi Tampilan
    Route::controller(ProfileController::class)->group(function () {
        Route::get('/profile', 'index')->name('profile.index');
        Route::get('/profile/edit', 'edit')->name('profile.edit');
        Route::put('/profile/update', 'update')->name('profile.update');
        Route::get('/profile/tampilan', 'preferenceEdit')->name('profile.preference');
        Route::match(['put','post'], '/profile/tampilan', 'preferenceUpdate')->name('profile.preference.update');
        Route::get('/profile/tampilan/reset', 'preferenceReset')->name('profile.preference.reset');
        Route::post('/profile/gaya', 'setStyle')->name('profile.style');
    });

    // ─── Admin ─────────────────────────────────────────────────────────────
    Route::middleware(IsAdmin::class)->group(function () {

        // Guru
        Route::resource('/guru', GuruController::class);
        Route::post('/guru/{uuid}/reset', [GuruController::class, 'reset'])->name('guru.reset');
        Route::get('/guru/{uuid}/pelajaran', [GuruController::class, 'pelajaran'])->name('guru.pelajaran');
        Route::post('/guru/{uuid}/pelajaran', [GuruController::class, 'ngajar'])->name('guru.ngajar');
        Route::delete('/guru/pelajaran/{uuid}/hapus', [GuruController::class, 'hapusNgajar'])->name('guru.hapusNgajar');

        // Kelas
        Route::resource('/kelas', KelasController::class)->except('show');
        Route::get('/kelas/{uuid}/walikelas', [KelasController::class, 'showWalikelas'])->name('kelas.showWalikelas');
        Route::post('/kelas/{uuid}/walikelas', [KelasController::class, 'walikelas'])->name('kelas.walikelas');
        Route::get('/kelas/setKelas', [KelasController::class, 'setKelasSiswa'])->name('kelas.setKelas');
        Route::post('/kelas/{uuid}/saveRombel', [KelasController::class, 'saveRombel'])->name('kelas.saveRombel');
        Route::get('/kelas/setKelas/histori', [KelasController::class, 'historiRombel'])->name('kelas.historiRombel');
        Route::post('/kelas/setKelas/histori/{uuid}/hapus', [KelasController::class, 'historiHapus'])->name('kelas.historiHapus');

        // Siswa
        Route::resource('/siswa', SiswaController::class);
        Route::post('/siswa/{uuid}/reset/siswa', [SiswaController::class, 'resetSiswa'])->name('siswa.reset');
        Route::post('/siswa/{uuid}/reset/ortu', [SiswaController::class, 'resetOrangtua'])->name('siswa.resetOrtu');

        // Pelajaran (semua AJAX, tanpa halaman create/edit terpisah)
        Route::get('/pelajaran', [PelajaranController::class, 'index'])->name('pelajaran.index');
        Route::post('/pelajaran', [PelajaranController::class, 'store'])->name('pelajaran.store');
        Route::put('/pelajaran/{uuid}', [PelajaranController::class, 'update'])->name('pelajaran.update');
        Route::delete('/pelajaran/{uuid}', [PelajaranController::class, 'destroy'])->name('pelajaran.destroy');
        Route::post('/pelajaran/sorting', [PelajaranController::class, 'sorting'])->name('pelajaran.sorting');

        // Import Siswa
        Route::get('/siswa/import', [SiswaController::class, 'importForm'])->name('siswa.importForm');
        Route::post('/siswa/import', [SiswaController::class, 'import'])->name('siswa.import');
        Route::get('/siswa/import/template', [SiswaController::class, 'downloadTemplate'])->name('siswa.template');

        // Setting
        Route::controller(SettingController::class)->prefix('settings')->group(function () {
            Route::get('/', 'index')->name('setting.index');
            Route::post('/semester', 'updateSemester')->name('setting.semester');
            Route::post('/semester/store', 'storeSemester')->name('setting.semester.store');
            Route::post('/identitas', 'setIdentitasSekolah')->name('setting.identitas');
            Route::post('/poin-terlambat', 'setPoinTerlambat')->name('setting.poinTerlambat');
            Route::post('/waktu-terlambat', 'setWaktuTerlambat')->name('setting.waktuTerlambat');
            Route::post('/mapel-rapor', 'setMapelRapor')->name('setting.mapelRapor');
            Route::post('/tanggal-rapor', 'setTanggalRapor')->name('setting.tanggalRapor');
            Route::post('/cara-absensi', 'setCaraAbsensi')->name('setting.caraAbsensi');
            Route::post('/rumus-rapor', 'setRumusRapor')->name('setting.rumusRapor');
        });
    });
});
