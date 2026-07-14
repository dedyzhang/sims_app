<?php

namespace App\Services;

/**
 * Parser best-effort teks soal dari Asisten AI Guru → struktur builder.
 * Format yang dikenali (longgar):
 * 1. Pertanyaan?
 * A. opsi
 * B. opsi *
 * atau "Benar/Salah" dengan kunci di baris Kunci:/Jawaban:
 */
class GameQuizImporter
{
    /**
     * @return array<int, array{type:string,question_text:string,options:array<int,array{option_text:string,is_correct:bool}>,explanation:?string}>
     */
    public function parse(string $raw): array
    {
        $raw = trim(str_replace(["\r\n", "\r"], "\n", $raw));
        if ($raw === '') {
            return [];
        }

        $blocks = preg_split('/\n(?=\s*\d+[\.\)]\s+)/u', $raw) ?: [];
        $questions = [];

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));
            if (count($lines) < 1) {
                continue;
            }

            $first = preg_replace('/^\d+[\.\)]\s*/u', '', $lines[0]) ?? $lines[0];
            $options = [];
            $explanation = null;
            $correctHint = null;

            foreach (array_slice($lines, 1) as $line) {
                if (preg_match('/^(kunci|jawaban|answer)\s*[:：]\s*(.+)$/iu', $line, $m)) {
                    $correctHint = trim($m[2]);
                    continue;
                }
                if (preg_match('/^(pembahasan|explanation)\s*[:：]\s*(.+)$/iu', $line, $m)) {
                    $explanation = trim($m[2]);
                    continue;
                }
                if (preg_match('/^([A-Da-d]|[Bb]enar|[Ss]alah)[\.\)\-:]\s*(.+)$/u', $line, $m)) {
                    $label = strtolower($m[1]);
                    $text = trim($m[2]);
                    $star = str_ends_with($text, '*') || str_contains($text, '(benar)');
                    $text = rtrim(str_ireplace(['*', '(benar)'], '', $text));
                    $options[] = [
                        'option_text' => $text !== '' ? $text : $m[1],
                        'is_correct'  => $star,
                        '_label'      => $label,
                    ];
                }
            }

            $type = 'mcq';
            if (count($options) === 2) {
                $labels = collect($options)->pluck('_label')->implode(' ');
                if (str_contains($labels, 'benar') || str_contains($labels, 'salah')) {
                    $type = 'true_false';
                }
            }

            if ($correctHint && $options) {
                $hint = strtolower($correctHint);
                foreach ($options as &$opt) {
                    $opt['is_correct'] = $opt['is_correct']
                        || strtolower($opt['_label'] ?? '') === $hint
                        || strtolower($opt['option_text']) === $hint
                        || str_starts_with(strtolower($opt['option_text']), $hint);
                }
                unset($opt);
            }

            // Pastikan tepat 1 benar untuk MCQ; jika tidak ada, tandai opsi pertama
            $correctCount = collect($options)->where('is_correct', true)->count();
            if ($options && $correctCount === 0) {
                $options[0]['is_correct'] = true;
            } elseif ($correctCount > 1) {
                $seen = false;
                foreach ($options as &$opt) {
                    if ($opt['is_correct']) {
                        if ($seen) {
                            $opt['is_correct'] = false;
                        }
                        $seen = true;
                    }
                }
                unset($opt);
            }

            if (!$options) {
                // Default TF jika tidak ada opsi terdeteksi
                $type = 'true_false';
                $options = [
                    ['option_text' => 'Benar', 'is_correct' => true],
                    ['option_text' => 'Salah', 'is_correct' => false],
                ];
            }

            $cleanOptions = array_map(fn ($o) => [
                'option_text' => $o['option_text'],
                'is_correct'  => (bool) $o['is_correct'],
            ], $options);

            $questions[] = [
                'type'          => $type,
                'question_text' => $first,
                'options'       => $cleanOptions,
                'explanation'   => $explanation,
            ];
        }

        return $questions;
    }
}
