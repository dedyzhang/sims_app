<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agenda Rapat / Notulen Rapat — dicatat oleh admin/kurikulum/kepala
        // atau guru yang ditunjuk jadi sekretaris (lihat kolom gurus.sekretaris_rapat).
        Schema::create('rapats', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('judul');
            $table->date('tanggal');
            $table->text('pokok_permasalahan')->nullable();
            $table->text('hasil_rapat')->nullable();
            $table->string('id_pencatat', 36)->nullable(); // gurus.uuid — yang input/terakhir ubah
            $table->timestamps();

            $table->index('tanggal');
        });

        // Guru yang hadir pada satu rapat (pivot murni, menggantikan pola serialize() di app lama).
        // PK auto-increment biasa (bukan uuid) — belongsToMany::sync()/attach() insert lewat
        // query builder mentah, tidak memicu event HasUuids utk mengisi kolom uuid.
        Schema::create('rapat_hadir', function (Blueprint $table) {
            $table->id();
            $table->string('id_rapat', 36);
            $table->string('id_guru', 36);
            $table->timestamps();

            $table->unique(['id_rapat', 'id_guru']);
            $table->index('id_rapat');

            $table->foreign('id_rapat')->references('uuid')->on('rapats')->cascadeOnDelete();
        });

        // Dokumentasi foto rapat — hasil kompresi via FileCompressionService (pola sama classroom_material_files).
        Schema::create('rapat_dokumentasi', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_rapat', 36);

            $table->string('original_name');
            $table->string('stored_name');
            $table->string('path');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size_original')->default(0);
            $table->unsignedBigInteger('size_compressed')->nullable();
            $table->integer('sort_order')->default(0);

            $table->string('id_pengunggah', 36)->nullable();
            $table->timestamps();

            $table->index('id_rapat');

            $table->foreign('id_rapat')->references('uuid')->on('rapats')->cascadeOnDelete();
        });

        // Penunjukan sekretaris rapat: flag global per-guru (sekali ditunjuk, bisa kelola semua rapat) —
        // meniru pola gurus.sekretaris di aplikasi lama (smp_ver5), bukan penunjukan per-rapat.
        Schema::table('gurus', function (Blueprint $table) {
            $table->boolean('sekretaris_rapat')->default(false)->after('face_photo');
        });
    }

    public function down(): void
    {
        Schema::table('gurus', function (Blueprint $table) {
            $table->dropColumn('sekretaris_rapat');
        });
        Schema::dropIfExists('rapat_dokumentasi');
        Schema::dropIfExists('rapat_hadir');
        Schema::dropIfExists('rapats');
    }
};
