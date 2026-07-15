<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rekapan pemanggilan orang tua/siswa — dicatat guru atau kesiswaan, tanpa alur
        // approval (beda dari poin_temp/p3_temp) karena ini murni catatan kejadian, bukan
        // pengajuan poin yang perlu disetujui.
        Schema::create('pemanggilan', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_siswa', 36);
            $table->date('tanggal');
            $table->string('dipanggil', 20); // siswa|orangtua|keduanya
            $table->string('perihal', 150);
            $table->text('permasalahan');
            $table->text('hasil')->nullable(); // boleh diisi belakangan setelah pertemuan
            $table->string('id_pencatat', 36); // users.uuid — guru ATAU kesiswaan/admin, bukan selalu guru
            $table->timestamps();
            $table->index('id_siswa');
            $table->index('tanggal');
        });

        // Dokumentasi opsional (foto/PDF bukti pertemuan) — pola sama persis dgn rapat_dokumentasi.
        Schema::create('pemanggilan_dokumentasi', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_pemanggilan', 36);
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_original');
            $table->unsignedBigInteger('size_compressed');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('id_pengunggah', 36)->nullable();
            $table->timestamps();
            $table->index('id_pemanggilan');

            $table->foreign('id_pemanggilan')->references('uuid')->on('pemanggilan')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pemanggilan_dokumentasi');
        Schema::dropIfExists('pemanggilan');
    }
};
