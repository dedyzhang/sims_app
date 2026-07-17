<?php

namespace App\Support;

/**
 * Penyusun file Word (.docx) untuk dokumen soal evaluasi hasil parse QuizDocument.
 * Mengikuti format acuan: kop sekolah bergaris tebal, judul dan sub-judul di tengah,
 * tabel identitas, petunjuk pengerjaan, bagian soal dengan opsi menjorok, lalu kunci
 * jawaban di halaman terpisah dengan tabel jawaban pilihan ganda dua kolom.
 */
final class QuizDocxBuilder
{
    use DocxXml;

    private const CONTENT_W = 9026; // lebar area isi A4 (dxa) dengan margin 1 inci

    public static function write(string $path, array $doc): bool
    {
        return self::writeDocxPackage($path, self::documentXml($doc));
    }

    public static function documentXml(array $doc): string
    {
        $body = self::kop($doc['kop'])
            .self::title($doc)
            .self::identity($doc['identity'])
            .self::petunjuk($doc['petunjuk'])
            .self::sections($doc['sections'])
            .self::kunci($doc['kunci']);

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
            $sz = $isCaps ? ($i < 2 ? 26 : 20) : 16;
            $xml .= self::p([self::run($line, $isCaps, false, $sz)], ['align' => 'center', 'after' => 0]);
        }

        return $xml.'<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="24" w:space="1" w:color="000000"/></w:pBdr><w:spacing w:after="120"/></w:pPr></w:p>';
    }

    private static function title(array $doc): string
    {
        $xml = self::p([self::run($doc['title'], true, false, 26)], ['align' => 'center', 'before' => 120, 'after' => 40]);
        if ($doc['subtitle'] !== '') {
            $xml .= self::p([self::run($doc['subtitle'], false, true, 20)], ['align' => 'center', 'after' => 160]);
        }

        return $xml;
    }

    private static function identity(array $rows): string
    {
        if ($rows === []) {
            return '';
        }

        $labelW = 2500;
        $sepW = 250;
        $valueW = self::CONTENT_W - $labelW - $sepW;

        $trs = '';
        foreach ($rows as $row) {
            $trs .= self::tr(
                self::tc([self::p([self::run($row['label'])], ['after' => 20])], ['w' => $labelW])
                .self::tc([self::p([self::run(':')], ['after' => 20])], ['w' => $sepW])
                .self::tc([self::p([self::run($row['value'])], ['after' => 20])], ['w' => $valueW])
            );
        }

        return self::tbl([$labelW, $sepW, $valueW], $trs, false);
    }

    private static function petunjuk(array $petunjuk): string
    {
        if ($petunjuk['heading'] === '' && $petunjuk['lines'] === []) {
            return '';
        }

        $xml = self::heading($petunjuk['heading'] !== '' ? $petunjuk['heading'] : 'Petunjuk Pengerjaan');
        foreach ($petunjuk['lines'] as $i => $line) {
            $xml .= self::p([self::run(($i + 1).'. '.$line)], ['after' => 40, 'left' => 284, 'hanging' => 284]);
        }

        return $xml;
    }

    private static function sections(array $sections): string
    {
        $xml = '';
        foreach ($sections as $section) {
            $xml .= self::heading($section['heading']);

            foreach ($section['intro'] as $line) {
                $xml .= self::p([self::run($line, false, true)], ['after' => 60]);
            }

            foreach ($section['questions'] as $question) {
                $xml .= self::p(
                    [self::run($question['number'].'. '.$question['text'])],
                    ['before' => 60, 'after' => 40, 'left' => 284, 'hanging' => 284],
                );

                foreach ($question['images'] ?? [] as $image) {
                    $caption = trim((string) ($image['caption'] ?? ''));
                    $label = $caption !== ''
                        ? '[Gambar soal: '.$caption.'] — lihat pratinjau/PDF untuk gambar AI'
                        : '[Gambar soal] — lihat pratinjau/PDF untuk gambar AI';
                    $xml .= self::p(
                        [self::run($label, false, true, 18)],
                        ['after' => 40, 'left' => 284],
                    );
                }

                foreach ($question['options'] as $option) {
                    $xml .= self::p(
                        [self::run($option['label'].'. '.$option['text'])],
                        ['after' => 20, 'left' => 624, 'hanging' => 284],
                    );
                }

                if ($question['answer_space']) {
                    $xml .= self::p([self::run(str_repeat('_', 71))], ['after' => 60, 'left' => 284]);
                }
            }
        }

        return $xml;
    }

    private static function kunci(array $kunci): string
    {
        if ($kunci['heading'] === '' && $kunci['pg'] === [] && $kunci['lainnya'] === [] && $kunci['esai'] === []) {
            return '';
        }

        $xml = self::pageBreak()
            .self::heading($kunci['heading'] !== '' ? $kunci['heading'] : 'Kunci Jawaban & Pedoman Penilaian', 26);

        if ($kunci['subtitle'] !== '') {
            $xml .= self::p([self::run($kunci['subtitle'], false, true)], ['after' => 120]);
        }

        if ($kunci['pg'] !== []) {
            $xml .= self::subheading('Pilihan Ganda').self::pgTable($kunci['pg']);
        }

        foreach ($kunci['lainnya'] as $kunciLainnya) {
            $xml .= self::subheading($kunciLainnya['heading']);
            foreach ($kunciLainnya['lines'] as $line) {
                $xml .= self::p([self::run('• '.$line)], ['after' => 20, 'left' => 284, 'hanging' => 284]);
            }
        }

        if ($kunci['esai'] !== []) {
            $xml .= self::subheading('Esai - Poin Jawaban Ideal');
            foreach ($kunci['esai'] as $esai) {
                $xml .= self::p([self::run($esai['heading'], true)], ['before' => 80, 'after' => 40]);
                foreach ($esai['lines'] as $line) {
                    $xml .= self::p([self::run('• '.$line)], ['after' => 20, 'left' => 284, 'hanging' => 284]);
                }
            }
        }

        $rubrik = $kunci['rubrik'];
        if ($rubrik['heading'] !== '' || $rubrik['lines'] !== []) {
            $xml .= self::subheading($rubrik['heading'] !== '' ? $rubrik['heading'] : 'Rubrik Penilaian');
            foreach ($rubrik['lines'] as $line) {
                $xml .= self::p([self::run('• '.$line)], ['after' => 20, 'left' => 284, 'hanging' => 284]);
            }
        }

        return $xml;
    }

    /** Kunci pilihan ganda dua kolom: 1-5 di kiri, sisanya di kanan (seperti dokumen acuan). */
    private static function pgTable(array $answers): string
    {
        $half = (int) ceil(count($answers) / 2);
        $left = array_slice($answers, 0, $half);
        $right = array_slice($answers, $half);
        $colW = (int) floor(self::CONTENT_W / 2);

        $trs = '';
        for ($i = 0; $i < $half; $i++) {
            $cells = '';
            foreach ([$left[$i] ?? null, $right[$i] ?? null] as $item) {
                $text = $item !== null ? $item['number'].'. '.$item['answer'] : '';
                $cells .= self::tc([self::p([self::run($text, true)], ['after' => 20])], ['w' => $colW]);
            }
            $trs .= self::tr($cells);
        }

        return self::tbl([$colW, $colW], $trs);
    }

    private static function heading(string $text, int $sz = 22): string
    {
        return self::p([self::run($text, true, false, $sz)], ['before' => 240, 'after' => 120, 'rule' => true]);
    }

    private static function subheading(string $text): string
    {
        return self::p([self::run($text, true)], ['before' => 160, 'after' => 60]);
    }
}
