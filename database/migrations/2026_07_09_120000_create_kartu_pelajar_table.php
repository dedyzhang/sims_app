<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Kartu Pelajar Digital — kartu terbitan sekolah (gambar/PDF) yang diunggah
| admin per siswa, lalu diunduh siswa sendiri. Satu kartu per siswa (unique
| id_siswa). File disimpan di disk privat `local`, hanya bisa diakses lewat
| route ber-auth. Tanpa school_id — SIMS single-school.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kartu_pelajar', function (Blueprint $table) {
            $table->id();
            $table->uuid('id_siswa')->unique();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime')->nullable();
            $table->uuid('uploaded_by')->nullable();
            $table->timestamps();

            $table->foreign('id_siswa')
                ->references('uuid')->on('siswa')
                ->cascadeOnDelete();
            $table->foreign('uploaded_by')
                ->references('uuid')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kartu_pelajar');
    }
};
