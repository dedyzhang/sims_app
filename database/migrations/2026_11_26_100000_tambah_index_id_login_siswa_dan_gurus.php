<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * siswa.id_login & gurus.id_login dipakai di SETIAP request yg butuh profil (login, header
 * nama, policy ujian), tapi sejak awal tanpa index & tanpa foreign key — MySQL membaca
 * SELURUH tabel tiap kali. Index biasa (bukan unique): data lama tak dijamin bebas duplikat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->index('id_login');
        });
        Schema::table('gurus', function (Blueprint $table) {
            $table->index('id_login');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropIndex(['id_login']);
        });
        Schema::table('gurus', function (Blueprint $table) {
            $table->dropIndex(['id_login']);
        });
    }
};
