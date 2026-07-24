<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Paksaan variasi sudut (5 zona: tengah/kiri/kanan/atas/bawah) + posisi wajah di dalam
 * lingkaran panduan saat registrasi wajah — logikanya murni JS/Alpine (butuh kamera
 * sungguhan utk diuji end-to-end), jadi di level PHPUnit cuma dijaga lewat pemeriksaan
 * SOURCE CODE supaya potongan penting tidak sengaja terhapus lain waktu. Logika inti
 * (classifyAngle/zoneLabel/checkFramed) SATU tempat di partials/_face_enroll_shared.blade.php
 * (dulu disalin manual ke 3 file, rawan ketinggalan sinkron) — 3 halaman registrasi cuma
 * meng-include & meng-Object.assign partial ini ke state masing2.
 */
class FaceAngleDiversityTest extends TestCase
{
    public static function halamanRegistrasiProvider(): array
    {
        return [
            'siswa'   => ['absensi/wajah.blade.php', 'faceEnroll'],
            'guru'    => ['absensi/wajah-guru.blade.php', 'faceEnrollGuru'],
            'sendiri' => ['face/self.blade.php', 'selfEnroll'],
        ];
    }

    public function test_logika_inti_variasi_sudut_ada_di_partial_bersama(): void
    {
        $source = file_get_contents(resource_path('views/absensi/_face_enroll_shared.blade.php'));

        // 5 zona sudut: tengah/kiri/kanan/atas/bawah.
        $this->assertStringContainsString("const ANGLE_ZONES = ['tengah','kiri','kanan','atas','bawah'];", $source);
        $this->assertStringContainsString('function faceEnrollShared()', $source);

        // Klasifikasi zona dari yaw+pitch bertanda (proksi InsightFace pakai 5 landmark).
        $this->assertStringContainsString('classifyAngle(signedYaw, signedPitch)', $source);
        $this->assertStringContainsString("return 'tengah'", $source);
        $this->assertStringContainsString("signedPitch < 0 ? 'atas' : 'bawah'", $source);

        // State awal 5 zona.
        $this->assertStringContainsString('zoneCounts:{tengah:0, kiri:0, kanan:0, atas:0, bawah:0}', $source);

        // Cek posisi/jarak wajah di dalam lingkaran panduan — dihitung terhadap area video yg
        // BENAR2 terlihat (clientWidth/clientHeight, bukan videoWidth/videoHeight penuh) supaya
        // konsisten dgn crop CSS object-cover yg dipakai elemen <video>-nya.
        $this->assertStringContainsString('checkFramed(face, video)', $source);
        $this->assertStringContainsString('video.clientWidth || vw', $source);
        $this->assertStringContainsString('Posisikan wajah di tengah lingkaran panduan', $source);
        $this->assertStringContainsString('Wajah terlalu dekat ke kamera', $source);
    }

    #[DataProvider('halamanRegistrasiProvider')]
    public function test_halaman_registrasi_pakai_partial_bersama_dan_paksaan_zona(string $view, string $factoryFn): void
    {
        $source = file_get_contents(resource_path('views/'.$view));

        // Include partial bersama & gabungkan ke state via Object.assign (bukan salin ulang logika).
        $this->assertStringContainsString("@include('absensi._face_enroll_shared')", $source);
        $this->assertStringContainsString("function {$factoryFn}(opts){", $source);
        $this->assertStringContainsString('return Object.assign(faceEnrollShared(), {', $source);

        // Logika lama yg dulu disalin manual per file HARUS sudah tidak ada lagi di sini.
        $this->assertStringNotContainsString("const ANGLE_ZONES = ['tengah','kiri','kanan','atas','bawah'];", $source);
        $this->assertStringNotContainsString('classifyAngle(signedYaw, signedPitch){', $source);

        // Batas real-time: maks 1 sampel diterima per zona (5 zona x 1 = pas 5 target sampel).
        $this->assertStringContainsString('this.zoneCounts[zone]||0) >= 1', $source);
        $this->assertStringContainsString('Sudah dapat sampel dari sisi', $source);

        // Guard simpan: wajib ke-5 zona terisi.
        $this->assertStringContainsString('ANGLE_ZONES.filter(z => !this.zoneCounts[z])', $source);
        $this->assertStringContainsString('Variasi sudut belum lengkap', $source);
    }

    public function test_zone_counts_direset_tiap_kali_modal_kamera_dibuka_ulang(): void
    {
        // face/self.blade.php sengaja dikecualikan — halaman itu sekali-pakai per sesi (bukan
        // modal yg dibuka-tutup berulang utk siswa/guru berbeda), jadi tak butuh reset eksplisit.
        foreach (['absensi/wajah.blade.php', 'absensi/wajah-guru.blade.php'] as $view) {
            $source = file_get_contents(resource_path('views/'.$view));
            $this->assertStringContainsString('this.zoneCounts={tengah:0, kiri:0, kanan:0, atas:0, bawah:0};', $source);
        }
    }
}
