<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_piket', function (Blueprint $table) {
            $table->boolean('is_ketua')->default(false)->after('hari');
            $table->index(['hari', 'is_ketua'], 'jadwal_piket_hari_ketua_index');
        });

        // Pertahankan konfigurasi lama: satu baris pertama per hari kerja menjadi
        // ketua sementara, lalu admin dapat menggantinya dari pengaturan jadwal.
        foreach (range(1, 5) as $hari) {
            $uuid = DB::table('jadwal_piket')
                ->where('hari', $hari)
                ->orderBy('created_at')
                ->orderBy('uuid')
                ->value('uuid');

            if ($uuid) {
                DB::table('jadwal_piket')->where('uuid', $uuid)->update(['is_ketua' => true]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('jadwal_piket', function (Blueprint $table) {
            $table->dropIndex('jadwal_piket_hari_ketua_index');
            $table->dropColumn('is_ketua');
        });
    }
};
