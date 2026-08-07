<?php

namespace App\Support;

use Illuminate\Support\Str;

final class BlueprintDocument
{
    public static function looksLike(string $content): bool
    {
        return str_contains(Str::upper($content), 'KISI-KISI');
    }

    public static function parse(string $content): array
    {
        $lines = LearningDocument::sanitize($content, keepUnderline: true);
        $doc = [
            'parsed' => false,
            'text' => implode("\n", $lines),
            'title' => 'KISI-KISI PENILAIAN',
            'subject' => '',
            'identity' => [],
            'rows' => [],
            'legend' => [],
            'note' => '',
            'answer_title' => 'KUNCI JAWABAN',
            'answer_subtitle' => '',
            'answer_sections' => [],
            'recap' => [],
            'recap_note' => '',
        ];

        $state = 'top';
        $answerSection = null;
        $flushAnswerSection = function () use (&$doc, &$answerSection) {
            if ($answerSection !== null) {
                $doc['answer_sections'][] = $answerSection;
            }
            $answerSection = null;
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $upper = Str::upper($trimmed);

            if (str_starts_with($upper, 'KUNCI JAWABAN')) {
                $flushAnswerSection();
                $doc['answer_title'] = $trimmed;
                $state = 'answer_intro';
                continue;
            }

            if (str_starts_with($upper, 'REKAP PENILAIAN')) {
                $flushAnswerSection();
                $state = 'recap';
                continue;
            }

            if ($state === 'top') {
                if (str_contains($upper, 'KISI-KISI')) {
                    $doc['title'] = $trimmed;
                    $state = 'subject';
                }
                continue;
            }

            if ($state === 'subject') {
                if (! str_contains($trimmed, ':') && ! self::isTableLine($trimmed)) {
                    $doc['subject'] = $trimmed;
                    $state = 'identity';
                    continue;
                }
                $state = 'identity';
            }

            if ($state === 'identity') {
                if (str_starts_with($upper, 'TABEL KISI-KISI') || str_starts_with($upper, 'KISI-KISI SOAL')) {
                    $state = 'blueprint_table';
                    continue;
                }
                if (self::isTableLine($trimmed) && str_contains($upper, 'INDIKATOR')) {
                    $state = 'blueprint_table';
                    continue;
                }
                $identity = self::identityRow($trimmed);
                if ($identity !== null) {
                    $doc['identity'][] = $identity;
                }
                continue;
            }

            if ($state === 'blueprint_table') {
                if (str_starts_with($upper, 'LEGENDA') || str_starts_with($upper, 'CATATAN') || str_starts_with($upper, 'TANDA TANGAN') || str_starts_with($upper, 'MENGETAHUI')) {
                    $state = str_starts_with($upper, 'LEGENDA') ? 'legend' : (str_starts_with($upper, 'CATATAN') ? 'note' : 'signatures');
                    if ($state === 'note') {
                        $doc['note'] = trim((string) preg_replace('/^Catatan\s*:\s*/iu', '', $trimmed));
                    }
                    continue;
                }

                $row = self::blueprintRow($trimmed);
                if ($row !== null) {
                    $doc['rows'][] = $row;
                }
                continue;
            }

            if ($state === 'legend') {
                if (str_starts_with($upper, 'CATATAN')) {
                    $state = 'note';
                    $doc['note'] = trim((string) preg_replace('/^Catatan\s*:\s*/iu', '', $trimmed));
                    continue;
                }
                if (str_starts_with($upper, 'TANDA TANGAN') || str_starts_with($upper, 'MENGETAHUI')) {
                    $state = 'signatures';
                    continue;
                }
                $doc['legend'][] = $trimmed;
                continue;
            }

            if ($state === 'note') {
                if (str_starts_with($upper, 'TANDA TANGAN') || str_starts_with($upper, 'MENGETAHUI')) {
                    $state = 'signatures';
                    continue;
                }
                $doc['note'] = trim($doc['note'].' '.$trimmed);
                continue;
            }

            if ($state === 'signatures') {
                continue;
            }

            if ($state === 'answer_intro') {
                if (preg_match('/^[A-Z]\.\s+/u', $trimmed)) {
                    $state = 'answer_section';
                } else {
                    $doc['answer_subtitle'] = trim($doc['answer_subtitle'].' '.$trimmed);
                    continue;
                }
            }

            if ($state === 'answer_section') {
                if (preg_match('/^[A-Z]\.\s+(.+)$/u', $trimmed)) {
                    $flushAnswerSection();
                    $answerSection = ['heading' => $trimmed, 'headers' => [], 'rows' => []];
                    continue;
                }
                if ($answerSection !== null && self::isTableLine($trimmed)) {
                    $parts = self::splitTable($trimmed);
                    if ($answerSection['headers'] === [] && self::looksLikeHeader($parts)) {
                        $answerSection['headers'] = $parts;
                    } else {
                        $answerSection['rows'][] = $parts;
                    }
                    continue;
                }
                if ($answerSection !== null) {
                    $answerSection['rows'][] = [$trimmed];
                }
                continue;
            }

            if ($state === 'recap') {
                if (str_starts_with($upper, 'KETERANGAN')) {
                    $doc['recap_note'] = trim((string) preg_replace('/^Keterangan\s*:\s*/iu', '', $trimmed));
                    continue;
                }
                if (self::isTableLine($trimmed)) {
                    $parts = self::splitTable($trimmed);
                    if (! self::looksLikeHeader($parts)) {
                        $doc['recap'][] = $parts;
                    }
                } elseif ($doc['recap_note'] !== '') {
                    $doc['recap_note'] .= ' '.$trimmed;
                }
            }
        }

        $flushAnswerSection();
        $doc['parsed'] = $doc['rows'] !== [] || $doc['answer_sections'] !== [];

        return $doc;
    }

    private static function identityRow(string $line): ?array
    {
        if (! preg_match('/^([^:]{2,80})\s*:\s*(.+)$/u', $line, $m)) {
            return null;
        }

        return ['label' => trim($m[1]), 'value' => trim($m[2])];
    }

    private static function blueprintRow(string $line): ?array
    {
        $parts = self::isTableLine($line) ? self::splitTable($line) : (preg_split('/\s{2,}/u', $line) ?: []);
        if (count($parts) < 7 || self::looksLikeHeader($parts) || ! is_numeric($parts[0])) {
            return null;
        }

        return [
            'no' => $parts[0] ?? '',
            'element' => $parts[1] ?? '',
            'material' => $parts[2] ?? '',
            'indicator' => $parts[3] ?? '',
            'level' => $parts[4] ?? '',
            'shape' => $parts[5] ?? '',
            'question_no' => $parts[6] ?? '',
        ];
    }

    private static function isTableLine(string $line): bool
    {
        return substr_count($line, '|') >= 2;
    }

    private static function splitTable(string $line): array
    {
        $parts = array_map('trim', explode('|', trim($line, " \t\n\r\0\x0B|")));

        return array_values(array_filter($parts, fn (string $part) => $part !== '' && ! preg_match('/^-+$/', $part)));
    }

    private static function looksLikeHeader(array $parts): bool
    {
        $joined = Str::lower(implode(' ', $parts));

        return str_contains($joined, 'no') && (
            str_contains($joined, 'indikator')
            || str_contains($joined, 'kunci')
            || str_contains($joined, 'bentuk')
            || str_contains($joined, 'jumlah')
        )
            || (str_contains($joined, 'bagian') && str_contains($joined, 'bentuk') && str_contains($joined, 'jumlah'))
            || (str_contains($joined, 'istilah') && str_contains($joined, 'pasangan'));
    }
}
