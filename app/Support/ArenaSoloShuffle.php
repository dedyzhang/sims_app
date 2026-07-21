<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Acak urutan soal/opsi untuk mode solo, deterministic per attempt.
 * Refresh halaman = urutan sama; attempt/siswa lain = urutan berbeda.
 */
class ArenaSoloShuffle
{
    /**
     * @template T
     *
     * @param  Collection<int, T>  $items
     * @return Collection<int, T>
     */
    public static function shuffle(Collection $items, string $seed): Collection
    {
        $list = $items->values()->all();
        $n = count($list);
        if ($n <= 1) {
            return collect($list);
        }

        $state = self::seedToInt($seed);
        for ($i = $n - 1; $i > 0; $i--) {
            $state = self::next($state);
            $j = $state % ($i + 1);
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        }

        return collect($list)->values();
    }

    private static function seedToInt(string $seed): int
    {
        return hexdec(substr(hash('sha256', $seed), 0, 8));
    }

    private static function next(int $state): int
    {
        // LCG numerik stabil lintas PHP — cukup untuk shuffle soal kelas.
        return (int) (($state * 1664525 + 1013904223) % 2147483647);
    }
}
