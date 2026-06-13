<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Presensi / kehadiran guru per tanggal (check-in)
        Schema::create('presensi_gurus', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_guru', 36);
            $table->date('tanggal');
            $table->time('jam_masuk')->nullable();
            $table->enum('status', ['hadir', 'izin', 'sakit', 'alpa'])->default('hadir');
            $table->string('keterangan')->nullable();
            $table->string('dicatat_oleh', 36)->nullable(); // user uuid
            $table->timestamps();
            $table->unique(['id_guru', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presensi_gurus');
    }
};
