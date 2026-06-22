<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Komentar untuk materi & latihan/tugas (polymorphic). Mendukung balasan 1 level
 * (parent_id self-FK), mirip diskusi forum. UUID semua.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_comments', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuidMorphs('commentable');     // commentable_id (char36) + commentable_type
            $table->uuid('user_id');
            $table->uuid('parent_id')->nullable();
            $table->text('body');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('user_id')->references('uuid')->on('users')->cascadeOnDelete();
            $table->foreign('parent_id')->references('uuid')->on('classroom_comments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_comments');
    }
};
