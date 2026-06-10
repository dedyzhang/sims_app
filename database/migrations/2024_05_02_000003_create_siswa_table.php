<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_login')->nullable();
            $table->string('nama');
            $table->string('nis')->nullable()->unique();
            $table->string('nisn')->nullable();
            $table->string('id_kelas', 36)->nullable();
            $table->enum('jk', ['L', 'P'])->default('L');
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            $table->text('alamat')->nullable();
            $table->string('no_handphone')->nullable();
            $table->string('nama_ayah')->nullable();
            $table->string('pekerjaan_ayah')->nullable();
            $table->string('no_telp_ayah')->nullable();
            $table->string('nama_ibu')->nullable();
            $table->string('pekerjaan_ibu')->nullable();
            $table->string('no_telp_ibu')->nullable();
            $table->string('nama_wali')->nullable();
            $table->string('pekerjaan_wali')->nullable();
            $table->string('no_telp_wali')->nullable();
            $table->string('sekolah_asal')->nullable();
            $table->string('nama_ijazah')->nullable();
            $table->string('ortu_ijazah')->nullable();
            $table->string('tempat_lahir_ijazah')->nullable();
            $table->date('tanggal_lahir_ijazah')->nullable();
            $table->string('va')->nullable();
            $table->integer('spp')->nullable();
            $table->string('foto')->nullable();
            $table->timestamps();
        });

        Schema::create('orangtua', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_siswa', 36);
            $table->string('id_login', 36);
            $table->timestamps();
        });

        Schema::create('nis', function (Blueprint $table) {
            $table->id();
            $table->integer('kode')->default(1);
            $table->timestamps();
        });

        Schema::create('rombels', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_siswa', 36);
            $table->string('id_kelas', 36);
            $table->string('semester'); // "1/2024", "2/2024"
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rombels');
        Schema::dropIfExists('nis');
        Schema::dropIfExists('orangtua');
        Schema::dropIfExists('siswa');
    }
};
