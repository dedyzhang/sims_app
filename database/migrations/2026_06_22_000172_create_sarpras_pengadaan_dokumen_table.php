<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_pengadaan_dokumen', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->foreignUuid('pengadaan_id')
                ->constrained('sarpras_pengadaan')->cascadeOnDelete();
            $table->string('nama');
            // Path relatif foto/nota TERKOMPRES di disk public.
            $table->string('file_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_pengadaan_dokumen');
    }
};
