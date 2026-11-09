<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_pretests', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ujian');
            $table->string('token', 32)->unique();
            $table->string('judul')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('durasi_menit')->default(60);
            $table->boolean('acak_soal')->default(true);
            $table->boolean('acak_opsi')->default(true);
            $table->boolean('tampilkan_nilai')->default(true);
            $table->boolean('tampilkan_pembahasan')->default(false);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('id_ujian')->references('uuid')->on('ujians')->cascadeOnDelete();
            $table->foreign('created_by')->references('uuid')->on('users')->nullOnDelete();
            $table->index(['id_ujian', 'is_active']);
        });

        Schema::create('ujian_pretest_pesertas', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_pretest');
            $table->string('guest_token', 64)->index();
            $table->string('nama');
            $table->string('identitas')->comment('Asal sekolah / kelas / instansi');
            $table->string('nomor_kontak')->nullable();
            $table->json('urutan_soal');
            $table->json('urutan_opsi')->nullable();
            $table->timestamp('mulai_pada')->nullable();
            $table->timestamp('batas_waktu_pada')->nullable();
            $table->timestamp('selesai_pada')->nullable();
            $table->string('status', 20)->default('in_progress');
            $table->decimal('skor_objektif', 5, 2)->default(0);
            $table->decimal('total_skor', 5, 2)->default(0);
            $table->unsignedInteger('jumlah_benar')->default(0);
            $table->unsignedInteger('jumlah_salah')->default(0);
            $table->unsignedInteger('total_soal')->default(0);
            $table->timestamps();

            $table->foreign('id_pretest')->references('uuid')->on('ujian_pretests')->cascadeOnDelete();
            $table->index(['id_pretest', 'status']);
        });

        Schema::create('ujian_pretest_jawabans', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_peserta');
            $table->uuid('id_soal');
            $table->uuid('id_opsi_dipilih')->nullable();
            $table->json('opsi_dipilih_multi')->nullable();
            $table->json('jawaban_pasangan')->nullable();
            $table->text('jawaban_esai')->nullable();
            $table->boolean('is_benar')->nullable();
            $table->decimal('skor_diperoleh', 5, 2)->default(0);
            $table->timestamp('dijawab_pada')->nullable();
            $table->timestamps();

            $table->foreign('id_peserta')->references('uuid')->on('ujian_pretest_pesertas')->cascadeOnDelete();
            $table->foreign('id_soal')->references('uuid')->on('ujian_soal')->cascadeOnDelete();
            $table->unique(['id_peserta', 'id_soal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_pretest_jawabans');
        Schema::dropIfExists('ujian_pretest_pesertas');
        Schema::dropIfExists('ujian_pretests');
    }
};
