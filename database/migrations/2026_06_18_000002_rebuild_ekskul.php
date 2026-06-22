<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ekskul = entitas tersendiri (punya pembina/guru), nilainya berupa DESKRIPSI.
     *  - id_pelajaran NULL → ketik manual.
     *  - id_pelajaran ada  → deskripsi diolah dari nilai rapor mapel itu
     *    (predikat, capaian positif "namun" capaian negatif).
     * Mengganti pendekatan lama (pelajarans.is_ekskul + nilai_ekskul).
     */
    public function up(): void
    {
        Schema::dropIfExists('nilai_ekskul');
        if (Schema::hasColumn('pelajarans', 'is_ekskul')) {
            Schema::table('pelajarans', fn (Blueprint $t) => $t->dropColumn('is_ekskul'));
        }

        Schema::create('ekskul', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('nama', 100);
            $table->uuid('id_guru')->nullable();        // pembina
            $table->uuid('id_pelajaran')->nullable();   // jika diisi → ambil dari rapor mapel ini
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('ekskul_siswa', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_ekskul');
            $table->uuid('id_siswa');
            $table->unsignedBigInteger('id_semester')->nullable();
            $table->text('deskripsi')->nullable();
            $table->timestamps();
            $table->unique(['id_ekskul', 'id_siswa', 'id_semester'], 'ekskul_siswa_unik');
            $table->index(['id_ekskul', 'id_semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekskul_siswa');
        Schema::dropIfExists('ekskul');
    }
};
