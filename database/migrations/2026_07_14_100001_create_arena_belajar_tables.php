<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arena Belajar Fase 1 — bank soal & kuis async.
 * Sibling Ruang Kelas; tidak mengubah classroom_assignments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_quizzes', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('classroom_id');
            $table->uuid('created_by');

            $table->string('title');
            $table->longText('instructions')->nullable();
            $table->string('mode', 16)->default('async'); // async|live
            $table->string('scoring_mode', 16)->default('accuracy'); // accuracy|competitive
            $table->unsignedInteger('max_score')->default(100);
            $table->boolean('hide_scores')->default(false);
            $table->boolean('show_leaderboard')->default(false);
            $table->boolean('instant_feedback')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->string('access_token', 16)->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 16)->default('draft'); // draft|published|closed

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnDelete();
            $table->index(['classroom_id', 'status']);
        });

        Schema::create('game_questions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('quiz_id');
            $table->string('type', 24)->default('mcq'); // mcq|true_false|short_answer|match
            $table->text('question_text');
            $table->unsignedInteger('points')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->text('explanation')->nullable();
            $table->timestamps();

            $table->foreign('quiz_id')->references('uuid')->on('game_quizzes')->cascadeOnDelete();
            $table->index(['quiz_id', 'sort_order']);
        });

        Schema::create('game_question_options', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('question_id');
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('question_id')->references('uuid')->on('game_questions')->cascadeOnDelete();
            $table->index(['question_id', 'sort_order']);
        });

        Schema::create('game_quiz_assignments', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('quiz_id');
            $table->uuid('classroom_id');
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status', 16)->default('open'); // open|closed
            $table->timestamps();

            $table->foreign('quiz_id')->references('uuid')->on('game_quizzes')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->unique(['quiz_id', 'classroom_id']);
        });

        Schema::create('game_attempts', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('assignment_id');
            $table->uuid('student_id'); // users.uuid
            $table->unsignedInteger('total_score')->default(0);
            $table->unsignedInteger('correct_count')->default(0);
            $table->string('status', 16)->default('in_progress'); // in_progress|submitted|graded
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->foreign('assignment_id')->references('uuid')->on('game_quiz_assignments')->cascadeOnDelete();
            $table->foreign('student_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->unique(['assignment_id', 'student_id']);
        });

        Schema::create('game_answers', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->uuid('selected_option_id')->nullable();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedInteger('points_awarded')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->foreign('attempt_id')->references('uuid')->on('game_attempts')->cascadeOnDelete();
            $table->foreign('question_id')->references('uuid')->on('game_questions')->cascadeOnDelete();
            $table->foreign('selected_option_id')->references('uuid')->on('game_question_options')->nullOnDelete();
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_answers');
        Schema::dropIfExists('game_attempts');
        Schema::dropIfExists('game_quiz_assignments');
        Schema::dropIfExists('game_question_options');
        Schema::dropIfExists('game_questions');
        Schema::dropIfExists('game_quizzes');
    }
};
