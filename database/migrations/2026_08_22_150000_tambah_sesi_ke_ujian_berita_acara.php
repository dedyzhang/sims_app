<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Berita acara SEBELUMNYA satu per (ruangan, tanggal) — tapi satu hari bisa ada
 * beberapa SESI ujian berbeda mapel di ruangan yg sama (lihat multi-mapel-per-
 * ruangan di UjianRuanganMonitorController::poll()), jadi tiap sesi butuh berita
 * acaranya sendiri. Tambah `id_ujian` (sesi mana) + `id_guru_pengawas` (otomatis
 * terisi dari siapa yg scan QR ruangan saat sesi itu berlangsung, lihat
 * UjianRuanganScanController), ganti unique jadi per (ruangan, ujian, tanggal).
 *
 * `id_ujian` SENGAJA nullable (bukan required) — ada 1 baris berita_acara nyata
 * yg sudah dibuat SEBELUM fitur sesi ini ada (belum py sesi tercatat); baris lama
 * itu dibiarkan apa adanya (id_ujian tetap NULL), validasi "wajib pilih sesi"
 * cukup di level form utk pengisian BARU, tak perlu maksa isi data lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_berita_acara', function (Blueprint $table) {
            $table->dropForeign(['id_ruangan']);
        });

        Schema::table('ujian_berita_acara', function (Blueprint $table) {
            $table->dropUnique(['id_ruangan', 'tanggal']);
        });

        Schema::table('ujian_berita_acara', function (Blueprint $table) {
            $table->uuid('id_ujian')->nullable()->after('id_ruangan');
            $table->uuid('id_guru_pengawas')->nullable()->after('diisi_oleh');

            $table->foreign('id_ruangan')->references('uuid')->on('ujian_ruangan')->cascadeOnDelete();
            $table->foreign('id_ujian')->references('uuid')->on('ujians')->nullOnDelete();
            $table->foreign('id_guru_pengawas')->references('uuid')->on('gurus')->nullOnDelete();

            $table->unique(['id_ruangan', 'id_ujian', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::table('ujian_berita_acara', function (Blueprint $table) {
            $table->dropForeign(['id_ruangan']);
        });

        Schema::table('ujian_berita_acara', function (Blueprint $table) {
            $table->dropUnique(['id_ruangan', 'id_ujian', 'tanggal']);
            $table->dropForeign(['id_ujian']);
            $table->dropForeign(['id_guru_pengawas']);
            $table->dropColumn(['id_ujian', 'id_guru_pengawas']);
        });

        Schema::table('ujian_berita_acara', function (Blueprint $table) {
            $table->foreign('id_ruangan')->references('uuid')->on('ujian_ruangan')->cascadeOnDelete();
            $table->unique(['id_ruangan', 'tanggal']);
        });
    }
};
