<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_stok_opname', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->string('periode', 32); // mis. 2026-S1
            $table->string('judul');
            $table->string('status', 32)->default('draft'); // draft, selesai
            $table->foreignUuid('dibuat_oleh')->nullable()->constrained('users', 'uuid')->nullOnDelete();
            $table->timestamp('selesai_pada')->nullable();
            $table->timestamps();
        });

        Schema::create('sarpras_stok_opname_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable()->index();
            $table->foreignUuid('opname_id')->constrained('sarpras_stok_opname')->cascadeOnDelete();
            $table->foreignUuid('aset_id')->constrained('sarpras_aset')->cascadeOnDelete();
            $table->string('kondisi_sistem', 32)->nullable();
            $table->string('kondisi_fisik', 32)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['opname_id', 'aset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_stok_opname_item');
        Schema::dropIfExists('sarpras_stok_opname');
    }
};
