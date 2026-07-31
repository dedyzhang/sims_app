<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Catatan guru tidak hadir per hari — otomatis dari presensi_gurus atau manual oleh guru piket.
        Schema::create('guru_tidak_hadir', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_guru', 36);
            $table->date('tanggal');
            $table->string('sumber', 20); // otomatis_presensi | manual_piket
            $table->string('alasan', 20); // sakit | izin | dinas_luar | alpa
            $table->text('keterangan')->nullable();
            $table->string('id_presensi_guru', 36)->nullable(); // presensi_gurus.uuid, idempotensi sinkronisasi
            $table->string('dicatat_oleh', 36)->nullable(); // users.uuid, null bila otomatis
            $table->timestamps();

            $table->unique(['id_guru', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guru_tidak_hadir');
    }
};
