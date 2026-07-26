<?php

namespace Tests\Unit;

use App\Support\TeacherGenerationText;
use PHPUnit\Framework\TestCase;

class TeacherGenerationTextTest extends TestCase
{
    public function test_menyatukan_chunk_dengan_overlap_di_sambungan(): void
    {
        $accumulated = 'IDENTIFIKASI Murid: Fase C, kelas 5 SD yang mempelajari';
        $chunk = 'yang mempelajari ekosistem hutan tropis.';

        $merged = TeacherGenerationText::stitchContinuation($accumulated, $chunk);

        $this->assertSame(
            'IDENTIFIKASI Murid: Fase C, kelas 5 SD yang mempelajari ekosistem hutan tropis.',
            $merged,
        );
    }

    public function test_menyatukan_chunk_tanpa_overlap_dengan_concat_biasa(): void
    {
        $accumulated = 'Bagian pertama selesai.';
        $chunk = 'Bagian kedua dimulai.';

        $this->assertSame(
            'Bagian pertama selesai.Bagian kedua dimulai.',
            TeacherGenerationText::stitchContinuation($accumulated, $chunk),
        );
    }

    public function test_chunk_kosong_tidak_mengubah_accumulated(): void
    {
        $this->assertSame('Sudah ada', TeacherGenerationText::stitchContinuation('Sudah ada', ''));
    }
}
