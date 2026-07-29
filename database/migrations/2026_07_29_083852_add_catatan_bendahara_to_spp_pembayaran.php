<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom TERPISAH dari `catatan` yang sudah ada (itu murni utk alasan tolak — dihapus otomatis
 * tiap kali status diverifikasi/divalidasi/direset, lihat KeuanganController). `catatan_bendahara`
 * ini adalah catatan bebas per bulan yang bendahara isi kapan saja & TIDAK ikut terhapus oleh
 * transisi status apa pun — supaya bisa dipakai utk info umum ke orang tua (mis. "sudah dapat
 * potongan yatim piatu", "SPP bulan ini termasuk biaya study tour").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spp_pembayaran', function (Blueprint $table) {
            $table->text('catatan_bendahara')->nullable()->after('catatan');
        });
    }

    public function down(): void
    {
        Schema::table('spp_pembayaran', function (Blueprint $table) {
            $table->dropColumn('catatan_bendahara');
        });
    }
};
