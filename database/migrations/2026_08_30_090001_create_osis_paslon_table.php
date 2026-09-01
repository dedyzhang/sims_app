<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Kandidat (ketua+wakil) dlm satu osis_pemilihan. Foto/visi/misi diisi admin via menu Kelola Paslon. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('osis_paslon', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_pemilihan');
            $table->unsignedTinyInteger('nomor_urut');
            $table->string('nama_ketua', 100);
            $table->string('nama_wakil', 100)->nullable();
            $table->string('foto')->nullable();
            $table->text('visi')->nullable();
            $table->text('misi')->nullable(); // satu poin per baris, dirender <ul><li> di halaman publik
            $table->unsignedSmallInteger('urutan_tampil')->default(0);
            $table->timestamps();

            $table->foreign('id_pemilihan')->references('uuid')->on('osis_pemilihan')->cascadeOnDelete();
            $table->unique(['id_pemilihan', 'nomor_urut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('osis_paslon');
    }
};
