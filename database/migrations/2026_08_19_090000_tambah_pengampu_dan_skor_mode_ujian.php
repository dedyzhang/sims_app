<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_kelas', function (Blueprint $table) {
            // Guru yg ditetapkan bertanggung jawab atas kelas ini pada ujian ini — dipakai
            // saat satu mapel+kelas diajar 2+ guru (kolaboratif) & perlu satu penanggung jawab
            // yg jelas utk penilaian esai & resolusi id_ngajar transfer nilai. Nullable: kalau
            // cuma 1 guru cocok, tak perlu dipilih manual (auto/tak ambigu).
            $table->uuid('id_guru_pengampu')->nullable()->after('id_kelas');
            $table->foreign('id_guru_pengampu')->references('uuid')->on('gurus')->nullOnDelete();
        });

        Schema::table('ujian_soal', function (Blueprint $table) {
            // Hanya relevan utk tipe mcq_complex & match: all_or_nothing (skor penuh cuma kalau
            // 100% benar) vs proporsional (skor sesuai jumlah yg benar). Diabaikan tipe lain.
            $table->string('skor_mode', 16)->default('all_or_nothing')->after('meta');
        });

        Schema::table('bank_soal', function (Blueprint $table) {
            // Ikut ditambahkan di sini (bukan cuma ujian_soal) krn UjianSoalController::
            // sisipkanDariBank()/simpanKeBank() menyalin field mentah2 antar kedua tabel ini —
            // kalau cuma satu sisi yg punya kolom, pengaturan skor_mode hilang diam2 tiap kali
            // soal disisipkan dari/ke bank.
            $table->string('skor_mode', 16)->default('all_or_nothing')->after('meta');
        });

        // PENTING: soal tipe 'match' yg SUDAH ADA (dibuat sebelum toggle skor_mode ini ada)
        // secara historis SELALU dinilai proporsional (satu2nya perilaku yg pernah ada di
        // UjianGrader sblm migration ini). Default kolom di atas ('all_or_nothing') akan diam2
        // MENGUBAH perilaku skor soal match lama kalau tak di-backfill di sini — mcq_complex
        // TIDAK perlu backfill krn perilaku lamanya (all_or_nothing) SAMA dgn default kolom.
        DB::table('ujian_soal')->where('tipe', 'match')->update(['skor_mode' => 'proporsional']);
        DB::table('bank_soal')->where('tipe', 'match')->update(['skor_mode' => 'proporsional']);
    }

    public function down(): void
    {
        Schema::table('bank_soal', function (Blueprint $table) {
            $table->dropColumn('skor_mode');
        });

        Schema::table('ujian_soal', function (Blueprint $table) {
            $table->dropColumn('skor_mode');
        });

        Schema::table('ujian_kelas', function (Blueprint $table) {
            $table->dropForeign(['id_guru_pengampu']);
            $table->dropColumn('id_guru_pengampu');
        });
    }
};
