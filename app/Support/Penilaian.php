<?php

namespace App\Support;

class Penilaian
{
    public const RUMUS = [
        'bagi3'      => 'Bobot 1 — (Formatif + Sumatif + PAS) ÷ 3',
        'bagi4'      => 'Bobot 2 — (2×Formatif + Sumatif + PAS) ÷ 4',
        'jumlahDulu' => 'Bobot 3 — jumlahkan semua nilai lalu rata-rata',
    ];

    /**
     * Hitung nilai rapor satu siswa.
     *
     * @param  float[] $formatif  daftar nilai formatif (per TP) yang terisi
     * @param  float[] $sumatif   daftar nilai sumatif (per materi) yang terisi
     * @param  float|null $pas    nilai PAS (null = belum ada)
     * @param  string $rumus      bagi3 | bagi4 | jumlahDulu
     * @return array{rataFormatif:int,rataSumatif:int,pas:int,rapor:int}
     */
    public static function hitung(array $formatif, array $sumatif, ?float $pas, string $rumus): array
    {
        $fCount = count($formatif);
        $sCount = count($sumatif);
        $fSum   = array_sum($formatif);
        $sSum   = array_sum($sumatif);
        $pasAda = $pas !== null;
        $pasVal = (float) ($pas ?? 0);

        // Rata-rata untuk DITAMPILKAN di kolom (selalu rata-rata, biar jelas)
        $avgF = $fCount > 0 ? (int) round($fSum / $fCount) : 0;
        $avgS = $sCount > 0 ? (int) round($sSum / $sCount) : 0;

        if ($rumus === 'jumlahDulu') {
            // Jumlahkan SEMUA nilai (tiap formatif/sumatif/pas dihitung satu data) lalu rata-rata
            $jml = $fCount + $sCount + ($pasAda ? 1 : 0);
            $rapor = $jml > 0 ? (int) round(($fSum + $sSum + $pasVal) / $jml) : 0;
        } elseif ($rumus === 'bagi3') {
            $rapor = (int) round(($avgF + $avgS + $pasVal) / 3);
        } else { // bagi4 (default)
            $rapor = (int) round((2 * $avgF + $avgS + $pasVal) / 4);
        }

        return ['rataFormatif' => $avgF, 'rataSumatif' => $avgS, 'pas' => (int) round($pasVal), 'rapor' => $rapor];
    }

    /**
     * Predikat A/B/C/D berdasarkan KKM (interval = (100-KKM)/3), gaya Kurikulum Merdeka.
     */
    public static function predikat(?int $nilai, int $kkm): string
    {
        if ($nilai === null) return '-';
        if ($nilai < $kkm) return 'D';
        $interval = (100 - $kkm) / 3;
        if ($nilai >= $kkm + 2 * $interval) return 'A';
        if ($nilai >= $kkm + $interval) return 'B';
        return 'C';
    }

    /** Kalimat deskripsi capaian berdasarkan predikat + teks TP. */
    public static function kalimatPositif(string $predikat, string $tupe): string
    {
        $t = rtrim(lcfirst(trim($tupe)), '.');
        return match ($predikat) {
            'A' => "Menunjukkan penguasaan yang sangat baik dalam {$t}.",
            'B' => "Menunjukkan penguasaan yang baik dalam {$t}.",
            'C' => "Menunjukkan penguasaan yang cukup dalam {$t}.",
            default => "Menunjukkan penguasaan dalam {$t}.",
        };
    }

    public static function kalimatNegatif(string $tupe): string
    {
        $t = rtrim(lcfirst(trim($tupe)), '.');
        return "Perlu ditingkatkan lagi dalam {$t}.";
    }

    /** Kata predikat untuk ekskul (Amat baik / Baik / Cukup / Perlu bimbingan). */
    public static function predikatKata(string $predikat): string
    {
        return match ($predikat) {
            'A' => 'Amat baik',
            'B' => 'Baik',
            'C' => 'Cukup',
            default => 'Perlu bimbingan',
        };
    }
}
