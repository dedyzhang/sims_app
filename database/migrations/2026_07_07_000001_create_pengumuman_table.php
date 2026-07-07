<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Pengumuman sekolah. Saat dibuat, otomatis mengirim notifikasi (database +
| push FCM) ke user sesuai target_roles (null/[] = semua peran). Dibuat oleh
| user yang punya izin RBAC 'manage_pengumuman'.
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengumuman', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('judul');
            $table->text('isi');
            // Daftar peran sasaran (mis. ["guru","siswa"]). NULL = semua peran.
            $table->json('target_roles')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->foreign('created_by')
                ->references('uuid')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengumuman');
    }
};
