<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_penghapusan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->string('kode');
            $table->foreignUuid('aset_id')->constrained('sarpras_aset')->cascadeOnDelete();
            $table->text('alasan');
            $table->enum('metode', ['jual', 'musnah', 'hibah', 'lainnya'])->default('musnah');
            $table->enum('status', ['diajukan', 'disetujui', 'ditolak'])->default('diajukan');
            $table->foreignUuid('diajukan_oleh')->constrained('users', 'uuid')->cascadeOnDelete();
            $table->foreignUuid('disetujui_oleh')->nullable()
                ->constrained('users', 'uuid')->nullOnDelete();
            $table->timestamp('disetujui_pada')->nullable();
            $table->text('alasan_tolak')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'kode']);
            $table->index(['school_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_penghapusan');
    }
};
