<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materi terkunci: siswa wajib masukkan token guru untuk membuka, lalu masuk mode
 * layar-penuh; keluar layar = otomatis ter-keluar. Tabel baru milik modul ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classroom_materials', function (Blueprint $table) {
            $table->boolean('is_locked')->default(false)->after('is_published');
            $table->string('access_token', 16)->nullable()->after('is_locked');
        });
    }

    public function down(): void
    {
        Schema::table('classroom_materials', function (Blueprint $table) {
            $table->dropColumn(['is_locked', 'access_token']);
        });
    }
};
