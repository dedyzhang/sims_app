<?php

namespace App\Support;

use Throwable;

/**
 * Deteksi SATU jenis error spesifik: hosting shared (mis. Hostinger/CloudLinux) menolak
 * koneksi MySQL BARU sesaat saat trafik ramai (max_user_connections/entry-process penuh)
 * — muncul sbg PDOException "SQLSTATE[HY000] [2002] Operation not permitted", terjadi di
 * level KONEKSI (sebelum query apa pun terkirim), BUKAN error SQL biasa. Gangguan
 * sementara, bukan bug — aman diberi kesempatan coba lagi.
 *
 * SENGAJA cek teks pesan spesifik ini (bukan tangkap semua QueryException/kode HY000)
 * supaya error DB lain yg genuinely bug tetap tampil apa adanya, tak ketutup pesan
 * "server sibuk" yg menyesatkan.
 */
class DbBusy
{
    public static function terdeteksi(Throwable $e): bool
    {
        $pesan = $e->getMessage();

        return str_contains($pesan, 'Operation not permitted')
            || str_contains($pesan, 'SQLSTATE[HY000] [2002]');
    }

    public static function pesan(): string
    {
        return 'Server sedang sibuk (banyak yang mengakses bersamaan). Silakan coba lagi dalam beberapa detik.';
    }
}
