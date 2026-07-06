<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadikan classroom_lock_events polymorphic agar dipakai materi DAN latihan/tugas.
 * Data lama hanya event pemantauan sementara (uji) → dibuang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('classroom_lock_events');

        Schema::create('classroom_lock_events', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuidMorphs('lockable');         // lockable_type + lockable_id (materi/tugas)
            $table->uuid('student_id');
            $table->enum('type', ['masuk', 'keluar']);
            $table->string('reason')->nullable();
            $table->timestamps();

            $table->foreign('student_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->index(['lockable_type', 'lockable_id', 'created_at'], 'class_lock_events_lockable_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_lock_events');
    }
};
