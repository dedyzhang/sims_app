<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Skor per-peserta Latihan — langsung ke session_id+participant_id, SENGAJA tanpa lewat
 * game_quiz_assignments (itu ada utk due-date/gradebook real, tak relevan utk rehearsal
 * sekali-pakai) — sekaligus memastikan data latihan tak pernah bisa ke-JOIN ke query
 * gradebook/hasil asli manapun.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_practice_attempts', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('session_id');
            $table->uuid('participant_id');
            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->string('status', 16)->default('in_progress'); // in_progress|submitted
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->foreign('session_id')->references('uuid')->on('game_practice_sessions')->cascadeOnDelete();
            $table->foreign('participant_id')->references('uuid')->on('game_practice_participants')->cascadeOnDelete();
            $table->unique(['session_id', 'participant_id'], 'game_practice_attempts_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_practice_attempts');
    }
};
