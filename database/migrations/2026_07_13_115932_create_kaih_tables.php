<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 7 Kebiasaan Anak Indonesia Hebat — master pertanyaan (7 slot tetap, teks & opsi bisa dikustomisasi admin/kurikulum).
        Schema::create('kaih_pertanyaan', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->unsignedTinyInteger('urutan');
            $table->string('kebiasaan');   // label singkat, mis. "Bangun Pagi"
            $table->text('pertanyaan');
            $table->boolean('aktif')->default(true);
            $table->timestamps();

            $table->unique('urutan');
        });

        // Opsi pilihan ganda per pertanyaan, tiap opsi punya bobot 1-4.
        Schema::create('kaih_opsi', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_pertanyaan', 36);
            $table->string('label');
            $table->unsignedTinyInteger('bobot');   // 1-4
            $table->unsignedTinyInteger('urutan')->default(0);
            $table->timestamps();

            $table->index('id_pertanyaan');
            $table->foreign('id_pertanyaan')->references('uuid')->on('kaih_pertanyaan')->cascadeOnDelete();
        });

        // Rekap harian per siswa (1 baris per siswa per tanggal).
        Schema::create('kaih_jawaban', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_siswa', 36);
            $table->date('tanggal');
            $table->unsignedTinyInteger('total_skor')->default(0);   // jumlah bobot 7 jawaban (7-28)
            $table->string('status')->default('diisi');              // diisi | dilewati
            $table->string('diisi_oleh', 36)->nullable();            // uuid users — null = siswa isi sendiri, terisi = override admin/walikelas
            $table->text('keterangan')->nullable();                  // alasan dilewati / catatan override
            $table->timestamps();

            $table->unique(['id_siswa', 'tanggal']);
            $table->index('tanggal');
        });

        // Detail jawaban per pertanyaan (7 baris per kaih_jawaban, kosong kalau status=dilewati).
        Schema::create('kaih_jawaban_detail', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('id_jawaban', 36);
            $table->string('id_pertanyaan', 36);
            $table->string('id_opsi', 36);
            $table->unsignedTinyInteger('bobot');   // snapshot bobot saat dijawab (riwayat tak berubah walau opsi diedit admin nanti)
            $table->timestamps();

            $table->index('id_jawaban');
            $table->foreign('id_jawaban')->references('uuid')->on('kaih_jawaban')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kaih_jawaban_detail');
        Schema::dropIfExists('kaih_jawaban');
        Schema::dropIfExists('kaih_opsi');
        Schema::dropIfExists('kaih_pertanyaan');
    }
};
