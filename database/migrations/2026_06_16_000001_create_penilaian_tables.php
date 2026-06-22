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
        // Komponen nilai global (Tugas/UH/UTS/UAS) + bobot %
        Schema::create('komponen_nilai', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->string('nama', 50);
            $table->string('kode', 15)->nullable();      // singkatan utk header tabel
            $table->unsignedTinyInteger('bobot')->default(0); // persen
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        // Nilai per siswa per mapel per komponen per semester
        Schema::create('nilai', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->uuid('id_siswa');
            $table->uuid('id_pelajaran');
            $table->uuid('id_komponen');
            $table->unsignedBigInteger('id_semester')->nullable();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->uuid('dicatat_oleh')->nullable();
            $table->timestamps();

            $table->unique(['id_siswa', 'id_pelajaran', 'id_komponen', 'id_semester'], 'nilai_unik');
            $table->index(['id_pelajaran', 'id_semester']);
        });

        // KKM per mata pelajaran
        if (!Schema::hasColumn('pelajarans', 'kkm')) {
            Schema::table('pelajarans', function (Blueprint $table) {
                $table->unsignedTinyInteger('kkm')->default(75)->after('jp');
            });
        }

        // Seed komponen default (bobot total 100)
        $now = now();
        $default = [
            ['Tugas', 'TGS', 20, 1],
            ['Ulangan Harian', 'UH', 30, 2],
            ['UTS', 'UTS', 20, 3],
            ['UAS', 'UAS', 30, 4],
        ];
        foreach ($default as [$nama, $kode, $bobot, $urut]) {
            DB::table('komponen_nilai')->insert([
                'uuid' => (string) Str::uuid(),
                'nama' => $nama, 'kode' => $kode, 'bobot' => $bobot, 'urutan' => $urut,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai');
        Schema::dropIfExists('komponen_nilai');
        if (Schema::hasColumn('pelajarans', 'kkm')) {
            Schema::table('pelajarans', function (Blueprint $table) {
                $table->dropColumn('kkm');
            });
        }
    }
};
