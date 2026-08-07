<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modul Ujian (formal: harian/PTS/PAS/UAS) — bank soal & penugasan kelas.
 * Sengaja terpisah dari Arena Belajar (game_quizzes dkk) — beda nuansa
 * (formal, bukan gamifikasi) dan beda kebutuhan (multi-kelas serentak,
 * anti-kecurangan ketat, transfer otomatis ke Nilai PTS/PAS/Sumatif).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujians', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_pelajaran');
            // Dipakai HANYA kalau target_nilai='sumatif' (jenis harian) — Materi bersifat
            // per-Ngajar, jadi ujian dgn target ini dibatasi ke SATU kelas (lihat syncKelas()).
            $table->uuid('id_materi')->nullable();
            $table->uuid('created_by');

            $table->string('judul');
            $table->longText('instruksi')->nullable();
            $table->string('jenis', 16)->default('harian'); // harian|pts|pas|uas
            $table->string('target_nilai', 16)->default('pts'); // pts|pas|sumatif
            $table->unsignedInteger('durasi_menit')->default(60);
            $table->boolean('acak_soal')->default(true);
            $table->boolean('acak_opsi')->default(true);
            $table->boolean('tampilkan_pembahasan')->default(false);
            $table->string('status', 16)->default('draft'); // draft|published|closed

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_pelajaran')->references('uuid')->on('pelajarans')->cascadeOnDelete();
            $table->foreign('id_materi')->references('uuid')->on('materi')->nullOnDelete();
            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnDelete();
            $table->index(['status']);
        });

        Schema::create('ujian_kelas', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ujian');
            $table->uuid('id_kelas');
            // Token per-kelas (bukan per-ujian) — mencegah kelas yg ujian hari Senin
            // membocorkan token ke kelas yg ujian hari Selasa pada ujian PTS/PAS yg sama.
            $table->string('token_masuk', 16)->nullable();
            $table->timestamp('dibuka_mulai')->nullable();
            $table->timestamp('dibuka_sampai')->nullable();
            $table->string('status', 16)->default('open'); // open|closed
            $table->timestamps();

            $table->foreign('id_ujian')->references('uuid')->on('ujians')->cascadeOnDelete();
            $table->foreign('id_kelas')->references('uuid')->on('kelas')->cascadeOnDelete();
            $table->unique(['id_ujian', 'id_kelas']);
        });

        Schema::create('ujian_soal', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ujian');
            $table->string('tipe', 16)->default('mcq'); // mcq|mcq_complex|true_false|match|essay
            $table->text('teks_soal');
            $table->unsignedInteger('poin')->default(1);
            $table->unsignedInteger('urutan')->default(0);
            // match: {"pairs":[{"left":..,"right":..}]} (bentuk sama dgn GameQuestion.meta).
            // essay: opsional kunci-jawaban referensi utk layar penilaian, TIDAK dipakai auto-grading.
            $table->json('meta')->nullable();
            $table->text('penjelasan')->nullable();
            $table->timestamps();

            $table->foreign('id_ujian')->references('uuid')->on('ujians')->cascadeOnDelete();
            $table->index(['id_ujian', 'urutan']);
        });

        Schema::create('ujian_soal_opsi', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_soal');
            $table->text('teks_opsi');
            $table->boolean('is_benar')->default(false);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->foreign('id_soal')->references('uuid')->on('ujian_soal')->cascadeOnDelete();
            $table->index(['id_soal', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_soal_opsi');
        Schema::dropIfExists('ujian_soal');
        Schema::dropIfExists('ujian_kelas');
        Schema::dropIfExists('ujians');
    }
};
