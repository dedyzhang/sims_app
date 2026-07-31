<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rotasi harian guru piket — acuan siapa yang bertugas pada tanggal tertentu.
        Schema::create('jadwal_piket', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_guru', 36);
            $table->date('tanggal');
            $table->unsignedTinyInteger('semester')->nullable();
            $table->string('status', 20)->default('aktif'); // aktif | ditukar | dibatalkan
            $table->timestamps();

            $table->index(['id_guru', 'tanggal']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_piket');
    }
};
