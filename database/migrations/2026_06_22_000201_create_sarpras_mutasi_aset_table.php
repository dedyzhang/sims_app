<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_mutasi_aset', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->foreignUuid('aset_id')->constrained('sarpras_aset')->cascadeOnDelete();
            $table->foreignUuid('ruangan_asal_id')->nullable()
                ->constrained('sarpras_denah_ruangan')->nullOnDelete();
            $table->foreignUuid('ruangan_tujuan_id')->nullable()
                ->constrained('sarpras_denah_ruangan')->nullOnDelete();
            $table->text('alasan')->nullable();
            $table->date('tgl_mutasi');
            $table->foreignUuid('dilakukan_oleh')->nullable()
                ->constrained('users', 'uuid')->nullOnDelete();
            $table->timestamps();

            $table->index(['school_id', 'aset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_mutasi_aset');
    }
};
