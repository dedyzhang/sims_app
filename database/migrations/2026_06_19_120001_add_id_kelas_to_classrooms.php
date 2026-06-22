<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Konsep baru: Ruang Kelas otomatis per (kelas, mapel) — guru mengisi materi sesuai
 * jam ngajarnya. Tambah id_kelas tunggal ke classrooms (tabel BARU milik modul ini).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->uuid('id_kelas')->nullable()->after('created_by');
            $table->foreign('id_kelas')->references('uuid')->on('kelas')->nullOnDelete();
            $table->index(['id_kelas', 'id_pelajaran']);
        });
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropForeign(['id_kelas']);
            $table->dropIndex(['id_kelas', 'id_pelajaran']);
            $table->dropColumn('id_kelas');
        });
    }
};
