<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Daftar hadir jadi per SESI (bukan lagi 1 baris/siswa/hari) — siswa bisa
 * "Hadir" di sesi 1 tapi beda status di sesi 2 (keputusan produk).
 *
 * Backfill baris lama: cocokkan ke sesi lewat ELIGIBILITY KELAS siswa (query
 * sama persis dgn UjianRuanganMonitorController::jumlahPesertaSeharusnya() —
 * cek ujian_kelas mana yg id_kelas-nya = kelas siswa), BUKAN duplikasi buta ke
 * semua sesi hari itu — lebih akurat drpd asumsi "hadir sepanjang hari = hadir
 * di semua mapel". Kalau eligibility tak ketemu match sama sekali (data
 * ujian_kelas blm lengkap saat itu), fallback duplikasi ke semua sesi hari itu
 * supaya data histori tak hilang dari tampilan baru.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_daftar_hadir', function (Blueprint $table) {
            $table->uuid('id_sesi')->nullable()->after('id_siswa');
            $table->foreign('id_sesi')->references('uuid')->on('ujian_sesi')->nullOnDelete();
        });

        // Drop unique lama DULU (bukan di akhir) — backfill di bawah sengaja bisa
        // insert >1 baris utk (id_ruangan,id_siswa,tanggal) yg sama (duplikasi per
        // sesi), yg akan ditolak constraint lama kalau belum di-drop.
        Schema::table('ujian_daftar_hadir', function (Blueprint $table) {
            $table->dropUnique(['id_ruangan', 'id_siswa', 'tanggal']);
        });

        foreach (DB::table('ujian_daftar_hadir')->get() as $row) {
            $idPaket = DB::table('ujian_ruangan')->where('uuid', $row->id_ruangan)->value('id_ujian_paket');
            $idKelasSiswa = DB::table('siswa')->where('uuid', $row->id_siswa)->value('id_kelas');

            $sesiHariItu = DB::table('ujian_sesi')
                ->where('id_ujian_paket', $idPaket)
                ->whereDate('tanggal', $row->tanggal)
                ->get();

            $sesiCocok = $sesiHariItu->filter(function ($sesi) use ($idKelasSiswa) {
                $idUjianSesi = DB::table('ujian_jadwal')->where('id_sesi', $sesi->uuid)->pluck('id_ujian');

                return DB::table('ujian_kelas')
                    ->whereIn('id_ujian', $idUjianSesi)
                    ->where('id_kelas', $idKelasSiswa)
                    ->exists();
            });

            $target = $sesiCocok->isNotEmpty() ? $sesiCocok : $sesiHariItu;

            $pertama = true;
            foreach ($target as $sesi) {
                if ($pertama) {
                    DB::table('ujian_daftar_hadir')->where('uuid', $row->uuid)->update(['id_sesi' => $sesi->uuid]);
                    $pertama = false;
                } else {
                    $baris = (array) $row;
                    $baris['uuid'] = (string) Str::orderedUuid();
                    $baris['id_sesi'] = $sesi->uuid;
                    DB::table('ujian_daftar_hadir')->insert($baris);
                }
            }
            // $target kosong (hari itu tak py sesi sama sekali) -> id_sesi baris asli tetap NULL.
        }

        Schema::table('ujian_daftar_hadir', function (Blueprint $table) {
            $table->unique(['id_ruangan', 'id_siswa', 'id_sesi']);
        });
    }

    public function down(): void
    {
        Schema::table('ujian_daftar_hadir', function (Blueprint $table) {
            $table->dropUnique(['id_ruangan', 'id_siswa', 'id_sesi']);
            $table->dropForeign(['id_sesi']);
            $table->dropColumn('id_sesi');
        });
        // NOTE: baris duplikat hasil backfill (kalau ada) TIDAK di-dedupe otomatis di sini —
        // unique lama (id_ruangan,id_siswa,tanggal) akan GAGAL re-add kalau masih ada duplikat
        // sisa dari backfill. Perlu DELETE manual duplikat dulu kalau benar2 rollback.
        Schema::table('ujian_daftar_hadir', function (Blueprint $table) {
            $table->unique(['id_ruangan', 'id_siswa', 'tanggal']);
        });
    }
};
