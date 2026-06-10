<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gurus', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_login')->nullable();
            $table->string('nama');
            $table->string('nik')->nullable();
            $table->string('nip')->nullable();
            $table->enum('jk', ['L', 'P'])->default('L');
            $table->string('tempat_lahir')->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('agama')->nullable();
            $table->text('alamat')->nullable();
            $table->string('tingkat_studi')->nullable();
            $table->string('program_studi')->nullable();
            $table->string('universitas')->nullable();
            $table->string('tahun_tamat')->nullable();
            $table->date('tmt_ngajar')->nullable();
            $table->date('tmt_smp')->nullable();
            $table->string('no_telp')->nullable();
            $table->string('foto')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gurus');
    }
};
