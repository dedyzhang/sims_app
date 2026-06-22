<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Anggota Ruang Kelas (siswa / asisten). Auto-enroll dari rombel terpilih. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_members', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('classroom_id');
            $table->uuid('user_id');
            $table->enum('role_in_class', ['siswa', 'asisten'])->default('siswa');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->unique(['classroom_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_members');
    }
};
