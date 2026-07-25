<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| RAG untuk Generator Soal: bedakan dokumen unggahan admin (Dokumen AI) dari
| materi/buku unggahan guru (Asisten Guru), dan lacak berapa kali ingest sebuah
| dokumen ditunda karena kuota harian Gemini habis.
|
| Dokumen lama diberi source 'admin_upload' karena sebelum ini hanya admin,
| kepala sekolah, kurikulum, dan kesiswaan yang bisa mengunggah.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->string('source')->default('admin_upload')->after('file_path');
            $table->unsignedTinyInteger('quota_retries')->default(0)->after('error');

            // Guru hanya mencari di dokumennya sendiri; indeks ini menopang
            // filter (user_uuid, source, status) yang dipakai RagService::search().
            $table->index(['user_uuid', 'source', 'status'], 'ai_documents_owner_scope_index');
        });
    }

    public function down(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->dropIndex('ai_documents_owner_scope_index');
            $table->dropColumn(['source', 'quota_retries']);
        });
    }
};
