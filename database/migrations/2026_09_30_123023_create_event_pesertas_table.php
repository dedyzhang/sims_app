<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('event_pesertas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('event_kegiatan_id');
            $table->uuid('user_id')->nullable();
            $table->json('biodata')->nullable();
            $table->string('qr_token')->unique();
            $table->string('status_kehadiran')->default('belum');
            $table->timestamp('waktu_hadir')->nullable();
            $table->timestamps();

            $table->foreign('event_kegiatan_id')->references('id')->on('event_kegiatans')->cascadeOnDelete();
            $table->foreign('user_id')->references('uuid')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_pesertas');
    }
};
