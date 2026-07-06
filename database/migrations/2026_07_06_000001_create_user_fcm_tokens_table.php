<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Token FCM per user untuk push notification (FASE 9). Satu user boleh punya
| banyak token (multi-device), maka token yang unik, bukan user_uuid.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_fcm_tokens', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('user_uuid');
            $table->string('token', 512)->unique();
            $table->string('device_type')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_uuid')
                ->references('uuid')->on('users')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_fcm_tokens');
    }
};
