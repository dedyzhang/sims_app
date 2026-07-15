<?php

use App\Sarpras\Http\Controllers\AsetController;
use App\Sarpras\Http\Controllers\BookingController;
use App\Sarpras\Http\Controllers\DashboardController;
use App\Sarpras\Http\Controllers\DenahController;
use App\Sarpras\Http\Controllers\JadwalController;
use App\Sarpras\Http\Controllers\KategoriController;
use App\Sarpras\Http\Controllers\KerusakanController;
use App\Sarpras\Http\Controllers\LaporanController;
use App\Sarpras\Http\Controllers\MutasiController;
use App\Sarpras\Http\Controllers\PeminjamanController;
use App\Sarpras\Http\Controllers\PenghapusanController;
use App\Sarpras\Http\Controllers\PengadaanController;
use App\Sarpras\Http\Controllers\PerbaikanController;
use App\Sarpras\Http\Controllers\RuanganController;
use App\Sarpras\Http\Controllers\SupplierController;
use App\Sarpras\Http\Controllers\TeknisiController;
use Illuminate\Support\Facades\Route;

/*
|==========================================================================
| Rute modul Sarpras — prefix 'sarpras', name 'sarpras.', middleware auth.
|==========================================================================
| Otorisasi granular via middleware 'can:...' (Gate native SIMS — lihat
| App\Sarpras\SarprasServiceProvider yang memetakan tiap izin ke users.access).
*/
Route::middleware(['web', 'auth', 'modul:sarpras'])->prefix('sarpras')->name('sarpras.')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])
        ->middleware('can:sarpras.dashboard.lihat')->name('dashboard');

    /* 1. PELAPORAN KERUSAKAN */
    Route::middleware('can:sarpras.kerusakan.lihat')->group(function () {
        Route::get('kerusakan', [KerusakanController::class, 'index'])->name('kerusakan.index');
        Route::get('kerusakan/{kerusakan}', [KerusakanController::class, 'show'])->name('kerusakan.show');
    });
    Route::get('kerusakan-lapor', [KerusakanController::class, 'create'])
        ->middleware('can:sarpras.kerusakan.lapor')->name('kerusakan.create');
    Route::post('kerusakan', [KerusakanController::class, 'store'])
        ->middleware('can:sarpras.kerusakan.lapor')->name('kerusakan.store');
    Route::post('kerusakan/{kerusakan}/terima', [KerusakanController::class, 'terima'])
        ->middleware('can:sarpras.kerusakan.kelola')->name('kerusakan.terima');
    Route::post('kerusakan/{kerusakan}/tolak', [KerusakanController::class, 'tolak'])
        ->middleware('can:sarpras.kerusakan.kelola')->name('kerusakan.tolak');

    /* 2. DENAH SEKOLAH */
    Route::middleware('can:sarpras.denah.lihat')->group(function () {
        Route::get('denah', [DenahController::class, 'index'])->name('denah.index');
        Route::get('denah/{denah}', [DenahController::class, 'show'])->name('denah.show');
        Route::get('ruangan/{ruangan}', [RuanganController::class, 'show'])->name('ruangan.show');
    });
    Route::middleware('can:sarpras.denah.kelola')->group(function () {
        Route::get('denah-kelola/baru', [DenahController::class, 'create'])->name('denah.create');
        Route::post('denah', [DenahController::class, 'store'])->name('denah.store');
        Route::get('denah/{denah}/edit', [DenahController::class, 'edit'])->name('denah.edit');
        Route::put('denah/{denah}', [DenahController::class, 'update'])->name('denah.update');
        Route::delete('denah/{denah}', [DenahController::class, 'destroy'])->name('denah.destroy');
        // Editor sketsa: menggambar denah langsung di aplikasi (kanvas).
        Route::get('denah/{denah}/gambar', [DenahController::class, 'editorGambar'])->name('denah.gambar');
        Route::post('denah/{denah}/gambar', [DenahController::class, 'simpanGambar'])->name('denah.gambar.simpan');
        // Import gambar denah dari file (jpg/png/webp/gif/bmp).
        Route::post('denah/{denah}/import', [DenahController::class, 'imporGambar'])->name('denah.import');
        // Hapus gambar denah (mis. hasil import yang tidak sesuai).
        Route::delete('denah/{denah}/gambar', [DenahController::class, 'hapusGambar'])->name('denah.gambar.hapus');
        // Editor hotspot (penempatan ruangan dengan koordinat persen).
        Route::get('denah/{denah}/hotspot', [DenahController::class, 'editorHotspot'])->name('denah.hotspot');
        Route::post('denah/{denah}/ruangan', [RuanganController::class, 'store'])->name('ruangan.store');
        // Import data ruangan ke denah dari Excel/CSV + unduh template.
        Route::get('ruangan-import/template', [RuanganController::class, 'templateImport'])->name('ruangan.import.template');
        Route::post('denah/{denah}/ruangan-import', [RuanganController::class, 'import'])->name('ruangan.import');
        Route::put('ruangan/{ruangan}', [RuanganController::class, 'update'])->name('ruangan.update');
        Route::post('ruangan/{ruangan}/posisi', [RuanganController::class, 'simpanPosisi'])->name('ruangan.posisi');
        Route::delete('ruangan/{ruangan}', [RuanganController::class, 'destroy'])->name('ruangan.destroy');
    });

    /* 3. PENGADAAN + SUPPLIER */
    Route::middleware('can:sarpras.pengadaan.lihat')->group(function () {
        Route::get('pengadaan', [PengadaanController::class, 'index'])->name('pengadaan.index');
        Route::get('pengadaan/{pengadaan}', [PengadaanController::class, 'show'])->name('pengadaan.show');
    });
    Route::get('pengadaan-baru', [PengadaanController::class, 'create'])
        ->middleware('can:sarpras.pengadaan.ajukan')->name('pengadaan.create');
    Route::post('pengadaan', [PengadaanController::class, 'store'])
        ->middleware('can:sarpras.pengadaan.ajukan')->name('pengadaan.store');
    Route::post('pengadaan/{pengadaan}/setujui', [PengadaanController::class, 'setujui'])
        ->middleware('can:sarpras.pengadaan.setujui')->name('pengadaan.setujui');
    Route::post('pengadaan/{pengadaan}/tolak', [PengadaanController::class, 'tolak'])
        ->middleware('can:sarpras.pengadaan.setujui')->name('pengadaan.tolak');
    Route::post('pengadaan/{pengadaan}/terima', [PengadaanController::class, 'terima'])
        ->middleware('can:sarpras.pengadaan.kelola')->name('pengadaan.terima');
    Route::post('pengadaan/{pengadaan}/dokumen', [PengadaanController::class, 'uploadDokumen'])
        ->middleware('can:sarpras.pengadaan.kelola')->name('pengadaan.dokumen');
    Route::resource('supplier', SupplierController::class)
        ->middleware('can:sarpras.supplier.kelola')
        ->except(['show']);

    /* 4. KATALOG & ASET */
    Route::middleware('can:sarpras.aset.lihat')->group(function () {
        Route::get('aset', [AsetController::class, 'index'])->name('aset.index');
        Route::get('aset/{aset}', [AsetController::class, 'show'])->name('aset.show');
        Route::get('aset/{aset}/qr', [AsetController::class, 'qr'])->name('aset.qr');
    });
    Route::get('aset/{aset}/label', [AsetController::class, 'label'])
        ->middleware('can:sarpras.aset.label')->name('aset.label');
    Route::middleware('can:sarpras.aset.kelola')->group(function () {
        Route::get('aset-baru/form', [AsetController::class, 'create'])->name('aset.create');
        Route::post('aset', [AsetController::class, 'store'])->name('aset.store');
        // Import katalog aset dari Excel/CSV + unduh template.
        Route::get('aset-import/template', [AsetController::class, 'templateImport'])->name('aset.import.template');
        Route::post('aset-import', [AsetController::class, 'import'])->name('aset.import');
        Route::get('aset/{aset}/edit', [AsetController::class, 'edit'])->name('aset.edit');
        Route::put('aset/{aset}', [AsetController::class, 'update'])->name('aset.update');
        Route::delete('aset/{aset}', [AsetController::class, 'destroy'])->name('aset.destroy');
    });

    /* 5a. RUANGAN & BOOKING */
    Route::get('booking', [BookingController::class, 'index'])
        ->middleware('can:sarpras.peminjaman.lihat')->name('booking.index');
    Route::post('booking', [BookingController::class, 'store'])
        ->middleware('can:sarpras.peminjaman.ajukan')->name('booking.store');
    Route::post('booking/{booking}/setujui', [BookingController::class, 'setujui'])
        ->middleware('can:sarpras.booking.kelola')->name('booking.setujui');
    Route::post('booking/{booking}/tolak', [BookingController::class, 'tolak'])
        ->middleware('can:sarpras.booking.kelola')->name('booking.tolak');

    /* 5. PEMINJAMAN (terintegrasi dengan booking ruangan) */
    Route::middleware('can:sarpras.peminjaman.lihat')->group(function () {
        Route::get('peminjaman', [PeminjamanController::class, 'index'])->name('peminjaman.index');
        Route::get('peminjaman/{peminjaman}', [PeminjamanController::class, 'show'])->name('peminjaman.show');
    });
    Route::get('peminjaman-baru', [PeminjamanController::class, 'create'])
        ->middleware('can:sarpras.peminjaman.ajukan')->name('peminjaman.create');
    Route::post('peminjaman', [PeminjamanController::class, 'store'])
        ->middleware('can:sarpras.peminjaman.ajukan')->name('peminjaman.store');
    Route::post('peminjaman/{peminjaman}/setujui', [PeminjamanController::class, 'setujui'])
        ->middleware('can:sarpras.peminjaman.setujui')->name('peminjaman.setujui');
    Route::post('peminjaman/{peminjaman}/tolak', [PeminjamanController::class, 'tolak'])
        ->middleware('can:sarpras.peminjaman.setujui')->name('peminjaman.tolak');
    Route::post('peminjaman/{peminjaman}/kembalikan', [PeminjamanController::class, 'kembalikan'])
        ->middleware('can:sarpras.peminjaman.kelola')->name('peminjaman.kembalikan');

    /* 6. PERBAIKAN + TEKNISI + JADWAL */
    Route::middleware('can:sarpras.perbaikan.lihat')->group(function () {
        Route::get('perbaikan', [PerbaikanController::class, 'index'])->name('perbaikan.index');
        Route::get('perbaikan/{perbaikan}', [PerbaikanController::class, 'show'])->name('perbaikan.show');
    });
    Route::middleware('can:sarpras.perbaikan.kelola')->group(function () {
        Route::get('perbaikan-baru', [PerbaikanController::class, 'create'])->name('perbaikan.create');
        Route::post('perbaikan', [PerbaikanController::class, 'store'])->name('perbaikan.store');
        Route::put('perbaikan/{perbaikan}', [PerbaikanController::class, 'update'])->name('perbaikan.update');
        Route::post('perbaikan/{perbaikan}/selesai', [PerbaikanController::class, 'selesai'])->name('perbaikan.selesai');
    });
    Route::resource('teknisi', TeknisiController::class)
        ->middleware('can:sarpras.teknisi.kelola')->except(['show']);
    Route::resource('jadwal', JadwalController::class)
        ->middleware('can:sarpras.jadwal.kelola')->except(['show']);

    /* 7. PENGHAPUSAN + MUTASI */
    Route::middleware('can:sarpras.penghapusan.lihat')->group(function () {
        Route::get('penghapusan', [PenghapusanController::class, 'index'])->name('penghapusan.index');
        Route::get('penghapusan/{penghapusan}', [PenghapusanController::class, 'show'])->name('penghapusan.show');
        Route::get('penghapusan/{penghapusan}/berita-acara', [PenghapusanController::class, 'beritaAcara'])->name('penghapusan.berita');
    });
    Route::get('penghapusan-baru', [PenghapusanController::class, 'create'])
        ->middleware('can:sarpras.penghapusan.ajukan')->name('penghapusan.create');
    Route::post('penghapusan', [PenghapusanController::class, 'store'])
        ->middleware('can:sarpras.penghapusan.ajukan')->name('penghapusan.store');
    Route::post('penghapusan/{penghapusan}/setujui', [PenghapusanController::class, 'setujui'])
        ->middleware('can:sarpras.penghapusan.setujui')->name('penghapusan.setujui');
    Route::post('penghapusan/{penghapusan}/tolak', [PenghapusanController::class, 'tolak'])
        ->middleware('can:sarpras.penghapusan.setujui')->name('penghapusan.tolak');

    Route::middleware('can:sarpras.mutasi.kelola')->group(function () {
        Route::get('mutasi', [MutasiController::class, 'index'])->name('mutasi.index');
        Route::get('mutasi-baru', [MutasiController::class, 'create'])->name('mutasi.create');
        Route::post('mutasi', [MutasiController::class, 'store'])->name('mutasi.store');
        Route::get('mutasi/{mutasi}/berita-acara', [MutasiController::class, 'beritaAcara'])->name('mutasi.berita');
    });

    /* 8. LAPORAN + PENGATURAN */
    Route::middleware('can:sarpras.laporan.lihat')->group(function () {
        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan.index');
        Route::get('laporan/aktivitas', [LaporanController::class, 'aktivitas'])->name('laporan.aktivitas');
    });
    Route::middleware('can:sarpras.laporan.export')->group(function () {
        Route::get('laporan/aset/excel', [LaporanController::class, 'exportAsetExcel'])->name('laporan.aset.excel');
        Route::get('laporan/aset/pdf', [LaporanController::class, 'exportAsetPdf'])->name('laporan.aset.pdf');
        Route::get('laporan/mutasi/excel', [LaporanController::class, 'exportMutasiExcel'])->name('laporan.mutasi.excel');
    });
    // Import kategori aset dari Excel/CSV + unduh template (sebelum resource).
    Route::middleware('can:sarpras.pengaturan.kelola')->group(function () {
        Route::get('kategori-import/template', [KategoriController::class, 'templateImport'])->name('kategori.import.template');
        Route::post('kategori-import', [KategoriController::class, 'import'])->name('kategori.import');
    });
    Route::resource('kategori', KategoriController::class)
        ->middleware('can:sarpras.pengaturan.kelola')->except(['show']);
});
