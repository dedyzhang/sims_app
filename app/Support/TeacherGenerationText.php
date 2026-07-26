<?php

namespace App\Support;

/**
 * Utilitas teks untuk generate dokumen panjang Asisten Guru (RPM, soal).
 */
final class TeacherGenerationText
{
    /**
     * Gabungkan lanjutan chunk tanpa mengulang overlap di sambungan.
     * Cari suffix terpanjang dari $accumulated yang sama dengan prefix $chunk.
     */
    public static function stitchContinuation(string $accumulated, string $chunk): string
    {
        if ($accumulated === '') {
            return $chunk;
        }

        if ($chunk === '') {
            return $accumulated;
        }

        $maxOverlap = min(mb_strlen($accumulated), mb_strlen($chunk), 600);

        for ($len = $maxOverlap; $len >= 12; $len--) {
            if (mb_substr($accumulated, -$len) === mb_substr($chunk, 0, $len)) {
                return $accumulated.mb_substr($chunk, $len);
            }
        }

        return $accumulated.$chunk;
    }
}
