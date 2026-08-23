<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_jadwal', function (Blueprint $table) {
            $table->uuid('id_sesi')->nullable()->after('id_ujian');
            $table->foreign('id_sesi')->references('uuid')->on('ujian_sesi')->nullOnDelete();
        });

        // Backfill: kelompokkan baris ujian_jadwal yg ada SEKARANG jadi ujian_sesi.
        // Kunci kelompok = (id_ujian_paket, tanggal, sesi_label, jam_mulai, jam_selesai)
        // — DISTINCT memperlakukan NULL sbg setara utk grouping SQL standar.
        $grup = DB::table('ujian_jadwal')
            ->select('id_ujian_paket', 'tanggal', 'sesi_label', 'jam_mulai', 'jam_selesai')
            ->distinct()
            ->get();

        foreach ($grup as $g) {
            $idSesi = (string) Str::orderedUuid();

            DB::table('ujian_sesi')->insert([
                'uuid' => $idSesi,
                'id_ujian_paket' => $g->id_ujian_paket,
                'tanggal' => $g->tanggal,
                'jam_mulai' => $g->jam_mulai,
                'jam_selesai' => $g->jam_selesai,
                'label' => $g->sesi_label,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('ujian_jadwal')
                ->where('id_ujian_paket', $g->id_ujian_paket)
                ->where('tanggal', $g->tanggal)
                ->where('jam_mulai', $g->jam_mulai)
                ->where('jam_selesai', $g->jam_selesai)
                ->when(
                    $g->sesi_label === null,
                    fn ($q) => $q->whereNull('sesi_label'),
                    fn ($q) => $q->where('sesi_label', $g->sesi_label)
                )
                ->update(['id_sesi' => $idSesi]);
        }
    }

    public function down(): void
    {
        Schema::table('ujian_jadwal', function (Blueprint $table) {
            $table->dropForeign(['id_sesi']);
            $table->dropColumn('id_sesi');
        });
    }
};
