<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Ujian;
use App\Models\UjianSoal;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Penyusun file Word (.docx) untuk dokumen naskah soal ujian SIMS.
 * Format: Portrait A4, kop surat sekolah resmi, tabel identitas ujian,
 * petunjuk pengerjaan, daftar soal terformat rapi sesuai tipe (MCQ, Kompleks,
 * Benar/Salah, Mencocokkan, Esai), dan kunci jawaban di halaman terpisah.
 */
final class UjianDocxBuilder
{
    use DocxXml;

    private const CONTENT_W = 9026; // lebar area isi A4 portrait (dxa) dengan margin 1 inci

    public static function write(string $path, Ujian $ujian, bool $includeKunci = true): bool
    {
        return self::writeDocxPackage($path, self::documentXml($ujian, $includeKunci));
    }

    public static function documentXml(Ujian $ujian, bool $includeKunci = true): string
    {
        $soalList = $ujian->soal()->with('opsi')->orderBy('urutan')->get();
        $ujian->loadMissing(['pelajaran', 'kelas.kelas', 'author', 'paket']);

        $body = self::kop()
            . self::title($ujian)
            . self::identity($ujian, $soalList)
            . self::petunjuk($ujian)
            . self::soalSection($soalList);

        if ($includeKunci) {
            $body .= self::kunciSection($soalList, $ujian);
        }

        return self::documentXmlFor($body, ['orientation' => 'portrait']);
    }

    // ---------- Bagian-Bagian Dokumen ----------

    private static function kop(): string
    {
        $lines = SchoolLetterhead::lines();
        if ($lines === []) {
            $lines = [SchoolLetterhead::schoolName()];
        }

        $xml = '';
        foreach ($lines as $i => $line) {
            $isCaps = mb_strtoupper($line, 'UTF-8') === $line && !preg_match('/\d{5}|@|http/u', $line);
            $sz = match (true) {
                $i === 0 => 24, // 12pt bold
                $i === 1 && $isCaps => 22, // 11pt bold
                default => 18, // 9pt
            };
            $isBold = $i === 0 || $isCaps;
            $xml .= self::p([self::run($line, $isBold, false, $sz)], ['align' => 'center', 'after' => 20]);
        }

        // Garis ganda pembatas kop surat resmi
        return $xml . '<w:p><w:pPr><w:pBdr><w:bottom w:val="double" w:sz="18" w:space="4" w:color="000000"/></w:pBdr><w:spacing w:after="140"/></w:pPr></w:p>';
    }

    private static function title(Ujian $ujian): string
    {
        $judulDokumen = 'NASKAH SOAL UJIAN';
        $judulUjian = mb_strtoupper((string) $ujian->judul, 'UTF-8');

        $xml = self::p([self::run($judulDokumen, true, false, 24)], ['align' => 'center', 'before' => 80, 'after' => 20]);
        $xml .= self::p([self::run($judulUjian, true, false, 22)], ['align' => 'center', 'after' => 40]);

        if ($ujian->paket?->nama) {
            $sub = mb_strtoupper((string) $ujian->paket->nama, 'UTF-8');
            $xml .= self::p([self::run($sub, false, true, 18)], ['align' => 'center', 'after' => 120]);
        }

        return $xml;
    }

    private static function identity(Ujian $ujian, $soalList): string
    {
        $kelasList = $ujian->kelas->map(fn ($k) => $k->kelas?->nama)->filter()->unique()->join(', ');
        $guruPengampu = $ujian->kelas->map(fn ($k) => $k->guruPengampu?->nama)->filter()->unique()->join(', ')
            ?: ($ujian->author?->guru?->nama ?? $ujian->author?->name ?? '-');

        $jadwal = $ujian->jadwalUjian()->orderBy('tanggal')->first();
        $tanggalUjian = $jadwal ? Carbon::parse($jadwal->tanggal)->isoFormat('dddd, D MMMM Y') : '................................';

        $tipeCount = [];
        foreach ($soalList as $s) {
            $tipeCount[$s->typeLabel()] = ($tipeCount[$s->typeLabel()] ?? 0) + 1;
        }
        $bentukSoal = [];
        foreach ($tipeCount as $lbl => $c) {
            $bentukSoal[] = "{$lbl} ({$c})";
        }
        $bentukSoalStr = $bentukSoal !== [] ? implode(', ', $bentukSoal) : '-';

        $col1W = 1800;
        $col2W = 2713;
        $col3W = 1800;
        $col4W = 2713;

        $rows = [
            [
                'l1' => 'Mata Pelajaran', 'v1' => ': ' . ($ujian->pelajaran?->nama ?? '-'),
                'l2' => 'Hari / Tanggal', 'v2' => ': ' . $tanggalUjian,
            ],
            [
                'l1' => 'Tingkat / Kelas', 'v1' => ': ' . ($kelasList !== '' ? $kelasList : '-'),
                'l2' => 'Waktu / Durasi', 'v2' => ': ' . $ujian->durasi_menit . ' Menit',
            ],
            [
                'l1' => 'Guru Pengampu', 'v1' => ': ' . $guruPengampu,
                'l2' => 'Bentuk Soal', 'v2' => ': ' . $bentukSoalStr,
            ],
        ];

        $trs = '';
        foreach ($rows as $r) {
            $trs .= self::tr(
                self::tc([self::p([self::run($r['l1'], true, false, 18)], ['after' => 20])], ['w' => $col1W, 'noBorder' => true])
                . self::tc([self::p([self::run($r['v1'], false, false, 18)], ['after' => 20])], ['w' => $col2W, 'noBorder' => true])
                . self::tc([self::p([self::run($r['l2'], true, false, 18)], ['after' => 20])], ['w' => $col3W, 'noBorder' => true])
                . self::tc([self::p([self::run($r['v2'], false, false, 18)], ['after' => 20])], ['w' => $col4W, 'noBorder' => true]),
                ['cantSplit' => true]
            );
        }

        return self::tbl([$col1W, $col2W, $col3W, $col4W], $trs, false);
    }

    private static function petunjuk(Ujian $ujian): string
    {
        $xml = self::p([self::run('PETUNJUK PENGERJAAN :', true, false, 20)], ['before' => 120, 'after' => 40, 'rule' => true]);

        $lines = [
            'Berdoalah sebelum mulai mengerjakan soal.',
            'Tulislah nama dan identitas Anda pada lembar jawaban yang telah disediakan.',
            'Bacalah setiap butir soal dengan teliti dan cermat sebelum menjawab.',
            'Dahulukan menjawab soal-soal yang Anda anggap lebih mudah.',
            'Periksa kembali seluruh lembar jawaban Anda sebelum diserahkan kepada pengawas ujian.',
        ];

        if (!empty($ujian->instruksi)) {
            $lines[] = 'Petunjuk Khusus: ' . trim(strip_tags((string) $ujian->instruksi));
        }

        foreach ($lines as $i => $line) {
            $xml .= self::p([self::run(($i + 1) . '. ' . $line, false, false, 18)], ['after' => 20, 'left' => 284, 'hanging' => 284]);
        }

        return $xml;
    }

    private static function soalSection($soalList): string
    {
        $xml = self::p([self::run('DAFTAR SOAL', true, false, 20)], ['before' => 200, 'after' => 100, 'rule' => true]);

        if ($soalList->isEmpty()) {
            return $xml . self::p([self::run('(Belum ada butir soal dalam ujian ini)', false, true)], ['after' => 100]);
        }

        foreach ($soalList as $index => $soal) {
            $no = $index + 1;
            $teks = self::formatQuestionText($soal->teks_soal);
            $poinStr = " ({$soal->poin} Poin)";

            // Paragraf Teks Soal
            $xml .= self::p([
                self::run("{$no}. ", true, false, 20),
                self::runWithBreaks($teks, false, false, 20),
                self::run($poinStr, false, true, 16, '555555'),
            ], ['before' => 80, 'after' => 40, 'left' => 360, 'hanging' => 360]);

            // Render opsi / ruang jawab sesuai tipe
            switch ($soal->tipe) {
                case 'mcq':
                    foreach ($soal->opsi as $j => $opsi) {
                        $label = chr(65 + $j) . '. ';
                        $opText = self::formatQuestionText($opsi->teks_opsi);
                        $xml .= self::p([
                            self::run($label, true, false, 20),
                            self::runWithBreaks($opText, false, false, 20),
                        ], ['after' => 20, 'left' => 640, 'hanging' => 280]);
                    }
                    break;

                case 'mcq_complex':
                    $xml .= self::p([
                        self::run('*(Pilihlah lebih dari satu jawaban yang benar)*', false, true, 16, '666666'),
                    ], ['after' => 20, 'left' => 640]);

                    foreach ($soal->opsi as $j => $opsi) {
                        $label = '[  ] ' . chr(65 + $j) . '. ';
                        $opText = self::formatQuestionText($opsi->teks_opsi);
                        $xml .= self::p([
                            self::run($label, true, false, 20),
                            self::runWithBreaks($opText, false, false, 20),
                        ], ['after' => 20, 'left' => 640, 'hanging' => 280]);
                    }
                    break;

                case 'true_false':
                    $xml .= self::p([
                        self::run('*(Tentukan apakah pernyataan berikut Benar atau Salah)*', false, true, 16, '666666'),
                    ], ['after' => 20, 'left' => 640]);

                    $xml .= self::p([
                        self::run('[  ] Benar          [  ] Salah', true, false, 20),
                    ], ['after' => 40, 'left' => 640]);
                    break;

                case 'match':
                    $xml .= self::p([
                        self::run('*(Jodohkan kolom kiri dengan jawaban yang sesuai di kolom kanan)*', false, true, 16, '666666'),
                    ], ['after' => 30, 'left' => 640]);

                    $pairs = $soal->meta['pairs'] ?? [];
                    if (!empty($pairs)) {
                        $colW = (int) floor(self::CONTENT_W / 2);
                        $matchTrs = self::tr(
                            self::tc([self::p([self::run('Kolom Kiri (Pernyataan)', true, false, 18)], ['align' => 'center'])], ['w' => $colW, 'fill' => 'F1F5F9'])
                            . self::tc([self::p([self::run('Kolom Kanan (Pilihan Jawaban)', true, false, 18)], ['align' => 'center'])], ['w' => $colW, 'fill' => 'F1F5F9']),
                            ['header' => true]
                        );

                        foreach ($pairs as $pj => $pair) {
                            $leftTeks = self::formatQuestionText($pair['left'] ?? $pair['kiri'] ?? '');
                            $rightTeks = self::formatQuestionText($pair['right'] ?? $pair['kanan'] ?? '');

                            $matchTrs .= self::tr(
                                self::tc([self::p([
                                    self::run(($pj + 1) . '. ', true, false, 18),
                                    self::runWithBreaks($leftTeks, false, false, 18),
                                ], ['after' => 20])], ['w' => $colW])
                                . self::tc([self::p([
                                    self::run(chr(65 + $pj) . '. ', true, false, 18),
                                    self::runWithBreaks($rightTeks, false, false, 18),
                                ], ['after' => 20])], ['w' => $colW])
                            );
                        }

                        $xml .= self::tbl([$colW, $colW], $matchTrs, true);
                    }
                    break;

                case 'essay':
                    for ($k = 0; $k < 3; $k++) {
                        $xml .= self::p([
                            self::run(str_repeat('.', 92), false, false, 16, '999999'),
                        ], ['after' => 30, 'left' => 640]);
                    }
                    break;
            }

            // Spasi pemisah antar butir soal
            $xml .= self::p([], ['after' => 60]);
        }

        return $xml;
    }

    private static function kunciSection($soalList, Ujian $ujian): string
    {
        $xml = self::pageBreak()
            . self::p([self::run('KUNCI JAWABAN & PEDOMAN PENILAIAN', true, false, 24)], ['align' => 'center', 'before' => 100, 'after' => 20])
            . self::p([self::run(mb_strtoupper((string) $ujian->judul, 'UTF-8'), false, true, 20)], ['align' => 'center', 'after' => 160]);

        $pgList = [];
        $kompleksList = [];
        $tfList = [];
        $matchList = [];
        $esaiList = [];

        foreach ($soalList as $idx => $soal) {
            $no = $idx + 1;
            if ($soal->tipe === 'mcq') {
                $kunciHuruf = [];
                foreach ($soal->opsi as $j => $op) {
                    if ($op->is_benar) {
                        $kunciHuruf[] = chr(65 + $j);
                    }
                }
                $pgList[] = ['number' => $no, 'answer' => implode(', ', $kunciHuruf)];
            } elseif ($soal->tipe === 'mcq_complex') {
                $kunciHuruf = [];
                foreach ($soal->opsi as $j => $op) {
                    if ($op->is_benar) {
                        $kunciHuruf[] = chr(65 + $j) . ' (' . self::formatQuestionText($op->teks_opsi) . ')';
                    }
                }
                $kompleksList[] = ['number' => $no, 'answer' => implode('; ', $kunciHuruf)];
            } elseif ($soal->tipe === 'true_false') {
                $jawaban = [];
                foreach ($soal->opsi as $op) {
                    if ($op->is_benar) {
                        $jawaban[] = self::formatQuestionText($op->teks_opsi);
                    }
                }
                $tfList[] = ['number' => $no, 'answer' => implode(', ', $jawaban) ?: 'Benar'];
            } elseif ($soal->tipe === 'match') {
                $pairs = $soal->meta['pairs'] ?? [];
                $pairAnswers = [];
                foreach ($pairs as $pj => $pair) {
                    $pairAnswers[] = ($pj + 1) . ' -> ' . chr(65 + $pj);
                }
                $matchList[] = ['number' => $no, 'answer' => implode(', ', $pairAnswers)];
            } elseif ($soal->tipe === 'essay') {
                $penjelasan = trim((string) $soal->penjelasan);
                $esaiList[] = ['number' => $no, 'rubrik' => $penjelasan !== '' ? $penjelasan : 'Jawaban dievaluasi sesuai kelengkapan dan ketepatan konsep.'];
            }
        }

        // 1. Kunci Pilihan Ganda (Tabel 2 Kolom)
        if (!empty($pgList)) {
            $xml .= self::p([self::run('A. Pilihan Ganda', true, false, 20)], ['before' => 120, 'after' => 60]);

            $half = (int) ceil(count($pgList) / 2);
            $left = array_slice($pgList, 0, $half);
            $right = array_slice($pgList, $half);
            $colW = (int) floor(self::CONTENT_W / 2);

            $trs = '';
            for ($i = 0; $i < $half; $i++) {
                $cells = '';
                foreach ([$left[$i] ?? null, $right[$i] ?? null] as $item) {
                    $text = $item !== null ? $item['number'] . '. ' . $item['answer'] : '';
                    $cells .= self::tc([self::p([self::run($text, true, false, 18)], ['after' => 20])], ['w' => $colW]);
                }
                $trs .= self::tr($cells);
            }

            $xml .= self::tbl([$colW, $colW], $trs);
        }

        // 2. Kunci Pilihan Ganda Kompleks
        if (!empty($kompleksList)) {
            $xml .= self::p([self::run('B. Pilihan Ganda Kompleks', true, false, 20)], ['before' => 120, 'after' => 60]);
            foreach ($kompleksList as $item) {
                $xml .= self::p([
                    self::run($item['number'] . '. ', true, false, 18),
                    self::run($item['answer'], false, false, 18),
                ], ['after' => 30, 'left' => 284, 'hanging' => 284]);
            }
        }

        // 3. Kunci Benar / Salah
        if (!empty($tfList)) {
            $xml .= self::p([self::run('C. Benar / Salah', true, false, 20)], ['before' => 120, 'after' => 60]);
            foreach ($tfList as $item) {
                $xml .= self::p([
                    self::run($item['number'] . '. ', true, false, 18),
                    self::run($item['answer'], true, false, 18),
                ], ['after' => 30, 'left' => 284, 'hanging' => 284]);
            }
        }

        // 4. Kunci Mencocokkan
        if (!empty($matchList)) {
            $xml .= self::p([self::run('D. Mencocokkan', true, false, 20)], ['before' => 120, 'after' => 60]);
            foreach ($matchList as $item) {
                $xml .= self::p([
                    self::run($item['number'] . '. ', true, false, 18),
                    self::run($item['answer'], false, false, 18),
                ], ['after' => 30, 'left' => 284, 'hanging' => 284]);
            }
        }

        // 5. Pedoman Penilaian Esai
        if (!empty($esaiList)) {
            $xml .= self::p([self::run('E. Esai & Pedoman Penilaian', true, false, 20)], ['before' => 120, 'after' => 60]);
            foreach ($esaiList as $item) {
                $xml .= self::p([
                    self::run('Soal No. ' . $item['number'] . ' :', true, false, 18),
                ], ['before' => 40, 'after' => 20]);
                $xml .= self::p([
                    self::run('• ' . $item['rubrik'], false, false, 18),
                ], ['after' => 40, 'left' => 284, 'hanging' => 284]);
            }
        }

        return $xml;
    }

    // ---------- Helper Pembersihan Teks ----------

    public static function formatQuestionText(?string $html): string
    {
        if (empty($html)) {
            return '';
        }

        // Pertahankan representasi LaTeX rumus matematika jika ada
        $html = preg_replace('/<img[^>]+class=["\'][^"\']*math-svg[^"\']*["\'][^>]+data-latex=["\']([^"\']+)["\'][^>]*>/i', ' $1 ', $html);
        $html = preg_replace('/<img[^>]+data-latex=["\']([^"\']+)["\'][^>]+class=["\'][^"\']*math-svg[^"\']*["\'][^>]*>/i', ' $1 ', $html);

        // Beri placeholder teks jika ada tag gambar biasa
        $html = preg_replace_callback('/<img\b[^>]*>/i', function ($m) {
            if (preg_match('/alt=["\']([^"\']+)["\']/i', $m[0], $alt) && !empty($alt[1])) {
                return ' [Gambar: ' . trim($alt[1]) . '] ';
            }
            return ' [Gambar] ';
        }, $html);

        // Ganti elemen block dan pemisah baris dengan newline
        $html = preg_replace('#<(br|hr)\s*/?>#i', "\n", $html);
        $html = preg_replace('#</(p|div|tr|li|h[1-6])>#i', "\n", $html);

        // Bersihkan varian entitas non-breaking space
        $text = str_ireplace(['&nbsp;', '&nbsp', '&#160;', '&#xa0;', '&amp;nbsp;'], ' ', $html);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_ireplace(['&nbsp;', '&nbsp'], ' ', $text);

        // Bersihkan spasi dan newline berlebih
        $lines = explode("\n", $text);
        $cleanLines = [];
        foreach ($lines as $line) {
            $l = preg_replace('/[\x{00A0}\x{200B}\s]+/u', ' ', $line);
            $l = trim($l);
            if ($l !== '') {
                $cleanLines[] = $l;
            }
        }

        return implode("\n", $cleanLines);
    }

    protected static function runWithBreaks(string $text, bool $bold = false, bool $italic = false, int $sz = 20, ?string $color = null, bool $underline = false): string
    {
        $props = '<w:rFonts w:ascii="Arial" w:hAnsi="Arial" w:cs="Arial"/>'
            . ($bold ? '<w:b/>' : '')
            . ($italic ? '<w:i/>' : '')
            . ($underline ? '<w:u w:val="single"/>' : '')
            . ($color !== null ? '<w:color w:val="' . $color . '"/>' : '')
            . '<w:sz w:val="' . $sz . '"/><w:szCs w:val="' . $sz . '"/>';

        $escaped = htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $withBreaks = str_replace("\n", '</w:t><w:br/><w:t xml:space="preserve">', $escaped);

        return '<w:r><w:rPr>' . $props . '</w:rPr><w:t xml:space="preserve">' . $withBreaks . '</w:t></w:r>';
    }
}
