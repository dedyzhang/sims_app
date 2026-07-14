<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Arena Belajar Fase 2 — live session state (polling, bukan WebSocket). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_live_sessions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('quiz_id');
            $table->uuid('classroom_id');
            $table->uuid('hosted_by');
            $table->string('status', 16)->default('idle'); // idle|lobby|question|reveal|ended
            $table->uuid('current_question_id')->nullable();
            $table->unsignedInteger('question_index')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('question_started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->foreign('quiz_id')->references('uuid')->on('game_quizzes')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->foreign('hosted_by')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreign('current_question_id')->references('uuid')->on('game_questions')->nullOnDelete();
            $table->index(['quiz_id', 'classroom_id', 'status']);
        });

        // Template default untuk Fase 3 (kolom lebih awal agar tidak perlu migration terpisah besar)
        Schema::table('game_quizzes', function (Blueprint $table) {
            $table->string('template', 24)->default('quiz')->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('game_quizzes', function (Blueprint $table) {
            $table->dropColumn('template');
        });
        Schema::dropIfExists('game_live_sessions');
    }
};
