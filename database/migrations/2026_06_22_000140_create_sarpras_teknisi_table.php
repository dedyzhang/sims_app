<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_teknisi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->string('nama');
            $table->enum('tipe', ['internal', 'eksternal'])->default('internal');
            $table->string('spesialisasi')->nullable();  // mis. listrik, AC, komputer
            $table->string('telepon')->nullable();
            $table->text('alamat')->nullable();
            // Teknisi internal bisa terhubung ke user (opsional).
            $table->foreignUuid('user_id')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_teknisi');
    }
};
