<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Log masuk/keluar siswa pada materi terkunci (untuk pemantauan & notifikasi guru). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_lock_events', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('material_id');
            $table->uuid('student_id');
            $table->enum('type', ['masuk', 'keluar']);
            $table->string('reason')->nullable();   // mis. "keluar layar penuh", "pindah tab"
            $table->timestamps();

            $table->foreign('material_id')->references('uuid')->on('classroom_materials')->cascadeOnDelete();
            $table->foreign('student_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->index(['material_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_lock_events');
    }
};
