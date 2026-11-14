<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survei_periodes', function (Blueprint $t) {
            $t->uuid('uuid')->primary();
            $t->uuid('school_id')->index();
            $t->string('judul');
            $t->dateTime('mulai');
            $t->dateTime('selesai');
            $t->json('pertanyaan');
            $t->boolean('ditutup')->default(false);
            $t->uuid('dibuat_oleh');
            $t->timestamps();
        });
        Schema::create('survei_targets', function (Blueprint $t) {
            $t->uuid('uuid')->primary();
            $t->uuid('school_id')->index();
            $t->uuid('periode_id');
            $t->uuid('kelas_id');
            $t->uuid('guru_id');
            $t->uuid('pelajaran_id')->nullable();
            $t->string('jenis');
            $t->string('kelas_nama');
            $t->string('guru_nama');
            $t->string('pelajaran_nama')->nullable();
            $t->timestamps();
            $t->foreign('periode_id')->references('uuid')->on('survei_periodes')->cascadeOnDelete();
            $t->index(['periode_id', 'kelas_id']);
        });
        Schema::create('survei_respons', function (Blueprint $t) {
            $t->uuid('uuid')->primary();
            $t->uuid('school_id')->index();
            $t->uuid('target_id');
            $t->uuid('siswa_id');
            $t->json('jawaban');
            $t->json('komentar');
            $t->timestamps();
            $t->foreign('target_id')->references('uuid')->on('survei_targets')->cascadeOnDelete();
            $t->unique(['target_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survei_respons');
        Schema::dropIfExists('survei_targets');
        Schema::dropIfExists('survei_periodes');
    }
};
