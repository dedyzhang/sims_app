<?php

namespace App\Support;

/**
 * Parser dokumen soal evaluasi hasil Generator Soal. Mengubah teks polos dari AI
 * menjadi struktur kop/identitas/petunjuk/bagian soal/kunci jawaban, supaya
 * pratinjau layar dan export Word memakai satu sumber struktur yang sama —
 * mengikuti format acuan soal-agama-buddha.docx.
 *
 * Bila judul "SOAL EVALUASI" atau butir soal tidak ditemukan, `parsed` bernilai
 * false dan pemanggil kembali merender teks polos apa adanya.
 */
final class QuizDocument
{
    private const IDENTITY_LABELS = [
        'Mata Pelajaran', 'Kelas / Semester', 'Kelas/Semester', 'Kelas', 'Hari / Tanggal', 'Hari/Tanggal',
        'Waktu', 'Alokasi Waktu', 'Nama', 'Nilai',
    ];

    public static function parse(string $content): array
    {
        $doc = [
            'parsed' => false,
            'text' => '',
            'kop' => [],
            'title' => '',
            'subtitle' => '',
            'identity' => [],
            'petunjuk' => ['heading' => '', 'lines' => []],
            'sections' => [],   // ['heading' => 'Bagian A - Pilihan Ganda', 'intro' => [], 'questions' => []]
            'kunci' => ['heading' => '', 'subtitle' => '', 'pg' => [], 'lainnya' => [], 'esai' => [], 'rubrik' => ['heading' => '', 'lines' => []]],
        ];

        $lines = LearningDocument::sanitize($content, keepUnderline: true);
        $doc['text'] = implode("\n", $lines);

        $state = 'kop';
        $kunciMode = '';          // pg | esai | rubrik
        $section = null;          // bagian soal aktif
        $question = null;         // butir soal aktif
        $esai = null;             // blok "Soal 11" pada kunci jawaban
        $kunciLainnya = null;     // sub-bagian kunci untuk tipe baru

        $flushQuestion = function () use (&$section, &$question) {
            if ($question !== null && $section !== null) {
                $section['questions'][] = $question;
            }
            $question = null;
        };
        $flushSection = function () use (&$doc, &$section, &$question, $flushQuestion) {
            $flushQuestion();
            if ($section !== null) {
                $doc['sections'][] = $section;
            }
            $section = null;
        };
        $flushEsai = function () use (&$doc, &$esai) {
            if ($esai !== null) {
                $doc['kunci']['esai'][] = $esai;
            }
            $esai = null;
        };
        $flushKunciLainnya = function () use (&$doc, &$kunciLainnya) {
            if ($kunciLainnya !== null) {
                $doc['kunci']['lainnya'][] = $kunciLainnya;
            }
            $kunciLainnya = null;
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            // Kunci jawaban mengakhiri bagian soal, dari state mana pun.
            if (preg_match('/^Kunci Jawaban\b/iu', $trimmed)) {
                $flushSection();
                $doc['kunci']['heading'] = $trimmed;
                $state = 'kunci';
                $kunciMode = '';

                continue;
            }

            // Awal bagian soal: "Bagian A - Pilihan Ganda".
            if ($state !== 'kunci' && preg_match('/^Bagian\s+[A-Z]\b/iu', $trimmed)) {
                $flushSection();
                $section = ['heading' => $trimmed, 'intro' => [], 'questions' => []];
                $state = 'soal';

                continue;
            }

            if ($state === 'kop') {
                if (preg_match('/^SOAL\s+(EVALUASI|ULANGAN|LATIHAN)\b/iu', $trimmed)) {
                    $doc['title'] = $trimmed;
                    $state = 'judul';
                } else {
                    $doc['kop'][] = $trimmed;
                }

                continue;
            }

            if ($state === 'judul' || $state === 'identitas' || $state === 'petunjuk') {
                if (preg_match('/^Petunjuk\b.*$/iu', $trimmed) && ! str_contains($trimmed, ':')) {
                    $doc['petunjuk']['heading'] = $trimmed;
                    $state = 'petunjuk';

                    continue;
                }

                if ($state === 'petunjuk') {
                    $doc['petunjuk']['lines'][] = self::stripBullet($trimmed);

                    continue;
                }

                $identity = self::matchIdentity($trimmed);
                if ($identity !== null) {
                    $doc['identity'][] = $identity;
                    $state = 'identitas';
                } elseif ($state === 'judul' && $doc['subtitle'] === '') {
                    $doc['subtitle'] = $trimmed;
                }

                continue;
            }

            if ($state === 'soal' && $section !== null) {
                if (preg_match('/^(\d{1,2})[.)]\s*(.+)$/u', $trimmed, $m)) {
                    $flushQuestion();
                    $parsedQ = self::extractImagesFromText(trim($m[2]));
                    $question = [
                        'number' => $m[1],
                        'text' => $parsedQ['text'],
                        'options' => [],
                        'answer_space' => false,
                        'images' => $parsedQ['images'],
                    ];

                    continue;
                }

                if ($question !== null && preg_match('/^([A-E])[.)]\s*(.+)$/u', $trimmed, $m)) {
                    $question['options'][] = ['label' => $m[1], 'text' => trim($m[2])];

                    continue;
                }

                // Baris khusus token gambar AI (tanpa teks soal).
                if ($question !== null && preg_match(QuizImageEnricher::TOKEN_PATTERN, $trimmed)) {
                    $parsedLine = self::extractImagesFromText($trimmed);
                    foreach ($parsedLine['images'] as $img) {
                        $question['images'][] = $img;
                    }

                    continue;
                }

                // Garis jawaban esai ("______").
                if (preg_match('/^_{5,}$/u', $trimmed)) {
                    if ($question !== null) {
                        $question['answer_space'] = true;
                    }

                    continue;
                }

                if ($question === null) {
                    $section['intro'][] = self::stripBullet($trimmed);
                } elseif ($question['options'] === []) {
                    // Lanjutan kalimat soal yang terpotong ke baris berikutnya.
                    $parsedLine = self::extractImagesFromText($trimmed);
                    foreach ($parsedLine['images'] as $img) {
                        $question['images'][] = $img;
                    }
                    if ($parsedLine['text'] !== '') {
                        $question['text'] .= ' '.$parsedLine['text'];
                    }
                } else {
                    $last = count($question['options']) - 1;
                    $question['options'][$last]['text'] .= ' '.$trimmed;
                }

                continue;
            }

            if ($state === 'kunci') {
                if (preg_match('/^\(.*\)$/u', $trimmed) && $doc['kunci']['subtitle'] === '' && $kunciMode === '') {
                    $doc['kunci']['subtitle'] = $trimmed;

                    continue;
                }

                if (preg_match('/^(Pilihan Ganda Kompleks|Benar\/Salah|Mencocokkan|Isian)\b/iu', $trimmed)) {
                    $flushEsai();
                    $flushKunciLainnya();
                    $kunciMode = 'lainnya';
                    $kunciLainnya = ['heading' => $trimmed, 'lines' => []];

                    continue;
                }

                if (preg_match('/^Pilihan Ganda\b/iu', $trimmed)) {
                    $flushEsai();
                    $flushKunciLainnya();
                    $kunciMode = 'pg';

                    continue;
                }

                if (preg_match('/^Esai\b/iu', $trimmed) && ! preg_match('/^Esai\s+\d/iu', $trimmed)) {
                    $flushEsai();
                    $flushKunciLainnya();
                    $kunciMode = 'esai';

                    continue;
                }

                if (preg_match('/^Rubrik\b/iu', $trimmed)) {
                    $flushEsai();
                    $flushKunciLainnya();
                    $kunciMode = 'rubrik';
                    $doc['kunci']['rubrik']['heading'] = $trimmed;

                    continue;
                }

                if ($kunciMode === 'pg' && preg_match('/^(\d{1,2})[.)]\s*([A-E](?:\s*,\s*[A-E])*)\b\.?$/u', $trimmed, $m)) {
                    $doc['kunci']['pg'][] = ['number' => $m[1], 'answer' => $m[2]];

                    continue;
                }

                if ($kunciMode === 'lainnya' && $kunciLainnya !== null) {
                    $kunciLainnya['lines'][] = self::stripBullet($trimmed);

                    continue;
                }

                if ($kunciMode === 'esai') {
                    if (preg_match('/^Soal\s+(\d{1,2})\b/iu', $trimmed)) {
                        $flushEsai();
                        $esai = ['heading' => $trimmed, 'lines' => []];

                        continue;
                    }
                    if ($esai !== null) {
                        $esai['lines'][] = self::stripBullet($trimmed);

                        continue;
                    }
                }

                if ($kunciMode === 'rubrik') {
                    $doc['kunci']['rubrik']['lines'][] = self::stripBullet($trimmed);

                    continue;
                }

                // Baris di luar sub-bagian yang dikenali (mis. catatan) diperlakukan
                // sebagai keterangan rubrik agar tidak hilang dari dokumen.
                if ($kunciMode === '') {
                    $doc['kunci']['rubrik']['lines'][] = self::stripBullet($trimmed);
                }
            }
        }

        $flushSection();
        $flushEsai();
        $flushKunciLainnya();

        // Kunci PG dirender dua kolom (1-5 kiri, sisanya kanan), jadi urutan nomor wajib rapi
        // meski model menuliskannya berselang-seling.
        usort($doc['kunci']['pg'], fn ($a, $b) => (int) $a['number'] <=> (int) $b['number']);

        $hasQuestion = false;
        foreach ($doc['sections'] as $s) {
            if ($s['questions'] !== []) {
                $hasQuestion = true;
                break;
            }
        }

        $doc['parsed'] = $doc['title'] !== '' && $hasQuestion;

        return $doc;
    }

    /** @return array{label:string,value:string}|null */
    private static function matchIdentity(string $line): ?array
    {
        $pattern = '~^('.implode('|', array_map(fn ($l) => preg_quote($l, '~'), self::IDENTITY_LABELS)).')\s*:\s*(.*)$~iu';

        if (preg_match($pattern, $line, $m)) {
            return ['label' => trim($m[1]), 'value' => trim($m[2])];
        }

        return null;
    }

    /**
     * @return array{text:string,images:list<array{path:string,url:string,caption:string}>}
     */
    private static function extractImagesFromText(string $text): array
    {
        $images = QuizImageEnricher::extractTokens($text);

        return [
            'text' => QuizImageEnricher::stripTokens($text),
            'images' => $images,
        ];
    }

    private static function stripBullet(string $line): string
    {
        return trim((string) preg_replace('/^[•✓✔\-\*]\s*/u', '', $line));
    }
}
