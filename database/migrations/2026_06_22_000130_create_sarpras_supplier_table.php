<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_supplier', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->string('nama');
            $table->string('kontak')->nullable();   // nama PIC
            $table->string('telepon')->nullable();
            $table->string('email')->nullable();
            $table->text('alamat')->nullable();
            $table->string('npwp')->nullable();      // opsional
            $table->timestamps();

            $table->index(['school_id', 'nama']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_supplier');
    }
};
