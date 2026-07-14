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
        $this->assertStringContainsString('margin:0.08', $source);
        $this->assertStringContainsString('confirmFrames:4', $source);
        $this->assertStringContainsString('robustPersonSimilarity(faceEmbedding, descriptors)', $source);
        $this->assertStringContainsString('hasEnoughSampleAgreement(match)', $source);
        $this->assertStringContainsString('match.top1 >= this.threshold', $source);
        $this->assertStringNotContainsString('threshold:0.58', $source);
    }
}