<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_provisioning_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('demo_access_id')->nullable()->index();
            $table->uuid('source_request_id')->index();
            $table->uuid('idempotency_key')->unique();
            $table->char('body_hash', 64);
            $table->string('event_type', 64);
            $table->string('status', 32);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->json('safe_metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_provisioning_events');
    }
};
