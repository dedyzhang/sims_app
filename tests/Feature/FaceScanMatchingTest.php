<?php

namespace Tests\Feature;

use Tests\TestCase;

class FaceScanMatchingTest extends TestCase
{
    public function test_scan_wajah_memakai_gate_robust_anti_false_positive(): void
    {
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        $this->assertStringContainsString('threshold:0.66', $source);
        $this->assertStringContainsString('confidentThreshold:0.80', $source);
        $this->assertStringContainsString('supportThreshold:0.62', $source);
        $this->assertStringContainsString('minSampleSupport:2', $source);
        $this->assertStringContainsString('margin:0.05', $source);
        $this->assertStringContainsString('confirmFrames:1', $source);
        $this->assertStringContainsString('_faceLocked', $source);
        $this->assertStringContainsString('isKiosk', $source);
        $this->assertStringContainsString('afterFaceMarkSuccess', $source);
        $this->assertStringContainsString('singleSampleTop1:0.72', $source);
        $this->assertStringContainsString('robustPersonSimilarity(faceEmbedding, descriptors)', $source);
        $this->assertStringContainsString('hasEnoughSampleAgreement(match)', $source);
        $this->assertStringContainsString('rebuildEnrolled', $source);
        $this->assertStringContainsString('recordDiag', $source);
        $this->assertStringContainsString('submitBarcode', $source);
        $this->assertStringContainsString('_scanGen', $source);
        $this->assertStringContainsString('getVideoConstraints', $source);
        $this->assertStringContainsString('applyAutoExposure', $source);
        $this->assertStringContainsString('previewBrightness', $source);
        $this->assertStringContainsString('maybeAdjustHardwareExposure', $source);
        $this->assertStringNotContainsString('threshold:0.58', $source);
        $this->assertStringNotContainsString('margin:0.08', $source);
        $this->assertStringNotContainsString('confirmFrames:4', $source);
    }

    public function test_skor_kecocokan_pakai_top1_bukan_dirata_rata_dgn_top2(): void
    {
        // Regresi: skor sempat dihitung top1*0.58+top2*0.42 — wajah yg SANGAT mirip salah satu
        // sampel terdaftar (top1 tinggi) tetap bisa gagal gate `threshold` kalau sampel lain punya
        // sudut/cahaya beda (top2 rendah menyeret skor turun). Ini bikin "Perjelas wajah" muncul
        // terus meski wajahnya sudah dikenali dgn baik. Korroborasi tetap dijaga lewat
        // hasEnoughSampleAgreement() sbg gate terpisah, bukan campur ke skor utama.
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        $this->assertStringContainsString('const score = top1;', $source);
        $this->assertStringNotContainsString('top1 * 0.58 + top2 * 0.42', $source);
    }

    public function test_hud_atas_scan_wajah_tidak_pakai_3_badge_absolute_terpisah(): void
    {
        // Regresi: status/mode/counter dulu masing2 `absolute top-3 {left-3,left-1/2,right-3}` —
        // di layar HP sempit ketiganya berebut baris yg sama & saling tumpuk/terpotong (dilaporkan
        // user sbg "keluar dari viewportnya"). Sekarang satu wrapper flex-wrap supaya melipat ke
        // baris baru, bukan tumpuk, saat tak muat.
        $source = file_get_contents(resource_path('views/absensi/scan.blade.php'));

        $this->assertStringContainsString('flex flex-col gap-1.5 pointer-events-none', $source);
        $this->assertStringContainsString('flex items-start justify-between gap-1.5 flex-wrap', $source);
        $this->assertStringNotContainsString('absolute top-3 left-1/2 -translate-x-1/2', $source);
    }
}
