<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Warna blok ruangan (hex, mis. #059669) untuk mempermudah pembedaan visual
| di denah. Nullable — bila kosong dipakai warna default di sisi aplikasi.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sarpras_denah_ruangan', function (Blueprint $table) {
            $table->string('warna', 7)->nullable()->after('tinggi');
        });
    }

    public function down(): void
    {
        Schema::table('sarpras_denah_ruangan', function (Blueprint $table) {
            $table->dropColumn('warna');
        });
    }
};
