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
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->unsignedTinyInteger('hari')->nullable()->after('uuid')->index(); // 1=Senin..6=Sabtu
        });

        // Konversi jam global (hari null) menjadi jam PER-HARI (Senin..Sabtu),
        // lalu remap jadwal lama agar tetap menunjuk jam hari yang sesuai.
        $templates = DB::table('jam_pelajaran')->whereNull('hari')->orderBy('urutan')->get();
        if ($templates->count() > 0) {
            $now = now();
            $map = [];        // templateUuid => [hari => newUuid]
            $newRows = [];
            foreach (range(1, 6) as $hari) {
                foreach ($templates as $t) {
                    $nu = (string) Str::uuid();
                    $map[$t->uuid][$hari] = $nu;
                    $newRows[] = [
                        'uuid' => $nu, 'hari' => $hari,
                        'jam_ke' => $t->jam_ke, 'jam_mulai' => $t->jam_mulai, 'jam_selesai' => $t->jam_selesai,
                        'jenis' => $t->jenis, 'label' => $t->label, 'urutan' => $t->urutan,
                        'created_at' => $now, 'updated_at' => $now,
                    ];
                }
            }
            foreach (array_chunk($newRows, 200) as $chunk) {
                DB::table('jam_pelajaran')->insert($chunk);
            }

            // Remap jadwals.id_jam (template global → jam hari yang sesuai)
            foreach (DB::table('jadwals')->whereNotNull('id_jam')->get(['uuid', 'hari', 'id_jam']) as $j) {
                $nu = $map[$j->id_jam][$j->hari] ?? null;
                if ($nu) {
                    DB::table('jadwals')->where('uuid', $j->uuid)->update(['id_jam' => $nu]);
                }
            }

            // Hapus template global
            DB::table('jam_pelajaran')->whereNull('hari')->delete();
        }
    }

    public function down(): void
    {
        Schema::table('jam_pelajaran', function (Blueprint $table) {
            $table->dropIndex(['hari']);
            $table->dropColumn('hari');
        });
    }
};
