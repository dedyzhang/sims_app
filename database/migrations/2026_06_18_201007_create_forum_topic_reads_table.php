<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Penanda baca per user/topik (badge "balasan baru"). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_topic_reads', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            $table->uuid('topic_id');
            $table->uuid('user_id');
            $table->timestamp('last_read_at')->nullable();

            $table->timestamps();

            $table->foreign('topic_id')->references('uuid')->on('forum_topics')->cascadeOnDelete();
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();

            $table->unique(['user_id', 'topic_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_topic_reads');
    }
};
