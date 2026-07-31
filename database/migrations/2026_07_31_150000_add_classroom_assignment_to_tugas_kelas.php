<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tautan ke tugas Ruang Kelas (student-facing) yang dibuat otomatis saat tugas
        // dikonfirmasi — supaya siswa benar-benar menerima penugasan, bukan cuma tercatat
        // di Buku Agenda (guru-facing). Nullable: slot tanpa id_kelas/id_pelajaran (mis.
        // jam Istirahat/Upacara) tidak punya Ruang Kelas untuk dituju.
        Schema::table('tugas_kelas', function (Blueprint $table) {
            $table->string('id_classroom_assignment', 36)->nullable()->after('id_agenda');
        });
    }

    public function down(): void
    {
        Schema::table('tugas_kelas', function (Blueprint $table) {
            $table->dropColumn('id_classroom_assignment');
        });
    }
};
