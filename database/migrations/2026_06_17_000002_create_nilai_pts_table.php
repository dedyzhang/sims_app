<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai PTS (Penilaian Tengah Semester) per mapel (ngajar) per siswa.
     * Hanya dicatat — TIDAK masuk rumus/bobot nilai rapor.
     */
    public function up(): void
    {
        Schema::create('nilai_pts', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ngajar');
            $table->uuid('id_siswa');
            $table->unsignedBigInteger('id_semester')->nullable();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['id_ngajar', 'id_siswa', 'id_semester'], 'pts_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_pts');
    }
};
