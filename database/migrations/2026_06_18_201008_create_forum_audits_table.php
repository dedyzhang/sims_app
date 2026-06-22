<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit log Forum (pengganti spatie/activitylog yang tidak terpasang).
 * Mencatat create/edit/delete/pin/lock + perubahan matriks akses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_audits', function (Blueprint $table) {
            $table->uuid('uuid')->primary();

            $table->uuid('user_id')->nullable();
            $table->string('action');                 // create_topic, edit_topic, delete_topic, pin, unpin, lock, unlock, best_answer, delete_comment, update_access
            $table->string('subject_type')->nullable();
            $table->uuid('subject_id')->nullable();
            $table->text('meta')->nullable();         // JSON ringkas

            $table->timestamps();

            $table->foreign('user_id')->references('uuid')->on('users')->nullOnDelete();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_audits');
    }
};
