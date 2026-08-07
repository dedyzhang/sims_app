<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Log pelanggaran/kejadian anti-kecurangan ujian (keluar fullscreen, ganti tab, reset). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_pelanggaran', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_attempt');
            $table->uuid('id_siswa'); // didenormalisasi utk query cepat per-siswa
            $table->string('tipe', 30); // keluar_fullscreen|ganti_tab|reset_oleh_guru|direset_admin
            $table->string('detail', 150)->nullable();
            $table->timestamps();

            $table->foreign('id_attempt')->references('uuid')->on('ujian_attempts')->cascadeOnDelete();
            $table->foreign('id_siswa')->references('uuid')->on('users')->cascadeOnDelete();
            $table->index(['id_attempt']);
            $table->index(['id_siswa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_pelanggaran');
    }
};
