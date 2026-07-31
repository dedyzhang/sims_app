<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penugasan guru pengganti per jam kosong (satu baris per slot jadwal yang kosong).
        Schema::create('penugasan_pengganti', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_guru_tidak_hadir', 36);
            $table->string('id_jadwal', 36);
            $table->string('id_guru_pengganti', 36)->nullable();
            $table->string('id_guru_piket', 36)->nullable(); // terisi bila piket sendiri yang ambil alih
            $table->string('status', 20)->default('menunggu'); // menunggu | ditugaskan | selesai
            $table->timestamps();

            $table->unique(['id_guru_tidak_hadir', 'id_jadwal']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_pengganti');
    }
};
