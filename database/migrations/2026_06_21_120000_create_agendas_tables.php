<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Agenda harian guru — satu baris per slot jadwal yang diajar pada satu tanggal.
        Schema::create('agendas', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->date('tanggal');
            $table->string('id_jadwal', 36)->nullable();      // jadwals.uuid (slot kelas+mapel+jam)
            $table->string('id_guru', 36);                     // gurus.uuid
            $table->string('id_kelas', 36)->nullable();        // denormalisasi (rekap)
            $table->string('id_pelajaran', 36)->nullable();    // denormalisasi (rekap)
            $table->text('pembahasan')->nullable();
            $table->text('metode')->nullable();
            $table->string('proses')->default('belum');        // belum | selesai
            $table->text('kegiatan')->nullable();
            $table->text('kendala')->nullable();
            $table->string('validasi')->default('belum');      // belum | valid (kepala sekolah)
            $table->text('catatan_kepsek')->nullable();
            $table->unsignedTinyInteger('semester')->default(1);
            $table->timestamps();

            $table->index(['id_guru', 'tanggal']);
            $table->index(['tanggal', 'id_jadwal']);
        });

        // Catatan ketidakhadiran siswa pada agenda (hanya S/I/A — yang tidak hadir).
        Schema::create('agenda_absensi', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_agenda', 36);
            $table->string('id_siswa', 36);
            $table->string('absensi', 2);                      // S | I | A
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->index('id_agenda');
        });

        // Penilaian dimensi Profil Pelajar Pancasila pada agenda.
        Schema::create('agenda_pancasila', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_agenda', 36);
            $table->string('id_guru', 36);
            $table->date('tanggal')->nullable();
            $table->string('id_siswa', 36);
            $table->unsignedTinyInteger('dimensi');            // 1..6
            $table->text('keterangan')->nullable();
            $table->unsignedTinyInteger('semester')->default(1);
            $table->timestamps();

            $table->index('id_agenda');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_pancasila');
        Schema::dropIfExists('agenda_absensi');
        Schema::dropIfExists('agendas');
    }
};
