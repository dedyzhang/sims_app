<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_susulans', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ujian');
            $table->uuid('id_siswa');
            $table->date('tanggal');
            $table->timestamps();

            $table->foreign('id_ujian')->references('uuid')->on('ujians')->cascadeOnDelete();
            $table->foreign('id_siswa')->references('uuid')->on('siswas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_susulans');
    }
};
