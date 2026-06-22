<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Materi pembelajaran. File-nya di tabel terpisah (banyak file per materi). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classroom_materials', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('classroom_id');
            $table->uuid('uploaded_by');

            $table->string('title');
            $table->text('description')->nullable();
            $table->longText('body')->nullable();          // untuk materi bertipe teks
            $table->string('link_url')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_publish_at')->nullable();
            $table->integer('sort_order')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('classroom_id')->references('uuid')->on('classrooms')->cascadeOnDelete();
            $table->foreign('uploaded_by')->references('uuid')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classroom_materials');
    }
};
