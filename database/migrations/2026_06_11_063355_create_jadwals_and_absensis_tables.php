<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Jadwal pelajaran (timetable mingguan per kelas)
        Schema::create('jadwals', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_kelas', 36);
            $table->unsignedTinyInteger('hari');           // 1=Senin ... 6=Sabtu
            $table->unsignedTinyInteger('jam_ke')->nullable();
            $table->time('jam_mulai')->nullable();
            $table->time('jam_selesai')->nullable();
            $table->string('id_pelajaran', 36)->nullable();
            $table->string('id_guru', 36)->nullable();
            $table->string('keterangan')->nullable();      // mis. Istirahat, Upacara
            $table->timestamps();
            $table->index(['id_kelas', 'hari']);
        });

        // Absensi / kehadiran siswa per tanggal
        Schema::create('absensis', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_siswa', 36);
            $table->string('id_kelas', 36)->nullable();
            $table->date('tanggal');
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa'])->default('hadir');
            $table->string('keterangan')->nullable();
            $table->string('dicatat_oleh', 36)->nullable(); // user uuid
            $table->timestamps();
            $table->unique(['id_siswa', 'tanggal']);
            $table->index(['id_kelas', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensis');
        Schema::dropIfExists('jadwals');
    }
};
