<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Pelacakan pemakaian AI per request (FASE 1 — Inti AI Gateway). Menggantikan
| spatie/activitylog: SIMS tidak memakai spatie. Dipakai untuk kontrol biaya
| (perkiraan token) & deteksi penyalahgunaan. Tanpa school_id — SIMS single-school.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('user_uuid')->nullable();
            $table->string('feature');                        // chat, teacher_quiz, analyze, dll
            $table->string('model')->nullable();              // mis. gemini-2.0-flash
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->string('status')->default('success');     // success | rate_limited | error
            $table->timestamps();

            $table->foreign('user_uuid')
                ->references('uuid')->on('users')
                ->nullOnDelete();

            $table->index(['user_uuid', 'created_at']);
            $table->index('feature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
    }
};
