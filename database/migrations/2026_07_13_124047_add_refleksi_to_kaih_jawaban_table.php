<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Refleksi Hari Ini — 1 kolom teks bebas, SELALU tampil di form pengisian (bukan bagian pertanyaan
        // pilihan ganda yang dikustomisasi admin, tidak punya bobot/skor).
        Schema::table('kaih_jawaban', function (Blueprint $table) {
            $table->text('refleksi')->nullable()->after('total_skor');
        });
    }

    public function down(): void
    {
        Schema::table('kaih_jawaban', function (Blueprint $table) {
            $table->dropColumn('refleksi');
        });
    }
};
