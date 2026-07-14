<?php

namespace App\Support;

/**
 * Penyusun file Word (.docx) untuk dokumen perangkat ajar hasil parse
 * LearningDocument. Merender struktur RPM formal sebagai tabel Word asli:
 * kop bergaris tebal, tabel IDENTIFIKASI/DESAIN/PENGALAMAN/ASESMEN dengan
 * kolom label kiri (vertical merge), checkbox DPL dua kolom, blok tanda
 * tangan dua kolom, dan lampiran per halaman.
 */
final class LearningDocxBuilder
{
    use DocxXml;

    private const CONTENT_W = 9026; // lebar area isi A4 (dxa) dengan margin 1 inci

    private const SEC_W = 2000;

    private const SUB_W = 1750;

    private const BODY_W = 5276;

    public static function write(string $path, array $doc): bool
    {
        return self::writeDocxPackage($path, self::documentXml($doc));
    }

    public static function documentXml(array $doc): string
    {
        $body = self::kop($doc['kop'])
            .self::title($doc)
            .self::identity($doc['identity'])
            .self::labeledTable('IDENTIFIKASI', $doc['identifikasi'], true)
            .self::labeledTable('DESAIN PEMBELAJARAN', $doc['desain'])
            .self::pengalaman($doc['pengalaman'])
            .self::labeledTable('ASESMEN PEMBELAJARAN', $doc['asesmen'])
            .self::signature($doc['signature'])
            .self::lampiran($doc['lampiran']);

        return self::documentXmlFor($body);
    }

    // ---------- Bagian dokumen ----------

    private static function kop(array $lines): string
    {
        if ($lines === []) {
            return '';
        }

        $xml = '';
        foreach ($lines as $i => $line) {
            $isCaps = mb_strtoupper($line, 'UTF-8') === $line && ! preg_match('/\d{5}|@|http/u', $line);
            // Acuan PDF/DOCX: yayasan+sekolah 13pt & 12pt bold, akreditasi 10pt bold, alamat 8pt.
            $sz = match (true) {
                $i === 0 && $isCaps => 26,
                $i === 1 && $isCaps => 24,
                $isCaps => 20,
                default => 16,
            };
            $xml .= self::p([self::run($line, $isCaps || $i < 3, false, $sz)], ['align' => 'center', 'after' => 0]);
        }

        // Garis tebal di bawah kop (acuan: border bawah tebal).
        return $xml.'<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="24" w:space="1" w:color="000000"/></w:pBdr><w:spacing w:after="120"/></w:pPr></w:p>';
    }

    private static function title(array $doc): string
    {
        $xml = self::p([self::run($doc['title'], true, false, 26)], ['align' => 'center', 'after' => 40, 'before' => 120]);
        if ($doc['subtitle'] !== '') {
            $xml .= self::p([self::run($doc['subtitle'], true, false, 22)], ['align' => 'center', 'after' => 160]);
        }

        return $xml;
    }

    private static function identity(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $trs = '';
        foreach ($rows as $row) {
            $trs .= self::tr(
                self::tc([self::p([self::run($row['label'])], ['after' => 20])], ['w' => 2500, 'borders' => false])
                .self::tc([self::p([self::run(':')], ['after' => 20])], ['w' => 250, 'borders' => false])
                .self::tc([self::p([self::run($row['value'])], ['after' => 20])], ['w' => self::CONTENT_W - 2750, 'borders' => false])
            );
        }

        return self::tbl([2500, 250, self::CONTENT_W - 2750], $trs, false);
    }

    private static function labeledTable(string $sectionLabel, array $rows, bool $withDpl = false): string
    {
        if ($rows === []) {
            return '';
        }

        $trs = '';
        foreach ($rows as $i => $row) {
            $bodyXml = '';
            if ($withDpl && str_starts_with($row['label'], 'Dimensi Profil Lulusan') && $row['dpl'] !== []) {
                foreach (($row['intro'] ?: $row['lines']) as $line) {
                    $bodyXml .= self::cellLine($line);
                }
                // Tabel bersarang dalam sel wajib diakhiri satu paragraf kosong.
                $bodyXml .= self::dplTable($row['dpl']);
            } else {
                foreach ($row['lines'] as $line) {
                    $bodyXml .= self::cellLine($line);
                }
                $bodyXml = $bodyXml !== '' ? $bodyXml : '<w:p/>';
            }

            $trs .= self::tr(
                self::secCell($sectionLabel, $i === 0)
                .self::tc([self::p([self::run($row['label'], true)], ['after' => 20])], ['w' => self::SUB_W])
                .self::tc([$bodyXml], ['w' => self::BODY_W])
            );
        }

        return self::tbl([self::SEC_W, self::SUB_W, self::BODY_W], $trs);
    }

    private static function pengalaman(array $stages): string
    {
        if ($stages === []) {
            return '';
        }

        $trs = '';
        $first = true;
        foreach ($stages as $stage) {
            $headParas = self::p([self::run($stage['heading'], true)], ['after' => 0]);
            if ($stage['subtitle'] !== '') {
                $headParas .= self::p([self::run($stage['subtitle'], false, true, 18)], ['after' => 0]);
            }
            $trs .= self::tr(
                self::secCell('PENGALAMAN BELAJAR', $first)
                .self::tc([$headParas], ['w' => self::SUB_W + self::BODY_W, 'span' => 2, 'fill' => 'F2F2F2'])
            );
            $first = false;

            if ($stage['items'] === []) {
                continue;
            }

            $items = '';
            foreach ($stage['items'] as $item) {
                $items .= match ($item['type']) {
                    'check' => self::p([self::run('✓ '.$item['text'])], ['after' => 20, 'left' => 284, 'hanging' => 284]),
                    'quote' => self::p([self::run($item['text'], false, true)], ['after' => 20]),
                    default => self::p([self::run($item['text'])], ['after' => 20]),
                };
            }
            $trs .= self::tr(
                self::secCell('PENGALAMAN BELAJAR', false)
                .self::tc(['<w:p/>'], ['w' => self::SUB_W])
                .self::tc([$items], ['w' => self::BODY_W])
            );
        }

        return self::tbl([self::SEC_W, self::SUB_W, self::BODY_W], $trs);
    }

    private static function signature(array $signature): string
    {
        if ($signature['date'] === '' && $signature['rows'] === []) {
            return '';
        }

        $xml = '';
        if ($signature['date'] !== '') {
            $xml .= self::p([self::run($signature['date'])], ['align' => 'right', 'before' => 240, 'after' => 40]);
        }

        if ($signature['rows'] === []) {
            return $xml;
        }

        $firstNameIdx = null;
        foreach ($signature['rows'] as $k => $r) {
            if (preg_match('/^(NIK|NIP)\b/u', trim($r[0].$r[1]))) {
                $firstNameIdx = max(0, $k - 1);
                break;
            }
        }

        $half = (int) floor(self::CONTENT_W / 2);
        $trs = '';
        foreach ($signature['rows'] as $k => $r) {
            $bold = $firstNameIdx !== null && $k === $firstNameIdx;
            if ($bold) {
                // Ruang kosong untuk tanda tangan basah.
                for ($s = 0; $s < 3; $s++) {
                    $trs .= self::tr(
                        self::tc(['<w:p/>'], ['w' => $half, 'borders' => false])
                        .self::tc(['<w:p/>'], ['w' => $half, 'borders' => false])
                    );
                }
            }
            $trs .= self::tr(
                self::tc([self::p([self::run($r[0], $bold)], ['after' => 20])], ['w' => $half, 'borders' => false])
                .self::tc([self::p([self::run($r[1], $bold)], ['after' => 20, 'align' => 'center'])], ['w' => $half, 'borders' => false])
            );
        }

        return $xml.self::tbl([$half, $half], $trs, false);
    }

    private static function lampiran(array $lampiranList): string
    {
        $xml = '';
        foreach ($lampiranList as $lampiran) {
            $xml .= self::pageBreak();
            $xml .= self::p([self::run($lampiran['heading'], true, false, 22)], ['after' => 160]);

            foreach ($lampiran['blocks'] as $block) {
                if ($block['type'] === 'table') {
                    $xml .= self::lampiranTable($block['rows']).'<w:p/>';

                    continue;
                }
                foreach ($block['lines'] as $line) {
                    if (str_starts_with($line, '•') && str_contains($line, ':')) {
                        [$label, $rest] = explode(':', $line, 2);
                        $xml .= self::p([self::run(rtrim($label), true), self::run(' : '.trim($rest))], ['after' => 40]);

                        continue;
                    }
                    $opts = ['after' => 40];
                    if (preg_match('/^[a-e][\.\)]\s/u', $line)) {
                        $opts['left'] = 567;
                    } elseif (preg_match('/^\d{1,2}[\.\)]\s/u', $line)) {
                        $opts['left'] = 227;
                    }
                    $bold = (bool) preg_match('/^[A-Z][\.\)]\s/u', $line) || (bool) preg_match('/^.{1,60}:$/u', $line);
                    $xml .= self::p([self::run($line, $bold)], $opts);
                }
            }
        }

        return $xml;
    }

    // ---------- Komponen kecil ----------

    private static function dplTable(array $items): string
    {
        $half = (int) ceil(count($items) / 2);
        $left = array_slice($items, 0, $half);
        $right = array_slice($items, $half);
        $colW = (int) floor(self::BODY_W / 2) - 100;

        $trs = '';
        for ($j = 0; $j < $half; $j++) {
            $cells = '';
            foreach ([$left[$j] ?? null, $right[$j] ?? null] as $item) {
                $text = $item !== null ? ($item['checked'] ? '☑ ' : '☐ ').$item['label'] : '';
                $cells .= self::tc(
                    [self::p([self::run($text, true)], ['after' => 20, 'left' => 284, 'hanging' => 284])],
                    ['w' => $colW, 'borders' => false],
                );
            }
            $trs .= self::tr($cells);
        }

        return self::tbl([$colW, $colW], $trs, false, false).'<w:p/>';
    }

    private static function lampiranTable(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $first = array_map('trim', $rows[0]);
        // Acuan PDF LAMPIRAN 2: header 2 baris — Kompetensi (merge) + Kriteria (span 4),
        // lalu subheader Baru Mulai | Berkembang | Cakap | Mahir.
        $isRubrik5 = count($first) === 5
            && preg_match('/^kompetensi$/iu', $first[0])
            && preg_match('/^baru\s*mulai$/iu', $first[1]);

        if ($isRubrik5) {
            return self::rubrikTable(array_slice($rows, 1));
        }

        $cols = max(1, count($first));
        $colW = (int) floor(self::CONTENT_W / $cols);
        $grid = array_fill(0, $cols, $colW);

        $trs = '';
        foreach ($rows as $ri => $cells) {
            $cells = array_pad($cells, $cols, '');
            $tcXml = '';
            foreach (array_slice($cells, 0, $cols) as $cell) {
                $tcXml .= self::tc(
                    [self::p([self::run($cell, $ri === 0, false, 18)], ['after' => 20])],
                    ['w' => $colW, 'fill' => $ri === 0 ? 'FCE4B6' : null],
                );
            }
            $trs .= self::tr($tcXml);
        }

        return self::tbl($grid, $trs);
    }

    /** Rubrik LAMPIRAN 2 sesuai PDF acuan (header Kompetensi + Kriteria merge). */
    private static function rubrikTable(array $dataRows): string
    {
        $kompW = 2200;
        $critW = (int) floor((self::CONTENT_W - $kompW) / 4);
        $grid = [$kompW, $critW, $critW, $critW, $critW];
        $peach = 'FCE4B6';

        $header = self::tr(
            self::tc(
                [self::p([self::run('Kompetensi', true, false, 18)], ['after' => 20])],
                ['w' => $kompW, 'vmerge' => 'restart', 'fill' => $peach],
            )
            .self::tc(
                [self::p([self::run('Kriteria', true, false, 18)], ['after' => 20, 'align' => 'center'])],
                ['w' => $critW * 4, 'span' => 4, 'fill' => $peach],
            )
        );
        $subheader = self::tr(
            self::tc(['<w:p/>'], ['w' => $kompW, 'vmerge' => 'continue', 'fill' => $peach])
            .self::tc([self::p([self::run('Baru Mulai', true, false, 18)], ['after' => 20])], ['w' => $critW, 'fill' => $peach])
            .self::tc([self::p([self::run('Berkembang', true, false, 18)], ['after' => 20])], ['w' => $critW, 'fill' => $peach])
            .self::tc([self::p([self::run('Cakap', true, false, 18)], ['after' => 20])], ['w' => $critW, 'fill' => $peach])
            .self::tc([self::p([self::run('Mahir', true, false, 18)], ['after' => 20])], ['w' => $critW, 'fill' => $peach])
        );

        $trs = $header.$subheader;
        foreach ($dataRows as $cells) {
            $cells = array_pad(array_map('trim', $cells), 5, '');
            $tcXml = '';
            foreach ($cells as $i => $cell) {
                $tcXml .= self::tc(
                    [self::p([self::run($cell, false, false, 18)], ['after' => 20])],
                    ['w' => $i === 0 ? $kompW : $critW],
                );
            }
            $trs .= self::tr($tcXml);
        }

        return self::tbl($grid, $trs);
    }

    private static function cellLine(string $line): string
    {
        $bold = (bool) preg_match('/^.{1,60}:$/u', $line);

        return self::p([self::run($line, $bold)], ['after' => 20]);
    }

    private static function secCell(string $label, bool $first): string
    {
        return self::tc(
            [$first ? self::p([self::run($label, true)], ['after' => 0]) : '<w:p/>'],
            [
                'w' => self::SEC_W,
                'vmerge' => $first ? 'restart' : 'continue',
                'fill' => 'F2F2F2',
            ],
        );
    }
}
