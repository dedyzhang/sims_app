<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Parser best-effort teks soal dari Asisten AI Guru → struktur builder Arena.
 * Mendukung: PG, PG kompleks, Benar/Salah, Isian, Mencocokkan (+ blok Kunci Jawaban).
 */
class GameQuizImporter
{
    /**
     * @return array<int, array{type:string,question_text:string,options:array<int,array{option_text:string,is_correct:bool}>,explanation:?string,meta?:array}>
     */
    public function parse(string $raw): array
    {
        $raw = trim(str_replace(["\r\n", "\r"], "\n", $raw));
        if ($raw === '') {
            return [];
        }

        [$body, $answerMap] = $this->extractAnswerKey($raw);
        $body = $this->stripDocumentChrome($body);
        $body = $this->protectNestedNumberedLists($body);

        $blocks = preg_split('/\n(?=\s*\d+[\.\)]\s+)/u', $body) ?: [];
        $questions = [];

        foreach ($blocks as $block) {
            $block = trim(preg_replace('/§N(\d+)§\s*/u', '$1. ', $block) ?? $block);
            if ($block === '') {
                continue;
            }

            $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn ($l) => $l !== ''));
            if (count($lines) < 1) {
                continue;
            }

            if (! preg_match('/^(\d+)[\.\)]\s*(.+)$/u', $lines[0], $numMatch)) {
                continue;
            }

            $number = (int) $numMatch[1];
            $first = trim($numMatch[2]);
            $options = [];
            $explanation = null;
            $correctHint = $answerMap[$number] ?? null;
            $shortAnswers = [];
            $matchPairs = [];
            $kolomA = [];
            $kolomB = [];
            $inKolomA = false;
            $inKolomB = false;
            $looksLikeMatch = (bool) preg_match('/cocokkan|menjodohkan|kolom\s*a|kolom\s*b/iu', $first.$block);

            foreach (array_slice($lines, 1) as $line) {
                if (preg_match('/^(kunci|jawaban|answer)\s*[:：]\s*(.+)$/iu', $line, $m)) {
                    $hint = trim($m[2]);
                    // Lewati garis isian kosong
                    if (! preg_match('/^_+$/u', $hint)) {
                        $correctHint = $hint;
                    }
                    continue;
                }
                if (preg_match('/^(pembahasan|explanation|petunjuk)\s*[:：]\s*(.+)$/iu', $line, $m)) {
                    if (! preg_match('/^pilih semua/iu', $m[2])) {
                        $explanation = trim($m[2]);
                    }
                    continue;
                }
                if (preg_match('/^kolom\s*a\b/iu', $line)) {
                    $inKolomA = true;
                    $inKolomB = false;
                    $looksLikeMatch = true;
                    continue;
                }
                if (preg_match('/^kolom\s*b\b/iu', $line)) {
                    $inKolomB = true;
                    $inKolomA = false;
                    $looksLikeMatch = true;
                    continue;
                }
                if ($inKolomA && preg_match('/^(\d+)[\.\)]\s*(.+)$/u', $line, $m)) {
                    $kolomA[(int) $m[1]] = trim($m[2]);
                    continue;
                }
                if ($inKolomB && preg_match('/^([A-Da-d])[\.\)\-:]\s*(.+)$/u', $line, $m)) {
                    $kolomB[strtoupper($m[1])] = trim($m[2]);
                    continue;
                }
                if (preg_match('/^([A-Da-d]|[Bb]enar|[Ss]alah)[\.\)\-:]\s*(.+)$/u', $line, $m)) {
                    $label = strtolower($m[1]);
                    $text = trim($m[2]);
                    $star = str_ends_with($text, '*') || str_contains(strtolower($text), '(benar)');
                    $text = trim(rtrim(str_ireplace(['*', '(benar)'], '', $text)));
                    $options[] = [
                        'option_text' => $text !== '' ? $text : $m[1],
                        'is_correct' => $star,
                        '_label' => $label,
                    ];
                    if ($looksLikeMatch && preg_match('/^[a-d]$/', $label)) {
                        $kolomB[strtoupper($label)] = $text !== '' ? $text : $m[1];
                    }
                } elseif ($looksLikeMatch && preg_match('/^(\d+)[\.\)]\s*(.+)$/u', $line, $m) && ! $inKolomB) {
                    $kolomA[(int) $m[1]] = trim($m[2]);
                }
            }

            // Mencocokkan dari kunci 1-B, 2-A
            if ($looksLikeMatch || ($correctHint && preg_match('/\d+\s*[-–:]\s*[A-D]/iu', $correctHint))) {
                $matchPairs = $this->buildMatchPairs($kolomA, $kolomB, $correctHint);
                if (count($matchPairs) >= 2) {
                    $questions[] = [
                        'type' => 'match',
                        'question_text' => $first,
                        'options' => [],
                        'explanation' => $explanation,
                        'meta' => ['pairs' => $matchPairs],
                    ];
                    continue;
                }
            }

            $type = 'mcq';
            if (count($options) === 2) {
                $labels = collect($options)->pluck('_label')->implode(' ');
                if (str_contains($labels, 'benar') || str_contains($labels, 'salah')) {
                    $type = 'true_false';
                }
            }

            // Benar/Salah tanpa opsi eksplisit
            if (! $options && $correctHint && preg_match('/^(benar|salah)$/iu', $correctHint)) {
                $type = 'true_false';
                $isBenar = strcasecmp($correctHint, 'Benar') === 0;
                $options = [
                    ['option_text' => 'Benar', 'is_correct' => $isBenar, '_label' => 'benar'],
                    ['option_text' => 'Salah', 'is_correct' => ! $isBenar, '_label' => 'salah'],
                ];
            }

            if ($correctHint && $options) {
                $this->applyCorrectHint($options, $correctHint);
            }

            // Isian: tidak ada opsi A-D / Benar-Salah
            if (! $options) {
                $type = 'short_answer';
                if ($correctHint && ! preg_match('/^[A-D](\s*,\s*[A-D])*$/iu', $correctHint)
                    && ! preg_match('/\d+\s*[-–:]\s*[A-D]/iu', $correctHint)) {
                    $shortAnswers = [trim($correctHint)];
                } else {
                    $shortAnswers = [''];
                }
            }

            $correctCount = collect($options)->where('is_correct', true)->count();
            if ($options && $correctCount === 0) {
                $options[0]['is_correct'] = true;
                $correctCount = 1;
            }

            // Lebih dari satu kunci → Pilihan Ganda Kompleks
            if ($options && $correctCount > 1 && $type === 'mcq') {
                $type = 'mcq_complex';
            } elseif ($correctCount > 1 && $type === 'true_false') {
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

            $cleanOptions = array_map(fn ($o) => [
                'option_text' => $o['option_text'],
                'is_correct' => (bool) $o['is_correct'],
            ], $options);

            $item = [
                'type' => $type,
                'question_text' => $first,
                'options' => $cleanOptions,
                'explanation' => $explanation,
            ];

            if ($type === 'short_answer') {
                $item['meta'] = ['answers' => $shortAnswers ?: ['']];
            }

            $questions[] = $item;
        }

        return $questions;
    }

    /**
     * @param  array<int, string>  $kolomA
     * @param  array<string, string>  $kolomB
     * @return list<array{left:string,right:string}>
     */
    private function buildMatchPairs(array $kolomA, array $kolomB, ?string $correctHint): array
    {
        $pairs = [];

        if ($correctHint && preg_match_all('/(\d+)\s*[-–:]\s*([A-D])/iu', $correctHint, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $leftKey = (int) $m[1];
                $rightKey = strtoupper($m[2]);
                $left = $kolomA[$leftKey] ?? ('Pernyataan '.$leftKey);
                $right = $kolomB[$rightKey] ?? $rightKey;
                $pairs[] = ['left' => $left, 'right' => $right];
            }
        }

        if (count($pairs) < 2 && count($kolomA) >= 2 && count($kolomB) >= 2) {
            $rights = array_values($kolomB);
            $i = 0;
            foreach ($kolomA as $left) {
                $pairs[] = [
                    'left' => $left,
                    'right' => $rights[$i] ?? ($rights[$i % count($rights)] ?? ''),
                ];
                $i++;
            }
        }

        return array_values(array_filter(
            $pairs,
            fn ($p) => trim($p['left']) !== '' && trim($p['right']) !== ''
        ));
    }

    /**
     * @return array{0:string,1:array<int,string>}
     */
    private function extractAnswerKey(string $raw): array
    {
        if (! preg_match('/\nKunci Jawaban[^\n]*\n(.+)$/isu', $raw, $m)) {
            return [$raw, []];
        }

        $body = trim(preg_replace('/\nKunci Jawaban.*$/isu', '', $raw) ?? $raw);
        $map = [];

        foreach (preg_split('/\n+/', trim($m[1])) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || preg_match('/^(untuk guru|pilihan ganda|benar\/salah|isian|mencocokkan|menjodohkan|pedoman)/iu', $line)) {
                continue;
            }
            if (preg_match('/^(\d+)[\.\)]\s*(.+)$/u', $line, $km)) {
                $key = trim($km[2]);
                if (preg_match('/\d+\s*[-–:]\s*[A-D]/iu', $key)) {
                    // biarkan format mencocokkan utuh: 1-B, 2-A
                } elseif (preg_match('/^([A-D](?:\s*,\s*[A-D])+)\b/iu', $key, $letters)) {
                    // PG kompleks: A, C
                    $key = strtoupper(preg_replace('/\s+/', '', $letters[1]) ?? $letters[1]);
                } elseif (preg_match('/^([A-D])\b/iu', $key, $letter)) {
                    $key = strtoupper($letter[1]);
                } elseif (preg_match('/^(benar|salah)\b/iu', $key, $bs)) {
                    $key = Str::title($bs[1]);
                }
                $map[(int) $km[1]] = $key;
            }
        }

        return [$body, $map];
    }

    private function stripDocumentChrome(string $body): string
    {
        $lines = explode("\n", $body);
        $kept = [];
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $kept[] = '';
                continue;
            }
            if (preg_match('/^(YAYASAN|SMP |SMA |SD |TERAKREDITASI|Jl\.|SOAL EVALUASI|Mata Pelajaran|Kelas \/ Semester|Nama :|Nilai :|Petunjuk Pengerjaan|Bagian [A-Z]\b)/u', $trim)) {
                continue;
            }
            if (preg_match('/^Kerjakan soal sesuai/iu', $trim)) {
                continue;
            }
            $kept[] = $line;
        }

        return trim(implode("\n", $kept));
    }

    /**
     * Cegah nomor di Kolom A memecah blok soal saat split bernomor.
     */
    private function protectNestedNumberedLists(string $body): string
    {
        return preg_replace_callback(
            '/(Kolom\s*A\b[^\n]*\n)([\s\S]*?)(?=\n\s*Kolom\s*B\b)/iu',
            function (array $m): string {
                $inner = preg_replace('/^(\d+)[\.\)]\s+/um', '§N$1§ ', $m[2]) ?? $m[2];

                return $m[1].$inner;
            },
            $body
        ) ?? $body;
    }

    /** @param  list<array<string,mixed>>  $options */
    private function applyCorrectHint(array &$options, string $correctHint): void
    {
        $hint = trim($correctHint);
        preg_match_all('/\b([A-D])\b/iu', $hint, $letters);
        $wanted = array_values(array_unique(array_map('strtolower', $letters[1] ?? [])));

        if ($wanted !== []) {
            foreach ($options as &$opt) {
                $label = strtolower((string) ($opt['_label'] ?? ''));
                $opt['is_correct'] = in_array($label, $wanted, true);
            }
            unset($opt);

            return;
        }

        $hintLower = strtolower($hint);
        foreach ($options as &$opt) {
            $opt['is_correct'] = $opt['is_correct']
                || strtolower((string) ($opt['_label'] ?? '')) === $hintLower
                || strtolower((string) $opt['option_text']) === $hintLower
                || str_starts_with(strtolower((string) $opt['option_text']), $hintLower);
        }
        unset($opt);
    }

    /** Heuristik: teks layak dikirim ke form impor Arena (bukan penjelasan biasa). */
    public static function looksLikeImportableQuiz(string $raw): bool
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", $raw));
        if ($text === '') {
            return false;
        }

        if (preg_match('/SOAL EVALUASI|Kunci Jawaban|Bagian\s+[A-Z]\s*-/iu', $text) === 1) {
            return true;
        }

        return preg_match('/^\s*\d+[\.\)]\s+\S/mu', $text) === 1
            && preg_match('/^\s*[A-Da-d][\.\)]\s+\S/mu', $text) === 1;
    }
}
