<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_activity_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->string('aksi', 64);
            $table->string('subjek_tipe', 64)->nullable();
            $table->uuid('subjek_id')->nullable();
            $table->foreignUuid('pelaku_id')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subjek_tipe', 'subjek_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_activity_log');
    }
};
