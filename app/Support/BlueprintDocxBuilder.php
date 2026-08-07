<?php

namespace App\Support;

/**
 * Penyusun file Word (.docx) untuk kisi-kisi asesmen.
 * Formatnya mengikuti PDF acuan: halaman A4 landscape, header ungu, tabel
 * identitas krem, tabel kisi-kisi berborder emas, halaman kunci jawaban hijau,
 * section biru/hijau, tabel kunci biru, dan rekap penilaian.
 */
final class BlueprintDocxBuilder
{
    use DocxXml;

    private const CONTENT_W = 14558; // A4 landscape, margin 12mm kiri/kanan

    public static function write(string $path, array $doc): bool
    {
        return self::writeDocxPackage($path, self::documentXml($doc));
    }

    public static function documentXml(array $doc): string
    {
        $body = self::topbar($doc)
            .self::identity($doc['identity'] ?? [])
            .self::blueprintTable($doc['rows'] ?? [])
            .self::notes($doc)
            .self::signature()
            .self::answers($doc);

        return self::documentXmlFor($body, [
            'orientation' => 'landscape',
            'margin' => ['top' => 720, 'right' => 680, 'bottom' => 720, 'left' => 680],
        ]);
    }

    private static function topbar(array $doc): string
    {
        $title = (string) ($doc['title'] ?? 'KISI-KISI PENILAIAN');
        $subject = trim((string) ($doc['subject'] ?? ''));

        $content = self::p([self::run($title, true, false, 28, 'FFFFFF')], ['align' => 'center', 'after' => $subject !== '' ? 40 : 0]);
        if ($subject !== '') {
            $content .= self::p([self::run($subject, true, false, 26, 'FFFFFF')], ['align' => 'center', 'after' => 0]);
        }

        return self::tbl(
            [self::CONTENT_W],
            self::tr(self::tc([$content], [
                'w' => self::CONTENT_W,
                'fill' => '5B2D91',
                'borders' => false,
            ])),
            false,
        );
    }

    private static function identity(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $cols = [2800, 4480, 2800, 4478];
        $rows = '';
        foreach (array_chunk($items, 2) as $pair) {
            $cells = '';
            foreach ($pair as $item) {
                $cells .= self::tc([self::p([self::run((string) $item['label'], true, false, 20)], ['after' => 0])], [
                    'w' => $cols[0],
                    'fill' => 'FFF8E8',
                    'border_sz' => 6,
                ]);
                $cells .= self::tc([self::p([self::run(': '.(string) $item['value'], false, false, 20)], ['after' => 0])], [
                    'w' => $cols[1],
                    'fill' => 'FFF8E8',
                    'border_sz' => 6,
                ]);
            }
            if (count($pair) === 1) {
                $cells .= self::tc([self::p([self::run(' ')], ['after' => 0])], ['w' => $cols[2], 'fill' => 'FFF8E8', 'border_sz' => 6]);
                $cells .= self::tc([self::p([self::run(' ')], ['after' => 0])], ['w' => $cols[3], 'fill' => 'FFF8E8', 'border_sz' => 6]);
            }
            $rows .= self::tr($cells);
        }

        return self::tbl($cols, $rows, true);
    }

    private static function blueprintTable(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $cols = [520, 2200, 2600, 4620, 1420, 1800, 1398];
        $headers = [
            'No.',
            'Elemen / Capaian Pembelajaran',
            'Materi Pokok',
            'Indikator Soal',
            'Level Kognitif (Taksonomi Bloom)',
            'Bentuk Soal',
            'No. Soal',
        ];

        $trs = self::tr(self::cells($headers, $cols, true, '5B2D91', 18, [], 'FFFFFF'));
        foreach ($rows as $i => $row) {
            $fill = $i % 2 === 1 ? 'F3EEF8' : null;
            $trs .= self::tr(self::cells([
                (string) $row['no'],
                (string) $row['element'],
                (string) $row['material'],
                (string) $row['indicator'],
                (string) $row['level'],
                (string) $row['shape'],
                (string) $row['question_no'],
            ], $cols, false, $fill, 16, [0, 4, 5, 6]));
        }

        return self::tbl($cols, $trs, true);
    }

    private static function notes(array $doc): string
    {
        $xml = '';
        if (($doc['legend'] ?? []) !== []) {
            $xml .= self::p([self::run(implode('    ', $doc['legend']), false, false, 16)], ['after' => 40]);
        }
        if (($doc['note'] ?? '') !== '') {
            $xml .= self::p([self::run('Catatan: '.$doc['note'], false, false, 16)], ['after' => 80]);
        }

        return $xml;
    }

    private static function signature(): string
    {
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $date = 'Tanjungpinang, '.now()->format('j').' '.$bulan[(int) now()->format('n')].' '.now()->format('Y');
        $half = (int) floor(self::CONTENT_W / 2);

        $left = self::p([self::run('Mengetahui,')], ['align' => 'center', 'after' => 0])
            .self::p([self::run('Kepala Sekolah')], ['align' => 'center', 'after' => 520])
            .self::p([self::run('(_______________________)')], ['align' => 'center', 'after' => 0]);
        $right = self::p([self::run($date)], ['align' => 'center', 'after' => 0])
            .self::p([self::run('Guru Mata Pelajaran')], ['align' => 'center', 'after' => 520])
            .self::p([self::run('(_______________________)')], ['align' => 'center', 'after' => 0]);

        return self::tbl([$half, $half], self::tr(
            self::tc([$left], ['w' => $half, 'borders' => false])
            .self::tc([$right], ['w' => $half, 'borders' => false])
        ), false);
    }

    private static function answers(array $doc): string
    {
        if (($doc['answer_sections'] ?? []) === [] && ($doc['recap'] ?? []) === []) {
            return '';
        }

        $xml = self::pageBreak().self::answerTop($doc);

        foreach ($doc['answer_sections'] as $section) {
            $isGreen = str_contains(mb_strtoupper((string) $section['heading'], 'UTF-8'), 'BENAR');
            $xml .= self::sectionBar((string) $section['heading'], $isGreen ? '00574B' : '1565C0');
            $headers = $section['headers'] ?: ['No.', 'Kunci', 'Jawaban'];
            $cols = self::answerCols(count($headers));
            $trs = self::tr(self::cells($headers, $cols, true, 'B7D8F1', 18));
            foreach ($section['rows'] as $i => $row) {
                $trs .= self::tr(self::cells(array_values($row), $cols, false, $i % 2 === 1 ? 'E3F2FD' : null, 16, [0, 1]));
            }
            $xml .= self::tbl($cols, $trs, true);
        }

        if (($doc['recap'] ?? []) !== []) {
            $xml .= self::sectionBar('REKAP PENILAIAN', '1565C0');
            $cols = [2000, 3400, 2600, 3000, 3558];
            $trs = self::tr(self::cells(['Bagian', 'Bentuk Soal', 'Jumlah Soal', 'Skor per Soal', 'Total Skor'], $cols, true, 'E3F2FD', 18));
            foreach ($doc['recap'] as $row) {
                $trs .= self::tr(self::cells(array_values($row), $cols, false, null, 16));
            }
            $xml .= self::tbl($cols, $trs, true);
        }

        if (($doc['recap_note'] ?? '') !== '') {
            $xml .= self::p([self::run('Keterangan: '.$doc['recap_note'], false, false, 16)], ['after' => 0]);
        }

        return $xml;
    }

    private static function answerTop(array $doc): string
    {
        $title = (string) ($doc['answer_title'] ?? 'KUNCI JAWABAN');
        $subtitle = trim((string) ($doc['answer_subtitle'] ?? ''));
        $content = self::p([self::run($title, true, false, 28, 'FFFFFF')], ['align' => 'center', 'after' => $subtitle !== '' ? 40 : 0]);
        if ($subtitle !== '') {
            $content .= self::p([self::run($subtitle, true, false, 24, 'FFFFFF')], ['align' => 'center', 'after' => 0]);
        }

        return self::tbl([self::CONTENT_W], self::tr(self::tc([$content], [
            'w' => self::CONTENT_W,
            'fill' => '2E7D32',
            'borders' => false,
        ])), false);
    }

    private static function sectionBar(string $text, string $fill): string
    {
        return self::tbl([self::CONTENT_W], self::tr(self::tc([
            self::p([self::run($text, true, false, 20, 'FFFFFF')], ['after' => 0]),
        ], [
            'w' => self::CONTENT_W,
            'fill' => $fill,
            'borders' => false,
        ])), false);
    }

    /** @param list<string> $values @param list<int> $cols @param list<int> $centerCols */
    private static function cells(array $values, array $cols, bool $bold = false, ?string $fill = null, int $size = 18, array $centerCols = [], ?string $color = null): string
    {
        $xml = '';
        foreach ($cols as $i => $w) {
            $align = $bold || in_array($i, $centerCols, true) ? 'center' : 'left';
            $cellColor = $color;
            if ($cellColor === null && ! $bold && $i === 1 && $centerCols !== [] && in_array(1, $centerCols, true)) {
                $cellColor = '0B66D8';
            }
            $xml .= self::tc([self::p([self::run((string) ($values[$i] ?? ''), $bold, false, $size, $cellColor)], [
                'align' => $align,
                'after' => 0,
            ])], [
                'w' => $w,
                'fill' => $fill,
                'border_sz' => 6,
            ]);
        }

        return $xml;
    }

    /** @return list<int> */
    private static function answerCols(int $count): array
    {
        if ($count <= 2) {
            return [3000, self::CONTENT_W - 3000];
        }
        if ($count === 3) {
            return [2200, 3000, self::CONTENT_W - 5200];
        }

        $base = (int) floor(self::CONTENT_W / max(1, $count));

        return array_fill(0, $count, $base);
    }
}
