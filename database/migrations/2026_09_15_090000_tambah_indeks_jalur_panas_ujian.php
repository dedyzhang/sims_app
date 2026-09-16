<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Indeks untuk jalur panas CBT/ujian online.
 *
 * Tiga query ini dijalankan berkali-kali per detik selama ujian serentak dan
 * SEMUANYA full table scan sebelum migrasi ini:
 *
 *  1. siswa.id_login   — UjianSiswaController::siswaAtauGagal() memanggil
 *     Siswa::where('id_login', $user->uuid) pada SETIAP request siswa
 *     (buka daftar ujian, gate, start). 300 siswa masuk bersamaan = 300 scan
 *     penuh tabel siswa. Dipakai juga oleh absensi, rapor, dan tagihan SPP.
 *
 *  2. siswa.id_kelas   — UjianRoster::untukKelas() (Pemantauan Live guru, yang
 *     di-poll tiap beberapa detik) dan pencarian anggota kelas di modul lain.
 *
 *  3. ujian_kelas.id_kelas — UjianSiswaController::index() memfilter HANYA pada
 *     id_kelas, sementara satu-satunya indeks yang ada adalah unique
 *     (id_ujian, id_kelas) yang tidak bisa dipakai karena kolom terdepannya
 *     id_ujian (aturan leftmost prefix).
 *
 * MySQL: id_login bertipe VARCHAR(255); di utf8mb4 indeks penuhnya bisa menembus
 * batas panjang kunci pada row format lama, jadi dipakai panjang awalan 36
 * (semua isinya UUID) lewat SQL mentah — Laravel tidak punya API untuk itu.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->tambahIndeks('siswa', 'id_login', 'siswa_id_login_index', 36);
        $this->tambahIndeks('siswa', 'id_kelas', 'siswa_id_kelas_index');
        $this->tambahIndeks('ujian_kelas', 'id_kelas', 'ujian_kelas_id_kelas_index');
    }

    public function down(): void
    {
        $this->hapusIndeks('siswa', 'siswa_id_login_index');
        $this->hapusIndeks('siswa', 'siswa_id_kelas_index');
        $this->hapusIndeks('ujian_kelas', 'ujian_kelas_id_kelas_index');
    }

    private function tambahIndeks(string $table, string $column, string $name, ?int $prefix = null): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column) || $this->indeksAda($table, $name)) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if ($prefix !== null && in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement("ALTER TABLE `{$table}` ADD INDEX `{$name}` (`{$column}`({$prefix}))");

            return;
        }

        Schema::table($table, fn ($t) => $t->index($column, $name));
    }

    private function hapusIndeks(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! $this->indeksAda($table, $name)) {
            return;
        }

        Schema::table($table, fn ($t) => $t->dropIndex($name));
    }

    private function indeksAda(string $table, string $name): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
