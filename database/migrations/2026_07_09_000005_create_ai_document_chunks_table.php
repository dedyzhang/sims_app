<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Potongan teks dokumen + embedding (FASE 5). embedding disimpan JSON (SQLite);
| cosine dihitung manual di PHP (RagService). Bila kelak pindah PostgreSQL,
| kolom ini bisa diganti pgvector.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_document_chunks', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('document_id');
            $table->unsignedInteger('ord')->default(0); // urutan chunk dalam dokumen
            $table->text('content');
            $table->json('embedding')->nullable();      // float[] JSON
            $table->timestamps();

            $table->foreign('document_id')->references('uuid')->on('ai_documents')->cascadeOnDelete();
            $table->index('document_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_document_chunks');
    }
};
