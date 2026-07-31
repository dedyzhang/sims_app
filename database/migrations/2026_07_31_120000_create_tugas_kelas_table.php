<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tugas yang didistribusikan ke kelas saat gurunya tidak hadir (upload guru asli / titip manual piket).
        Schema::create('tugas_kelas', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_penugasan_pengganti', 36);
            $table->string('jenis', 25); // upload_guru_asli | titip_manual_piket
            $table->string('judul');
            $table->text('deskripsi');
            $table->string('file_path')->nullable();
            $table->string('file_nama_asli')->nullable();
            $table->string('dibuat_oleh', 36);
            $table->string('id_agenda', 36)->nullable(); // terisi setelah entri agenda dibuat
            $table->timestamps();

            $table->unique('id_penugasan_pengganti');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tugas_kelas');
    }
};
