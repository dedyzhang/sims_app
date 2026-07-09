<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Isi pesan dalam satu percakapan chatbot AI (FASE 2). role = user|assistant.
| token_estimate = perkiraan token untuk pelacakan biaya (bukan uang, int biasa).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('conversation_id');
            $table->string('role');                       // user | assistant
            $table->text('content');
            $table->unsignedInteger('token_estimate')->default(0);
            $table->timestamps();

            $table->foreign('conversation_id')
                ->references('uuid')->on('ai_conversations')
                ->cascadeOnDelete();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
