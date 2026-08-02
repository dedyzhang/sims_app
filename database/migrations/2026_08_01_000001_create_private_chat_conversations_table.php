<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_chat_conversations', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('participant_one_id');
            $table->uuid('participant_two_id');
            $table->unsignedBigInteger('last_seq')->default(0);
            $table->timestamp('last_message_at')->nullable();
            $table->string('last_message_preview', 160)->nullable();
            $table->unsignedBigInteger('one_read_seq')->default(0);
            $table->unsignedBigInteger('two_read_seq')->default(0);
            $table->timestamps();

            $table->foreign('participant_one_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreign('participant_two_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->unique(['participant_one_id', 'participant_two_id']);
            $table->index(['participant_one_id', 'last_message_at']);
            $table->index(['participant_two_id', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_chat_conversations');
    }
};
