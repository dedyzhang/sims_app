<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mengganti modul penilaian sederhana (komponen_nilai + nilai) dengan
     * struktur Kurikulum Merdeka seperti smp_ver5:
     * Ngajar → Materi → Tujuan Pembelajaran (TP) → Formatif (per TP) + Sumatif
     * (per materi) + PAS (per mapel) → Rapor (per mapel + deskripsi).
     */
    public function up(): void
    {
        // Buang modul lama (belum ada data nyata)
        Schema::dropIfExists('nilai');
        Schema::dropIfExists('komponen_nilai');

        // Materi (bab) per penugasan mengajar
        Schema::create('materi', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ngajar');
            $table->string('nama');
            $table->unsignedBigInteger('id_semester')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);   // tampil di rapor / dihitung
            $table->timestamps();
            $table->index(['id_ngajar', 'id_semester']);
        });

        // Tujuan Pembelajaran (TP) per materi
        Schema::create('tujuan_pembelajaran', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_materi');
            $table->text('tupe');
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->index('id_materi');
        });

        // Nilai formatif: per TP per siswa
        Schema::create('nilai_formatif', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_materi');
            $table->uuid('id_tupe');
            $table->uuid('id_siswa');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['id_tupe', 'id_siswa'], 'formatif_unik');
            $table->index('id_materi');
        });

        // Nilai sumatif: per materi per siswa
        Schema::create('nilai_sumatif', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_materi');
            $table->uuid('id_siswa');
            $table->decimal('nilai', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['id_materi', 'id_siswa'], 'sumatif_unik');
        });

        // Nilai PAS (Penilaian Akhir Semester): per mapel (ngajar) per siswa
        Schema::create('nilai_pas', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ngajar');
            $table->uuid('id_siswa');
            $table->unsignedBigInteger('id_semester')->nullable();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->timestamps();
            $table->unique(['id_ngajar', 'id_siswa', 'id_semester'], 'pas_unik');
        });

        // Nilai rapor final (terkonfirmasi) + deskripsi capaian
        Schema::create('nilai_rapor', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ngajar');
            $table->uuid('id_siswa');
            $table->unsignedBigInteger('id_semester')->nullable();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('deskripsi_positif')->nullable();
            $table->text('deskripsi_negatif')->nullable();
            $table->timestamps();
            $table->unique(['id_ngajar', 'id_siswa', 'id_semester'], 'rapor_unik');
        });

        // Rumus rapor default (bagi4) bila belum diset admin
        if (!DB::table('settings')->where('key', 'rumus_rapor')->exists()) {
            DB::table('settings')->insert([
                'key' => 'rumus_rapor', 'value' => 'bagi4',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_rapor');
        Schema::dropIfExists('nilai_pas');
        Schema::dropIfExists('nilai_sumatif');
        Schema::dropIfExists('nilai_formatif');
        Schema::dropIfExists('tujuan_pembelajaran');
        Schema::dropIfExists('materi');
    }
};
