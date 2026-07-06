<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sarpras_jadwal_pemeliharaan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('school_id')->nullable();
            $table->foreignUuid('aset_id')->nullable()
                ->constrained('sarpras_aset')->nullOnDelete();
            $table->string('nama');
            // Interval dalam hari (mis. 30 = bulanan, 90 = triwulan).
            $table->unsignedInteger('interval_hari')->default(30);
            $table->date('tgl_terakhir')->nullable();
            $table->date('tgl_berikutnya');
            $table->boolean('aktif')->default(true);
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->index(['school_id', 'aktif', 'tgl_berikutnya'], 'sarpras_jadwal_pemeliharaan_school_aktif_tgl_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sarpras_jadwal_pemeliharaan');
    }
};
