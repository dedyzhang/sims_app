<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Library pengenalan wajah (Human.js) dimuat via CDN jsdelivr TANPA versi (mengambang) di
 * semua 5 halaman yg memuatnya — sama seperti pola Alpine.js yg sempat dicurigai jadi biang
 * kerok "total gagal render" di sesi lain. Untuk Human.js risikonya beda: embedding wajah yg
 * TERSIMPAN saat registrasi (mis. di hosting, sudah lama) bisa TIDAK COCOK lagi dgn embedding
 * yg dihitung live sekarang kalau versi library berubah di antara kedua momen itu — hasilnya
 * "wajah tak pernah dikenali" walau kamera & jumlah wajah terdaftar normal (persis laporan
 * user: lokal terbaca krn data baru diregistrasi hari ini dgn versi skrg, hosting tidak krn
 * data lama diregistrasi dgn versi yg mungkin berbeda). Dipin ke versi tetap (3.3.6) di SEMUA
 * halaman (scan kiosk + 3 halaman registrasi wajah + izin pulang guru) agar versi yg dipakai
 * utk REGISTRASI dan utk SCAN LIVE selalu identik ke depannya.
 */
class HumanJsVersionPinnedTest extends TestCase
{
    private const FILES = [
        'absensi/scan.blade.php',
        'absensi/wajah.blade.php',
        'absensi/wajah-guru.blade.php',
        'face/self.blade.php',
        'presensi_guru/self.blade.php',
    ];

    public function test_semua_halaman_memuat_human_js_versi_terpin_bukan_mengambang(): void
    {
        foreach (self::FILES as $file) {
            $source = file_get_contents(resource_path('views/'.$file));
            $this->assertStringContainsString(
                '@vladmandic/human@3.3.6/dist/human.js',
                $source,
                "Human.js di {$file} harus dipin ke versi tetap, bukan mengambang."
            );
            $this->assertStringNotContainsString(
                '@vladmandic/human/dist/human.js',
                $source,
                "Human.js di {$file} tidak boleh lagi memakai URL tanpa versi (mengambang)."
            );
        }
    }
}
