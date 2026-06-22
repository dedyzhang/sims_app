<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda rapor sudah dikonfirmasi (terkunci) per penugasan + semester.
     * Selama record ada → semua nilai (formatif/sumatif/PAS/rapor/materi) terkunci.
     */
    public function up(): void
    {
        Schema::create('rapor_konfirmasi', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ngajar');
            $table->unsignedBigInteger('id_semester')->nullable();
            $table->uuid('dikonfirmasi_oleh')->nullable();
            $table->timestamps();
            $table->unique(['id_ngajar', 'id_semester'], 'rapor_konf_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rapor_konfirmasi');
    }
};
