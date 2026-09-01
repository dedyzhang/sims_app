<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jadwal (rencana) admin kapan pemilihan boleh mulai/berakhir menerima suara —
 * BEDA dari `dibuka_pada`/`ditutup_pada` yg merekam waktu AKTUAL tombol status
 * diklik. Kalau diisi, jadi gerbang tambahan di atas `status`: walau status
 * sudah 'dibuka', publik tetap ditolak sampai `jadwal_mulai` terlewati (dan
 * otomatis dianggap tertutup begitu `jadwal_selesai` terlewati) — dicek live
 * per-request di OsisVoteController, tanpa cron/scheduled job.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('osis_pemilihan', function (Blueprint $table) {
            $table->timestamp('jadwal_mulai')->nullable()->after('status');
            $table->timestamp('jadwal_selesai')->nullable()->after('jadwal_mulai');
        });
    }

    public function down(): void
    {
        Schema::table('osis_pemilihan', function (Blueprint $table) {
            $table->dropColumn(['jadwal_mulai', 'jadwal_selesai']);
        });
    }
};
