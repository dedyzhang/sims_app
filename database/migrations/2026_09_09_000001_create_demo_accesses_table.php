<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_accesses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('source_request_id')->unique();
            $table->string('school_display_name', 160);
            $table->string('contact_name', 160);
            $table->text('contact_email_encrypted');
            $table->string('contact_email_hash', 64)->index();
            $table->string('status', 32)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('first_login_at')->nullable();
            $table->timestamp('onboarding_sent_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('approved_actor', 120)->nullable();
            $table->uuid('correlation_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_accesses');
    }
};
