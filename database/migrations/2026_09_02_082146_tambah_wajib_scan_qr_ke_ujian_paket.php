<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin bisa pilih per paket: siswa langsung token (default, perilaku lama)
 * ATAU wajib scan QR ruangan dulu sebelum bisa pilih mapel manapun (lihat
 * UjianPolicy::take()/UjianSiswaController::gate()/UjianPaket::sudahDicekSiswa()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_paket', function (Blueprint $table) {
            $table->boolean('wajib_scan_qr')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('ujian_paket', function (Blueprint $table) {
            $table->dropColumn('wajib_scan_qr');
        });
    }
};
