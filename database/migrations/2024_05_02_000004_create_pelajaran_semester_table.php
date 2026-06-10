<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pelajarans', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('nama');
            $table->string('kode')->nullable();
            $table->integer('tingkat'); // 7, 8, 9
            $table->integer('urutan')->default(0);
            $table->timestamps();
        });

        Schema::create('ngajars', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_guru', 36);
            $table->string('id_pelajaran', 36);
            $table->string('id_kelas', 36)->nullable();
            $table->timestamps();
        });

        Schema::create('semesters', function (Blueprint $table) {
            $table->id();
            $table->integer('semester'); // 1 atau 2
            $table->string('tahun');     // "2024/2025"
            $table->boolean('aktif')->default(false);
            $table->timestamps();
        });

        Schema::create('sekretaris', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_siswa', 36);
            $table->string('id_kelas', 36);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('sekretaris');
        Schema::dropIfExists('semesters');
        Schema::dropIfExists('ngajars');
        Schema::dropIfExists('pelajarans');
    }
};
