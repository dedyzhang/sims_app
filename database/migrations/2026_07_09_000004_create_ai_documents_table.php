<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Dokumen sumber RAG (FASE 5). Tanpa school_id — SIMS single-school.
| status: pending → processed | failed (hasil ingest/embedding).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_documents', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('user_uuid')->nullable();       // pengunggah
            $table->string('title');
            $table->string('file_path')->nullable();
            $table->string('status')->default('pending'); // pending|processed|failed
            $table->unsignedInteger('chunk_count')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();

            $table->foreign('user_uuid')->references('uuid')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_documents');
    }
};
