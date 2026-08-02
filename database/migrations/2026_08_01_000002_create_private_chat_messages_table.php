<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_chat_messages', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('conversation_id');
            $table->unsignedBigInteger('seq');
            $table->uuid('sender_id');
            $table->string('sender_nama', 80);
            $table->text('body');
            $table->timestamp('deleted_at')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->timestamps();

            $table->foreign('conversation_id')->references('uuid')->on('private_chat_conversations')->cascadeOnDelete();
            $table->foreign('sender_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreign('deleted_by')->references('uuid')->on('users')->nullOnDelete();
            $table->unique(['conversation_id', 'seq']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_chat_messages');
    }
};
