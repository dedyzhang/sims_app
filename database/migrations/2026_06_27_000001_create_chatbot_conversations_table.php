<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->enum('mode', ['bot', 'human'])->default('bot');
            $table->enum('status', ['active', 'waiting', 'assigned', 'closed'])->default('active');
            $table->uuid('assigned_admin_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamps();

            // SIMS: pengguna ber-PK users.uuid.
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreign('assigned_admin_id')->references('uuid')->on('users')->nullOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_conversations');
    }
};
