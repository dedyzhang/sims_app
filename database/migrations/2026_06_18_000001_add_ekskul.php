<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nilai Ekskul: mapel tertentu (ditandai admin) dinilai sebagai DESKRIPSI,
     * bisa diisi otomatis dari olahan nilai rapor atau diketik manual.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('pelajarans', 'is_ekskul')) {
            Schema::table('pelajarans', function (Blueprint $table) {
                $table->boolean('is_ekskul')->default(false)->after('kkm');
            });
        }

        Schema::create('nilai_ekskul', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ngajar');
            $table->uuid('id_siswa');
            $table->unsignedBigInteger('id_semester')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
            $table->unique(['id_ngajar', 'id_siswa', 'id_semester'], 'ekskul_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai_ekskul');
        if (Schema::hasColumn('pelajarans', 'is_ekskul')) {
            Schema::table('pelajarans', function (Blueprint $table) {
                $table->dropColumn('is_ekskul');
            });
        }
    }
};
