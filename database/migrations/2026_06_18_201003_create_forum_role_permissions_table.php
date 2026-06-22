<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Matriks izin Forum yang DAPAT DIATUR ADMIN (pengganti spatie/laravel-permission,
 * yang tidak terpasang di app ini — role = kolom users.access).
 * Satu baris = (access-role × permission) beserta status allowed.
 * Policy membaca tabel ini saat runtime (bukan hardcode nama role).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_role_permissions', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('access');         // nilai users.access: admin, guru, siswa, orangtua, kepala, kurikulum, kesiswaan, sapras, ...
            $table->string('permission');     // forum.view.all, forum.view.scope, dst.
            $table->boolean('allowed')->default(true);
            $table->timestamps();

            $table->unique(['access', 'permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_role_permissions');
    }
};
