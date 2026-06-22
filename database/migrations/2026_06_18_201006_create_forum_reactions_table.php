<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Reaksi "Suka" untuk topik ATAU komentar. Unique mencegah dobel. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_reactions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            $table->uuid('comment_id')->nullable();
            $table->uuid('topic_id')->nullable();
            $table->uuid('user_id');
            $table->string('type')->default('suka');

            $table->timestamps();

            $table->foreign('comment_id')->references('uuid')->on('forum_comments')->cascadeOnDelete();
            $table->foreign('topic_id')->references('uuid')->on('forum_topics')->cascadeOnDelete();
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();

            $table->unique(['user_id', 'comment_id']);
            $table->unique(['user_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_reactions');
    }
};
