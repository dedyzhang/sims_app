<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Siapa saja yang benar-benar "masuk" (membuka halaman) satu sesi live — dipakai sbg penyebut
 * utk auto-advance "semua yang hadir sudah menjawab". Beda dari classroom_members (anggota
 * kelas secara umum): baris ini cuma ada kalau siswa itu benar2 membuka halaman live session ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_live_participants', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('session_id');
            $table->uuid('user_id');
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('uuid')->on('game_live_sessions')->cascadeOnDelete();
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->unique(['session_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_live_participants');
    }
};
