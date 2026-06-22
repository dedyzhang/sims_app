<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai Penjabaran: rincian sub-nilai per mata pelajaran tertentu
     * (mis. B. Inggris → Listening/Speaking/Reading/Writing). Mapel mana yang
     * punya penjabaran & komponen nilainya DIATUR ADMIN di menu Setting.
     */
    public function up(): void
    {
        // Komponen penjabaran per mapel (dikonfigurasi admin)
        Schema::create('penjabaran_komponen', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_pelajaran');
            $table->string('nama', 60);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
            $table->index('id_pelajaran');
        });

        // Nilai penjabaran: per siswa per komponen per semester
        Schema::create('nilai_penjabaran', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ngajar');
            $table->uuid('id_siswa');
            $table->uuid('id_komponen');
            $table->unsignedBigInteger('id_semester')->nullable();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['id_siswa', 'id_komponen', 'id_semester'], 'penjabaran_unik');
            $table->index(['id_ngajar', 'id_semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_penjabaran');
        Schema::dropIfExists('penjabaran_komponen');
    }
};
