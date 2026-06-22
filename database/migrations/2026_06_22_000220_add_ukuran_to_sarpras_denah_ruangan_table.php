<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Blok ruangan: tambah ukuran (lebar & tinggi) dalam PERSEN agar ruangan
| dapat digambar sebagai kotak berlabel pada denah (bukan sekadar titik).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sarpras_denah_ruangan', function (Blueprint $table) {
            $table->decimal('lebar', 5, 2)->default(14)->after('pos_y');   // % lebar kanvas
            $table->decimal('tinggi', 5, 2)->default(9)->after('lebar');   // % tinggi kanvas
        });
    }

    public function down(): void
    {
        Schema::table('sarpras_denah_ruangan', function (Blueprint $table) {
            $table->dropColumn(['lebar', 'tinggi']);
        });
    }
};
