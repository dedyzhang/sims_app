<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
| Integrasi Peminjaman + Booking Ruangan menjadi SATU pengajuan.
| Menambahkan ruangan (opsional) + rentang waktu pada peminjaman, sehingga
| satu pengajuan dapat memuat ruangan dan/atau aset dalam satu alur persetujuan.
| Kolom ruangan_id sengaja tanpa FK DB (konsisten dgn pola modul, aman di SQLite).
*/
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sarpras_peminjaman', function (Blueprint $table) {
            $table->uuid('ruangan_id')->nullable()->after('peminjam_id');
            $table->dateTime('mulai')->nullable()->after('tgl_kembali_aktual');
            $table->dateTime('selesai')->nullable()->after('mulai');
            $table->index('ruangan_id');
        });
    }

    public function down(): void
    {
        Schema::table('sarpras_peminjaman', function (Blueprint $table) {
            $table->dropIndex(['ruangan_id']);
            $table->dropColumn(['ruangan_id', 'mulai', 'selesai']);
        });
    }
};
