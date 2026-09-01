<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sesi "Latihan" Arena Belajar — rehearsal sebelum live sungguhan. State machine PERSIS
 * game_live_sessions (lobby->question->reveal->standings->ended), tapi TERPISAH total dari
 * data live/gradebook asli — lihat game_practice_participants/attempts/answers. join_token
 * inilah yg di-encode QR/barcode di lobi, dipindai tamu (guru/siswa) TANPA login & TANPA
 * perlu jadi anggota kelas. Seluruh tabel game_practice_* sengaja disposable (tak ada
 * softDeletes) — dibersihkan berkala oleh command latihan:bersihkan-sesi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_practice_sessions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('quiz_id');
            $table->uuid('classroom_id');
            $table->uuid('hosted_by');
            $table->string('join_token', 8)->unique();
            $table->string('status', 16)->default('lobby'); // lobby|question|reveal|standings|ended
            $table->uuid('current_question_id')->nullable();
            $table->unsignedInteger('question_index')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('question_started_at')->nullable();
            $table->timestamp('question_deadline_at')->nullable();
            $table->timestamp('phase_started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('quiz_id')->references('uuid')->on('game_quizzes')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->foreign('hosted_by')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreign('current_question_id')->references('uuid')->on('game_questions')->nullOnDelete();
            $table->index(['quiz_id', 'classroom_id', 'status'], 'game_practice_sessions_lookup_index');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_practice_sessions');
    }
};
