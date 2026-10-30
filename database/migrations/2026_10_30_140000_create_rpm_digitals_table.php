<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rpm_digitals', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->uuid('user_uuid');
            $table->string('title');
            $table->string('mapel')->nullable();
            $table->string('kelas')->nullable();
            $table->string('semester')->nullable();
            $table->string('alokasi_waktu')->nullable();
            $table->text('topik')->nullable();
            $table->string('source_type', 30)->default('ai_direct'); // ai_direct, pdf_upload, asisten_guru, manual
            $table->string('source_file_name')->nullable();
            $table->longText('content');
            $table->string('status', 30)->default('draft');
            $table->timestamps();

            $table->foreign('user_uuid')->references('uuid')->on('users')->cascadeOnDelete();
            $table->index(['user_uuid', 'created_at']);
            $table->index(['mapel', 'kelas']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rpm_digitals');
    }
};
