<?php

namespace App\Support;

/**
 * Label mekanik Arena Belajar (Misi) untuk UI guru/siswa.
 * Hindari kata "Nalar" sendirian agar tidak bentrok dengan Nalar Guru (Asisten Guru).
 */
final class ArenaMechanics
{
    /** @return array<string, string> */
    public static function labels(): array
    {
        return [
            'nalar_bundle' => 'Misi cerita + keputusan',
            'recall_quiz_bundle' => 'Kuis di dalam misi',
            'interactive_narrative' => 'Cerita interaktif',
            'strategic_decision' => 'Keputusan strategis',
            'puzzle_sequencing' => 'Puzzle urutan',
            'quiz_matching' => 'Mencocokkan',
        ];
    }

    public static function label(?string $mechanicType): string
    {
        $key = (string) $mechanicType;
        if ($key === 'recall_quiz') {
            $key = 'recall_quiz_bundle';
        }
        $labels = self::labels();

        if (isset($labels[$key])) {
            return $labels[$key];
        }

        return $key !== ''
            ? str_replace('_', ' ', $key)
            : 'Misi';
    }
}
