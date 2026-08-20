<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_teacher_audio_assets', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('user_uuid');
            $table->string('source_type', 30);
            $table->uuid('source_uuid')->nullable();
            $table->string('title', 180);
            $table->longText('text_snapshot');
            $table->string('text_hash', 64);
            $table->string('language', 20)->default('id-ID');
            $table->string('voice', 60);
            $table->string('style_prompt', 120)->nullable();
            $table->string('model', 120);
            $table->string('status', 20)->default('queued');
            $table->string('disk', 40)->default('local');
            $table->string('path')->nullable();
            $table->string('mime', 100)->default('audio/mpeg');
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('user_uuid')->references('uuid')->on('users')->cascadeOnDelete();
            $table->index(['user_uuid', 'created_at']);
            $table->index(['text_hash', 'language', 'voice', 'model']);
            $table->index('status');
        });

        Schema::create('ai_teacher_audio_links', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('audio_uuid');
            $table->string('target_type', 40);
            $table->uuid('target_uuid');
            $table->uuid('created_by');
            $table->timestamps();

            $table->foreign('audio_uuid')->references('uuid')->on('ai_teacher_audio_assets')->cascadeOnDelete();
            $table->foreign('created_by')->references('uuid')->on('users')->cascadeOnDelete();
            $table->unique(['audio_uuid', 'target_type', 'target_uuid'], 'ai_audio_link_unique');
            $table->index(['target_type', 'target_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_teacher_audio_links');
        Schema::dropIfExists('ai_teacher_audio_assets');
    }
};