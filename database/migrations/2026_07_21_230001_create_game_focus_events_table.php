<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_focus_events', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->foreignUuid('quiz_id')->constrained('game_quizzes', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('classroom_id')->constrained('classrooms', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('student_id')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->string('context', 20); // solo|live|template
            $table->foreignUuid('attempt_id')->nullable()->constrained('game_attempts', 'uuid')->nullOnDelete();
            $table->foreignUuid('session_id')->nullable()->constrained('game_live_sessions', 'uuid')->nullOnDelete();
            $table->string('type', 20)->default('keluar');
            $table->string('reason', 100)->nullable();
            $table->timestamps();

            $table->index(['quiz_id', 'student_id']);
            $table->index(['classroom_id', 'quiz_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_focus_events');
    }
};
