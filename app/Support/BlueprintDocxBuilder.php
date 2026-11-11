<?php

namespace App\Support;

/**
 * Penyusun file Word (.docx) untuk kisi-kisi asesmen.
 * Formatnya mengikuti dokumen resmi sekolah: halaman A4 landscape, kop surat resmi,
 * header ungu, tabel identitas krem rapi berborder emas, tujuan pembelajaran,
 * tabel kisi-kisi berborder emas dengan header berulang, tanda tangan,
 * halaman kunci jawaban hijau, section biru/hijau, tabel kunci rapi, dan rekap penilaian.
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
        $is10Col = ($doc['table_type'] ?? '') === '10col'
            || isset($doc['rows'][0]['cp'])
            || isset($doc['rows'][0]['tp']);

        $body = self::kop($doc['kop'] ?? [])
            .self::topbar($doc, $is10Col)
            .self::identity($doc['identity'] ?? [], $is10Col)
            .self::competencies($doc['competencies'] ?? [])
            .self::blueprintTable($doc, $is10Col)
            .self::notes($doc)
            .self::signature($doc)
            .self::answers($doc, $is10Col);

        return self::documentXmlFor($body, [
            'orientation' => 'landscape',
            'margin' => ['top' => 720, 'right' => 680, 'bottom' => 720, 'left' => 680],
        ]);
    }

    private static function kop(array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        $xml = '';
        foreach ($lines as $i => $line) {
            $isCaps = mb_strtoupper($line, 'UTF-8') === $line && ! preg_match('/\d{5}|@|http/u', $line);
            $sz = $isCaps ? ($i < 2 ? 24 : 19) : 16;
            $xml .= self::p([self::run($line, $isCaps, false, $sz)], ['align' => 'center', 'after' => 0]);
        }

        return $xml.'<w:p><w:pPr><w:pBdr><w:bottom w:val="double" w:sz="18" w:space="2" w:color="000000"/></w:pBdr><w:spacing w:after="140"/></w:pPr></w:p>';
    }

    private static function topbar(array $doc, bool $is10Col = false): string
    {
        if ($is10Col) {
            $title = (string) ($doc['title'] ?? 'KISI-KISI SOAL');
            $subject = trim((string) ($doc['subject'] ?? ''));
            $academicYear = trim((string) ($doc['academic_year'] ?? ''));

            $xml = self::p([self::run($title, true, false, 24, '000000')], ['align' => 'center', 'after' => 30]);
            if ($subject !== '') {
                $xml .= self::p([self::run($subject, true, false, 20, '000000')], ['align' => 'center', 'after' => 20]);
            }
            if ($academicYear !== '') {
                $xml .= self::p([self::run($academicYear, true, false, 18, '000000')], ['align' => 'center', 'after' => 60]);
            }

            return $xml;
        }

        $title = (string) ($doc['title'] ?? 'KISI-KISI PENILAIAN');
        $subject = trim((string) ($doc['subject'] ?? ''));

        $content = self::p([self::run($title, true, false, 28, 'FFFFFF')], ['align' => 'center', 'after' => $subject !== '' ? 40 : 0]);
        if ($subject !== '') {
            $content .= self::p([self::run($subject, true, false, 24, 'FFFFFF')], ['align' => 'center', 'after' => 0]);
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

    private static function identity(array $items, bool $is10Col = false): string
    {
        if ($items === []) {
            return '';
        }

        if ($is10Col) {
            $colW1 = 2800;
            $colW2 = 8000;
            $cols = [$colW1, $colW2];
            $rows = '';

            foreach ($items as $item) {
                $c1 = self::tc([self::p([self::run((string) $item['label'], true, false, 18)], ['after' => 20])], [
                    'w' => $colW1,
                    'borders' => false,
                ]);
                $c2 = self::tc([self::p([self::run(': '.(string) $item['value'], false, false, 18)], ['after' => 20])], [
                    'w' => $colW2,
                    'borders' => false,
                ]);
                $rows .= self::tr($c1.$c2);
            }

            return self::tbl($cols, $rows, false).'<w:p><w:pPr><w:spacing w:after="80"/></w:pPr></w:p>';
        }

        $cols = [2400, 4879, 2400, 4879];
        $rows = '';
        foreach (array_chunk($items, 2) as $pair) {
            $cells = '';
            foreach ($pair as $idx => $item) {
                $cW1 = $cols[$idx * 2];
                $cW2 = $cols[$idx * 2 + 1];
                $cells .= self::tc([self::p([self::run((string) $item['label'], true, false, 18)], ['after' => 0])], [
                    'w' => $cW1,
                    'fill' => 'FFF8E8',
                    'border_sz' => 6,
                ]);
                $cells .= self::tc([self::p([self::run(': '.(string) $item['value'], false, false, 18)], ['after' => 0])], [
                    'w' => $cW2,
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

    private static function competencies(array $items): string
    {
        if ($items === []) {
            return '';
        }

        $xml = self::p([self::run('Tujuan Pembelajaran / Capaian Pembelajaran:', true, false, 18, '78350F')], ['after' => 20]);
        foreach ($items as $i => $item) {
            $num = ($i + 1).'. ';
            $xml .= self::p([
                self::run($num, true, false, 16),
                self::run($item, false, false, 16),
            ], ['left' => 360, 'hanging' => 360, 'after' => 20]);
        }

        return self::tbl([self::CONTENT_W], self::tr(self::tc([$xml], [
            'w' => self::CONTENT_W,
            'fill' => 'FFFDF5',
            'border_sz' => 6,
        ])), true);
    }

    private static function blueprintTable(array $doc, bool $is10Col = false): string
    {
        $rows = $doc['rows'] ?? [];
        if ($rows === []) {
            return '';
        }

        if ($is10Col) {
            $cols = [450, 1600, 1500, 1400, 1800, 700, 3908, 1100, 900, 1200];
            $headers = [
                'NO',
                'Capaian Pembelajaran',
                'Lingkup Materi',
                'Elemen',
                'Tujuan Pembelajaran',
                'Kelas',
                'Indikator Soal',
                'Level Kognitif',
                'Nomor Soal',
                'Bentuk Soal',
            ];

            $trs = self::tr(self::cells($headers, $cols, true, 'B8D8E8', 16, [0, 5, 7, 8, 9], '000000', 4), ['header' => true]);
            foreach ($rows as $i => $row) {
                $trs .= self::tr(self::cells([
                    (string) ($row['no'] ?? ''),
                    (string) ($row['cp'] ?? '-'),
                    (string) ($row['material'] ?? '-'),
                    (string) ($row['element'] ?? '-'),
                    (string) ($row['tp'] ?? '-'),
                    (string) ($row['kelas'] ?? '-'),
                    (string) ($row['indicator'] ?? '-'),
                    (string) ($row['level'] ?? '-'),
                    (string) ($row['question_no'] ?? $row['no'] ?? ''),
                    (string) ($row['shape'] ?? '-'),
                ], $cols, false, null, 15, [0, 5, 7, 8, 9], '000000', 4), ['cantSplit' => true]);
            }

            return self::tbl($cols, $trs, true);
        }

        $cols = [600, 2200, 2600, 4758, 1400, 1600, 1400];
        $headers = [
            'No.',
            'Elemen / Capaian Pembelajaran',
            'Materi Pokok',
            'Indikator Soal',
            'Level Kognitif (Taksonomi Bloom)',
            'Bentuk Soal',
            'No. Soal',
        ];

        $trs = self::tr(self::cells($headers, $cols, true, '5B2D91', 18, [], 'FFFFFF'), ['header' => true]);
        foreach ($rows as $i => $row) {
            $fill = $i % 2 === 1 ? 'F3EEF8' : null;
            $trs .= self::tr(self::cells([
                (string) ($row['no'] ?? ''),
                (string) ($row['element'] ?? ''),
                (string) ($row['material'] ?? ''),
                (string) ($row['indicator'] ?? ''),
                (string) ($row['level'] ?? ''),
                (string) ($row['shape'] ?? ''),
                (string) ($row['question_no'] ?? $row['no'] ?? ''),
            ], $cols, false, $fill, 16, [0, 4, 5, 6]), ['cantSplit' => true]);
        }

        return self::tbl($cols, $trs, true);
    }

    private static function notes(array $doc): string
    {
        $xml = '';
        if (($doc['legend'] ?? []) !== []) {
            $xml .= self::p([self::run('Legenda Level Kognitif: '.implode('    ', $doc['legend']), false, false, 16)], ['after' => 40]);
        }
        if (($doc['note'] ?? '') !== '') {
            $xml .= self::p([self::run('Catatan: '.$doc['note'], false, false, 16)], ['after' => 80]);
        }

        return $xml;
    }

    private static function signature(array $doc = []): string
    {
        $bulan = [
            1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
            5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
            9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $date = 'Tanjungpinang, '.now()->format('j').' '.$bulan[(int) now()->format('n')].' '.now()->format('Y');
        $half = (int) floor(self::CONTENT_W / 2);

        $kepala = $doc['kepala_sekolah'] ?? SchoolLetterhead::kepalaSekolah();
        $guru = $doc['guru_mapel'] ?? (auth()->user()?->name ?? 'Guru Mata Pelajaran');

        $left = self::p([self::run('Mengetahui,')], ['align' => 'center', 'after' => 0])
            .self::p([self::run('Kepala Sekolah')], ['align' => 'center', 'after' => 520])
            .self::p([self::run('('.$kepala.')', true)], ['align' => 'center', 'after' => 0]);
        $right = self::p([self::run($date)], ['align' => 'center', 'after' => 0])
            .self::p([self::run('Guru Mata Pelajaran')], ['align' => 'center', 'after' => 520])
            .self::p([self::run('('.$guru.')', true)], ['align' => 'center', 'after' => 0]);

        return self::tbl([$half, $half], self::tr(
            self::tc([$left], ['w' => $half, 'borders' => false])
            .self::tc([$right], ['w' => $half, 'borders' => false])
        ), false);
    }

    private static function answers(array $doc, bool $is10Col = false): string
    {
        if (($doc['answer_sections'] ?? []) === [] && ($doc['recap'] ?? []) === []) {
            return '';
        }

        $xml = self::pageBreak().self::answerTop($doc, $is10Col);

        foreach ($doc['answer_sections'] as $section) {
            $isGreen = str_contains(mb_strtoupper((string) $section['heading'], 'UTF-8'), 'BENAR');
            $sectionFill = $is10Col ? 'D8ECF8' : ($isGreen ? '00574B' : '1565C0');
            $sectionText = $is10Col ? '000000' : 'FFFFFF';
            $xml .= self::sectionBar((string) $section['heading'], $sectionFill, $sectionText, $is10Col ? 4 : 0);

            $headers = $section['headers'] ?: ['No.', 'Kunci', 'Jawaban'];
            $cols = self::answerCols($headers);
            $hdrFill = $is10Col ? 'B8D8E8' : 'B7D8F1';
            $trs = self::tr(self::cells($headers, $cols, true, $hdrFill, 16, [0, 1], '000000', $is10Col ? 4 : 6), ['header' => true]);
            foreach ($section['rows'] as $i => $row) {
                if (empty($row)) {
                    continue;
                }
                $rowValues = array_values($row);
                $centerCols = [0];
                if (count($cols) === 3 && ! str_contains(mb_strtoupper($headers[1] ?? '', 'UTF-8'), 'ISTILAH')) {
                    $centerCols[] = 1;
                }
                $rowFill = $is10Col ? null : ($i % 2 === 1 ? 'E3F2FD' : null);
                $trs .= self::tr(self::cells($rowValues, $cols, false, $rowFill, 15, $centerCols, '000000', $is10Col ? 4 : 6), ['cantSplit' => true]);
            }
            $xml .= self::tbl($cols, $trs, true);
        }

        if (($doc['recap'] ?? []) !== []) {
            $xml .= self::sectionBar('REKAP PENILAIAN', $is10Col ? 'D8ECF8' : '1565C0', $is10Col ? '000000' : 'FFFFFF', $is10Col ? 4 : 0);
            $cols = [1500, 4500, 2800, 2800, 2958];
            $recapHdrFill = $is10Col ? 'B8D8E8' : 'E3F2FD';
            $trs = self::tr(self::cells(['Bagian', 'Bentuk Soal', 'Jumlah Soal', 'Skor per Soal', 'Total Skor'], $cols, true, $recapHdrFill, 16, [0, 2, 3, 4], '000000', $is10Col ? 4 : 6), ['header' => true]);
            foreach ($doc['recap'] as $row) {
                if (empty($row)) {
                    continue;
                }
                $trs .= self::tr(self::cells(array_values($row), $cols, false, null, 15, [0, 2, 3, 4], '000000', $is10Col ? 4 : 6), ['cantSplit' => true]);
            }
            $xml .= self::tbl($cols, $trs, true);
        }

        if (($doc['recap_note'] ?? '') !== '') {
            $xml .= self::p([self::run('Keterangan: '.$doc['recap_note'], false, false, 16)], ['after' => 0]);
        }

        return $xml;
    }

    private static function answerTop(array $doc, bool $is10Col = false): string
    {
        $title = (string) ($doc['answer_title'] ?? 'KUNCI JAWABAN');
        $subtitle = trim((string) ($doc['answer_subtitle'] ?? ''));

        if ($is10Col) {
            $content = self::p([self::run($title, true, false, 22, '000000')], ['align' => 'center', 'after' => $subtitle !== '' ? 20 : 0]);
            if ($subtitle !== '') {
                $content .= self::p([self::run($subtitle, false, false, 18, '000000')], ['align' => 'center', 'after' => 0]);
            }

            return self::tbl([self::CONTENT_W], self::tr(self::tc([$content], [
                'w' => self::CONTENT_W,
                'fill' => 'B8D8E8',
                'border_sz' => 4,
            ])), true).'<w:p><w:pPr><w:spacing w:after="60"/></w:pPr></w:p>';
        }

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

    private static function sectionBar(string $text, string $fill = 'D8ECF8', string $textColor = '000000', int $borderSz = 4): string
    {
        return self::tbl([self::CONTENT_W], self::tr(self::tc([
            self::p([self::run($text, true, false, 18, $textColor)], ['after' => 0]),
        ], [
            'w' => self::CONTENT_W,
            'fill' => $fill,
            'border_sz' => $borderSz,
            'borders' => $borderSz > 0,
        ])), $borderSz > 0);
    }

    /** @param list<string> $values @param list<int> $cols @param list<int> $centerCols */
    private static function cells(array $values, array $cols, bool $bold = false, ?string $fill = null, int $size = 18, array $centerCols = [], ?string $color = null, int $borderSz = 4): string
    {
        $xml = '';
        foreach ($cols as $i => $w) {
            $align = $bold || in_array($i, $centerCols, true) ? 'center' : 'left';
            $cellColor = $color ?? '000000';
            $xml .= self::tc([self::p([self::run((string) ($values[$i] ?? ''), $bold, false, $size, $cellColor)], [
                'align' => $align,
                'after' => 0,
            ])], [
                'w' => $w,
                'fill' => $fill,
                'border_sz' => $borderSz,
            ]);
        }

        return $xml;
    }

    /** @param list<string> $headers @return list<int> */
    private static function answerCols(array $headers): array
    {
        $count = count($headers);
        if ($count <= 2) {
            return [1500, self::CONTENT_W - 1500];
        }
        if ($count === 3) {
            $h1Upper = mb_strtoupper($headers[1] ?? '', 'UTF-8');
            if (str_contains($h1Upper, 'ISTILAH') || str_contains($h1Upper, 'KONSEP')) {
                return [1500, 6529, 6529];
            }

            return [1000, 2200, self::CONTENT_W - 3200];
        }
        if ($count === 4) {
            return [1000, 2200, 5679, 5679];
        }

        $base = (int) floor(self::CONTENT_W / max(1, $count));
        $cols = array_fill(0, $count, $base);
        $cols[$count - 1] = self::CONTENT_W - ($base * ($count - 1));

        return $cols;
    }
}
