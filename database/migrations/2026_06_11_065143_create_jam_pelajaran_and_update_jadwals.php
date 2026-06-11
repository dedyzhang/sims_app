<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // Master jam pelajaran (baris pada grid jadwal)
        Schema::create('jam_pelajaran', function (Blueprint $table) {
            $table->uuid('uuid')->primary();
            $table->unsignedSmallInteger('jam_ke')->nullable();
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('jenis')->default('pelajaran'); // pelajaran | istirahat
            $table->string('label')->nullable();           // mis. "Istirahat", "Sholat"
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();
        });

        // Hubungkan jadwal ke master jam
        Schema::table('jadwals', function (Blueprint $table) {
            $table->uuid('id_jam')->nullable()->after('hari');
            $table->index(['hari', 'id_jam']);
        });

        // Seed jam default agar grid langsung bisa dipakai
        $now = now();
        $slots = [
            ['ke' => 1, 'm' => '07:00', 's' => '07:40', 'j' => 'pelajaran'],
            ['ke' => 2, 'm' => '07:40', 's' => '08:20', 'j' => 'pelajaran'],
            ['ke' => 3, 'm' => '08:20', 's' => '09:00', 'j' => 'pelajaran'],
            ['ke' => null, 'm' => '09:00', 's' => '09:20', 'j' => 'istirahat', 'l' => 'Istirahat'],
            ['ke' => 4, 'm' => '09:20', 's' => '10:00', 'j' => 'pelajaran'],
            ['ke' => 5, 'm' => '10:00', 's' => '10:40', 'j' => 'pelajaran'],
            ['ke' => 6, 'm' => '10:40', 's' => '11:20', 'j' => 'pelajaran'],
            ['ke' => null, 'm' => '11:20', 's' => '11:50', 'j' => 'istirahat', 'l' => 'Istirahat'],
            ['ke' => 7, 'm' => '11:50', 's' => '12:30', 'j' => 'pelajaran'],
            ['ke' => 8, 'm' => '12:30', 's' => '13:10', 'j' => 'pelajaran'],
        ];
        $rows = [];
        foreach ($slots as $i => $s) {
            $rows[] = [
                'uuid' => (string) Str::uuid(),
                'jam_ke' => $s['ke'],
                'jam_mulai' => $s['m'],
                'jam_selesai' => $s['s'],
                'jenis' => $s['j'],
                'label' => $s['l'] ?? null,
                'urutan' => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('jam_pelajaran')->insert($rows);
    }

    public function down(): void
    {
        Schema::table('jadwals', function (Blueprint $table) {
            $table->dropIndex(['hari', 'id_jam']);
            $table->dropColumn('id_jam');
        });
        Schema::dropIfExists('jam_pelajaran');
    }
};
