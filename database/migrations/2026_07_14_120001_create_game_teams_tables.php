<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Arena Belajar Fase 3 — mode tim. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_teams', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('quiz_id');
            $table->uuid('classroom_id');
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('quiz_id')->references('uuid')->on('game_quizzes')->cascadeOnDelete();
            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
        });

        Schema::create('game_team_members', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('team_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->foreign('team_id')->references('uuid')->on('game_teams')->cascadeOnDelete();
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->unique(['team_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_team_members');
        Schema::dropIfExists('game_teams');
    }
};
