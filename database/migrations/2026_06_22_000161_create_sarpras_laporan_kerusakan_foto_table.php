<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_laporan_kerusakan_foto', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->foreignUuid('laporan_id')
                ->constrained('sarpras_laporan_kerusakan')->cascadeOnDelete();
            // Path relatif foto TERKOMPRES (<=2MB) di disk public.
            $table->string('foto_path');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_laporan_kerusakan_foto');
    }
};
