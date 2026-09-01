<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Periode pemilihan ketua OSIS — entitas bisa berulang tiap tahun ajaran (histori
 * tetap tersimpan), TAPI cuma SATU baris `aktif=true` di satu waktu (pola sama
 * persis App\Models\Semester::aktif()) — dipakai sbg default saat admin generate
 * token/cetak QR & buka dashboard tanpa perlu pilih periode dulu.
 * `status` MENENTUKAN apakah publik boleh submit suara (lihat OsisVoteController)
 * — sengaja dipisah dari `aktif` krn admin butuh waktu siapkan paslon & cetak QR
 * SEBELUM pemilihan resmi dibuka: draft -> dibuka -> ditutup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('osis_pemilihan', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('nama', 150);
            $table->string('tahun_ajaran', 20)->nullable();
            $table->string('status', 20)->default('draft'); // draft|dibuka|ditutup
            $table->boolean('aktif')->default(false);
            $table->timestamp('dibuka_pada')->nullable();
            $table->timestamp('ditutup_pada')->nullable();
            $table->uuid('dibuat_oleh')->nullable();
            $table->timestamps();

            $table->foreign('dibuat_oleh')->references('uuid')->on('users')->nullOnDelete();
            $table->index('aktif');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('osis_pemilihan');
    }
};
