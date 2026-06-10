<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->integer('tingkat'); // 7, 8, 9
            $table->string('kelas');   // A, B, C, dll
            $table->timestamps();
        });

        Schema::create('walikelas', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_kelas', 36);
            $table->string('id_guru', 36);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('walikelas');
        Schema::dropIfExists('kelas');
    }
};
