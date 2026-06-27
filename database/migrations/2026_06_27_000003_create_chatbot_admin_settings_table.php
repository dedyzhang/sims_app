<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chatbot_admin_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('admin_user_id')->unique();
            $table->boolean('notif_enabled')->default(true);
            $table->boolean('sound_enabled')->default(true);
            $table->timestamps();

            $table->foreign('admin_user_id')->references('uuid')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chatbot_admin_settings');
    }
};
