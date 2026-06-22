<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Komentar/balasan. parent_id = self-FK (nested 1 level). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_comments', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            $table->uuid('topic_id');
            $table->uuid('user_id');
            $table->uuid('parent_id')->nullable();

            $table->text('body');
            $table->boolean('is_best_answer')->default(false);
            $table->timestamp('edited_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('topic_id')->references('uuid')->on('forum_topics')->cascadeOnDelete();
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreign('parent_id')->references('uuid')->on('forum_comments')->nullOnDelete();

            $table->index(['topic_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_comments');
    }
};
