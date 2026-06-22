<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel notifications standar Laravel (database channel) untuk notifikasi balasan/mention Forum.
 * uuidMorphs cocok dgn PK users yang berupa uuid.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications')) {
            return;
        }
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->uuidMorphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
