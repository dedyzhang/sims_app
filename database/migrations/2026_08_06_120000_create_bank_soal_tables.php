<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank Soal — kumpulan soal per-mapel yg bisa dipakai ulang, disisipkan (disalin)
 * ke ujian_soal saat guru menyusun ujian. Struktur SENGAJA identik dgn ujian_soal/
 * ujian_soal_opsi (tipe/meta/opsi sama persis) supaya kode simpan/sisip bisa saling
 * konversi lurus — bedanya cuma di-scope ke id_pelajaran, bukan id_ujian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_soal', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_pelajaran');
            $table->uuid('created_by');

            $table->string('tipe', 16)->default('mcq'); // mcq|mcq_complex|true_false|match|essay
            $table->text('teks_soal');
            $table->unsignedInteger('poin')->default(1);
            $table->unsignedInteger('urutan')->default(0);
            $table->json('meta')->nullable();
            $table->text('penjelasan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_pelajaran')->references('uuid')->on('pelajarans')->cascadeOnDelete();
            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnDelete();
            $table->index(['id_pelajaran', 'urutan']);
        });

        Schema::create('bank_soal_opsi', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_soal');
            $table->text('teks_opsi');
            $table->boolean('is_benar')->default(false);
            $table->unsignedInteger('urutan')->default(0);
            $table->timestamps();

            $table->foreign('id_soal')->references('uuid')->on('bank_soal')->cascadeOnDelete();
            $table->index(['id_soal', 'urutan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_soal_opsi');
        Schema::dropIfExists('bank_soal');
    }
};
