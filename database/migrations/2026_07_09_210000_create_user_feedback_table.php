<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_feedback', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('user_uuid')->nullable();
            $table->string('category', 30)->index();
            $table->string('status', 30)->default('baru')->index();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('subject', 160);
            $table->text('message');
            $table->string('context_url', 2048)->nullable();
            $table->text('admin_response')->nullable();
            $table->uuid('responded_by')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['user_uuid', 'created_at']);
            $table->index(['status', 'created_at']);

            $table->foreign('user_uuid')
                ->references('uuid')->on('users')
                ->nullOnDelete();

            $table->foreign('responded_by')
                ->references('uuid')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_feedback');
    }
};
