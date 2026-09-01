<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Roster tamu sesi Latihan — SENGAJA tanpa user_id/FK ke users sama sekali (beda dari
 * game_live_participants). Peserta tak login, cuma ketik nama; identitasnya sepanjang sesi
 * itu murni guest_token acak yg dibawa lewat query string (?g=...), bukan cookie/session
 * Laravel — pola sama seperti token per-pemilih di fitur Pemilihan OSIS.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_practice_participants', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('session_id');
            $table->string('guest_name', 60);
            $table->string('claimed_role', 10)->nullable(); // guru|siswa — self-report, tampilan saja
            $table->string('guest_token', 64);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('uuid')->on('game_practice_sessions')->cascadeOnDelete();
            $table->unique(['session_id', 'guest_token'], 'game_practice_participants_token_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_practice_participants');
    }
};
