<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Jawaban per-soal Latihan — mirror game_answers persis, cuma attempt_id menunjuk ke game_practice_attempts. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_practice_answers', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('attempt_id');
            $table->uuid('question_id');
            $table->uuid('selected_option_id')->nullable();
            $table->text('answer_text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->unsignedInteger('points_awarded')->default(0);
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();

            $table->foreign('attempt_id')->references('uuid')->on('game_practice_attempts')->cascadeOnDelete();
            $table->foreign('question_id')->references('uuid')->on('game_questions')->cascadeOnDelete();
            $table->foreign('selected_option_id')->references('uuid')->on('game_question_options')->nullOnDelete();
            $table->unique(['attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_practice_answers');
    }
};
