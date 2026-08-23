<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ganti ujian_berita_acara dari "1 baris = 1 mapel (id_ujian tunggal)" jadi
 * "1 baris = 1 sesi (id_sesi), bisa mencakup BEBERAPA mapel via tabel pivot
 * ujian_berita_acara_ujian" — supaya guru bisa centang multi-mapel dlm 1 modal
 * & tercetak sbg SATU dokumen Berita Acara gabungan (keputusan produk: bukan
 * banyak dokumen terpisah per mapel).
 *
 * IRREVERSIBLE PASCA GO-LIVE: down() best-effort ambil id_ujian PERTAMA saja
 * dari tiap grup pivot kalau sudah ada BA yg py >1 mapel tercentang (dibuat
 * lewat modal baru) — mapel lainnya HILANG saat rollback. Jangan rollback
 * migrasi ini setelah fitur modal dipakai sungguhan di produksi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_berita_acara', function (Blueprint $table) {
            $table->uuid('id_sesi')->nullable()->after('id_ujian');
            $table->foreign('id_sesi')->references('uuid')->on('ujian_sesi')->nullOnDelete();
        });

        // Backfill id_sesi: baris legacy (id_ujian=NULL, predates fitur sesi) DIBIARKAN
        // id_sesi=NULL juga — tak ada cara aman menebak sesi mana yg dimaksud.
        foreach (DB::table('ujian_berita_acara')->whereNotNull('id_ujian')->get() as $row) {
            $idPaket = DB::table('ujian_ruangan')->where('uuid', $row->id_ruangan)->value('id_ujian_paket');

            $idSesi = DB::table('ujian_jadwal')
                ->where('id_ujian_paket', $idPaket)
                ->where('id_ujian', $row->id_ujian)
                ->whereDate('tanggal', $row->tanggal)
                ->orderBy('uuid')
                ->value('id_sesi');

            if ($idSesi) {
                DB::table('ujian_berita_acara')->where('uuid', $row->uuid)->update(['id_sesi' => $idSesi]);
            }
        }

        Schema::create('ujian_berita_acara_ujian', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_berita_acara');
            $table->uuid('id_ujian');
            $table->timestamps();

            $table->foreign('id_berita_acara')->references('uuid')->on('ujian_berita_acara')->cascadeOnDelete();
            $table->foreign('id_ujian')->references('uuid')->on('ujians')->cascadeOnDelete();
            $table->unique(['id_berita_acara', 'id_ujian']);
        });

        foreach (DB::table('ujian_berita_acara')->whereNotNull('id_ujian')->get() as $row) {
            DB::table('ujian_berita_acara_ujian')->insert([
                'uuid' => (string) Str::orderedUuid(),
                'id_berita_acara' => $row->uuid,
                'id_ujian' => $row->id_ujian,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('ujian_berita_acara', function (Blueprint $table) {
            $table->dropForeign(['id_ruangan']);
            $table->dropUnique(['id_ruangan', 'id_ujian', 'tanggal']);
            $table->dropForeign(['id_ujian']);
            $table->dropColumn('id_ujian');

            $table->foreign('id_ruangan')->references('uuid')->on('ujian_ruangan')->cascadeOnDelete();
            $table->unique(['id_ruangan', 'id_sesi']);
        });
    }

    public function down(): void
    {
        Schema::table('ujian_berita_acara', function (Blueprint $table) {
            $table->dropForeign(['id_ruangan']);
            $table->dropUnique(['id_ruangan', 'id_sesi']);
            $table->uuid('id_ujian')->nullable()->after('id_ruangan');
            
            $table->foreign('id_ruangan')->references('uuid')->on('ujian_ruangan')->cascadeOnDelete();
            $table->foreign('id_ujian')->references('uuid')->on('ujians')->nullOnDelete();
            $table->unique(['id_ruangan', 'id_ujian', 'tanggal']);
        });

        // Best-effort, LOSSY kalau ada BA multi-mapel — ambil id_ujian pertama per grup.
        $pivotRows = DB::table('ujian_berita_acara_ujian')->orderBy('created_at')->get()->groupBy('id_berita_acara');
        foreach ($pivotRows as $idBa => $grup) {
            DB::table('ujian_berita_acara')->where('uuid', $idBa)->update(['id_ujian' => $grup->first()->id_ujian]);
        }

        Schema::dropIfExists('ujian_berita_acara_ujian');

        Schema::table('ujian_berita_acara', function (Blueprint $table) {
            $table->dropForeign(['id_sesi']);
            $table->dropColumn('id_sesi');
            $table->unique(['id_ruangan', 'id_ujian', 'tanggal']);
        });
    }
};
