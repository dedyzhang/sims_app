<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            // Kosong/null = berlaku utk SEMUA kelas (perilaku lama, default). Diisi = jam khusus
            // (istirahat/upacara/dll) ini HANYA berlaku utk kelas-kelas tsb; kelas lain tetap
            // punya slot pelajaran biasa pada jam yg sama (jam istirahat per-kelas/bergilir).
            $table->json('kelas_scope')->nullable()->after('label');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->dropColumn('kelas_scope');
        });
    }
};
