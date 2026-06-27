<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_peminjaman_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->foreignUuid('peminjaman_id')
                ->constrained('sarpras_peminjaman')->cascadeOnDelete();
            $table->foreignUuid('aset_id')->constrained('sarpras_aset')->cascadeOnDelete();
            $table->unsignedInteger('qty')->default(1);
            $table->string('kondisi_pinjam')->nullable();
            $table->string('kondisi_kembali')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_peminjaman_item');
    }
};
