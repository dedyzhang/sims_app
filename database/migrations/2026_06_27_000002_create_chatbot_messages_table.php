<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('conversation_id');
            $table->enum('sender', ['user', 'bot', 'admin']);
            $table->uuid('sender_user_id')->nullable();
            $table->text('body');
            $table->string('matched_intent')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('id')->on('chatbot_conversations')->cascadeOnDelete();
            $table->foreign('sender_user_id')->references('uuid')->on('users')->nullOnDelete();
            // Index untuk endpoint poll: ambil pesan baru per percakapan secara berurutan.
            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_messages');
    }
};
