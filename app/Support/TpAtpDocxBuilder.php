<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Penyusun file Microsoft Word (.docx) untuk dokumen Tujuan Pembelajaran (TP)
 * dan Alur Tujuan Pembelajaran (ATP).
 * Menghasilkan tata letak A4 Landscape profesional dengan KOP surat resmi sekolah,
 * tabel TP ber-border rapat dengan penggabungan sel vertikal (vMerge),
 * representasi visual alur pembelajaran (flowchart) dengan warna tema per jenjang,
 * serta blok tanda tangan guru mata pelajaran di kanan bawah.
 */
final class TpAtpDocxBuilder
{
    use DocxXml;

    private const CONTENT_W = 14558; // A4 landscape dengan margin 12mm

    // Lebar kolom tabel TP (Total: 14558 dxa)
    private const COL_ELEMEN = 1650;
    private const COL_CP = 3450;
    private const COL_MATERI = 2300;
    private const COL_TP = 4308;
    private const COL_KODE = 1050;
    private const COL_JP = 950;
    private const COL_SEM = 850;

    public static function write(string $path, array $doc): bool
    {
        return self::writeDocxPackage($path, self::documentXml($doc));
    }

    public static function documentXml(array $doc): string
    {
        $body = '';

        // Dokumen TP
        if (! empty($doc['has_tp']) || ! empty($doc['tp_table']['rows'])) {
            $body .= self::kop($doc['kop'])
                .self::title($doc['title_tp'] ?: 'TUJUAN PEMBELAJARAN (TP)')
                .self::identity($doc['identity'])
                .self::tpTable($doc['tp_table'])
                .self::signature($doc['signature']);
        }

        // Dokumen ATP
        if (! empty($doc['has_atp']) || ! empty($doc['atp_diagram']['nodes'])) {
            if ($body !== '') {
                // Pindah halaman jika ada bagian TP sebelumnya
                $body .= '<w:p><w:r><w:br w:type="page"/></w:r></w:p>';
            }

            $body .= self::kop($doc['kop'])
                .self::title($doc['title_atp'] ?: 'ALUR TUJUAN PEMBELAJARAN (ATP)')
                .self::identity($doc['identity'])
                .self::atpFlowchart($doc['atp_diagram'])
                .self::atpSummaryTable($doc['atp_diagram']['summary_rows'] ?? [])
                .self::signature($doc['signature']);
        }

        if ($body === '') {
            $body = self::p([self::run('Dokumen TP dan ATP belum memiliki konten.', false, false, 22)]);
        }

        return self::documentXmlFor($body, [
            'orientation' => 'landscape',
            'margin' => ['top' => 720, 'right' => 680, 'bottom' => 720, 'left' => 680],
        ]);
    }

    // ---------- KOP & Identitas ----------

    private static function kop(array $lines): string
    {
        if ($lines === []) {
            $lines = SchoolLetterhead::lines();
        }

        $xml = '';
        foreach ($lines as $i => $line) {
            $isCaps = mb_strtoupper($line, 'UTF-8') === $line && ! preg_match('/\d{5}|@|http/u', $line);
            $sz = match (true) {
                $i === 0 && $isCaps => 26,
                $i === 1 && $isCaps => 24,
                $isCaps => 20,
                default => 16,
            };
            $xml .= self::p([self::run($line, $isCaps || $i < 3, false, $sz)], ['align' => 'center', 'after' => 0]);
        }

        // Garis tebal di bawah kop
        return $xml.'<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="24" w:space="1" w:color="000000"/></w:pBdr><w:spacing w:after="140"/></w:pPr></w:p>';
    }

    private static function title(string $title): string
    {
        return self::p([self::run($title, true, false, 26, '000000')], [
            'align' => 'center',
            'before' => 60,
            'after' => 120,
        ]);
    }

    private static function identity(array $id): string
    {
        $mapel = (string) ($id['mapel'] ?? '-');
        $fase = (string) ($id['fase_kelas'] ?? 'D/VII-IX');
        $tahun = (string) ($id['tahun'] ?? '-');

        $rows = self::tr(
            self::tc([self::p([self::run('MATA PELAJARAN', true, false, 18)], ['after' => 0])], ['w' => 2200, 'borders' => false])
            .self::tc([self::p([self::run(': '.$mapel, false, false, 18)], ['after' => 0])], ['w' => 12358, 'borders' => false])
        );

        $rows .= self::tr(
            self::tc([self::p([self::run('FASE / KELAS', true, false, 18)], ['after' => 0])], ['w' => 2200, 'borders' => false])
            .self::tc([self::p([self::run(': '.$fase, false, false, 18)], ['after' => 0])], ['w' => 12358, 'borders' => false])
        );

        $rows .= self::tr(
            self::tc([self::p([self::run('TAHUN PELAJARAN', true, false, 18)], ['after' => 0])], ['w' => 2200, 'borders' => false])
            .self::tc([self::p([self::run(': '.$tahun, false, false, 18)], ['after' => 0])], ['w' => 12358, 'borders' => false])
        );

        return self::tbl([2200, 12358], $rows, false)
            .'<w:p><w:pPr><w:spacing w:after="120"/></w:pPr></w:p>';
    }

    // ---------- Tabel TP Formal ----------

    private static function tpTable(array $tpTable): string
    {
        $cols = [
            self::COL_ELEMEN,
            self::COL_CP,
            self::COL_MATERI,
            self::COL_TP,
            self::COL_KODE,
            self::COL_JP,
            self::COL_SEM,
        ];

        // Header baris tabel
        $head = self::tr(
            self::tc([self::p([self::run('ELEMEN', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_ELEMEN, 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('CAPAIAN PEMBELAJARAN', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_CP, 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('LINGKUP MATERI', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_MATERI, 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('TUJUAN PEMBELAJARAN (TP)', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_TP, 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('KODE TP', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_KODE, 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run("ALOKASI\nWAKTU (JP)", true, false, 15)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_JP, 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('SEMESTER', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_SEM, 'fill' => 'F2F2F2'])
        );

        $bodyRows = '';
        $elements = $tpTable['elements'] ?? [];

        if (empty($elements) && ! empty($tpTable['rows'])) {
            // Fallback baris datar jika grup belum terbentuk
            foreach ($tpTable['rows'] as $r) {
                $bodyRows .= self::tr(
                    self::tc([self::p([self::run((string) $r['elemen'], true, false, 16)], ['after' => 0])], ['w' => self::COL_ELEMEN])
                    .self::tc([self::p([self::run((string) $r['cp'], false, false, 15)], ['after' => 0])], ['w' => self::COL_CP])
                    .self::tc([self::p([self::run((string) $r['lingkup_materi'], true, false, 16)], ['after' => 0])], ['w' => self::COL_MATERI])
                    .self::tc([self::p([self::run((string) $r['tp'], false, false, 15)], ['after' => 0])], ['w' => self::COL_TP])
                    .self::tc([self::p([self::run((string) $r['kode_tp'], true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_KODE])
                    .self::tc([self::p([self::run((string) $r['jp'], false, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_JP])
                    .self::tc([self::p([self::run((string) $r['semester'], false, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_SEM])
                );
            }
        } else {
            foreach ($elements as $el) {
                $elFirst = true;

                foreach ($el['materi_groups'] as $mat) {
                    $matFirst = true;

                    foreach ($mat['tps'] as $tpItem) {
                        $cells = '';

                        // Kolom Elemen (vMerge per elemen)
                        $cells .= self::tc(
                            $elFirst ? [self::p([self::run((string) $el['nama'], true, false, 16)], ['after' => 0])] : [],
                            ['w' => self::COL_ELEMEN, 'vmerge' => $elFirst ? 'restart' : 'continue']
                        );

                        // Kolom CP (vMerge per elemen)
                        $cells .= self::tc(
                            $elFirst ? [self::p([self::run((string) $el['cp'], false, false, 15)], ['after' => 0])] : [],
                            ['w' => self::COL_CP, 'vmerge' => $elFirst ? 'restart' : 'continue']
                        );

                        // Kolom Lingkup Materi (vMerge)
                        $cells .= self::tc(
                            $matFirst ? [self::p([self::run((string) $mat['nama'], true, false, 16)], ['after' => 0])] : [],
                            ['w' => self::COL_MATERI, 'vmerge' => $matFirst ? 'restart' : 'continue']
                        );

                        // Kolom Tujuan Pembelajaran (per baris)
                        $cells .= self::tc([self::p([self::run((string) $tpItem['tp'], false, false, 15)], ['after' => 0])], ['w' => self::COL_TP]);

                        // Kolom Kode TP (per baris)
                        $cells .= self::tc([self::p([self::run((string) $tpItem['kode'], true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => self::COL_KODE]);

                        // Kolom JP (vMerge)
                        $cells .= self::tc(
                            $matFirst ? [self::p([self::run((string) $mat['jp'], false, false, 16)], ['align' => 'center', 'after' => 0])] : [],
                            ['w' => self::COL_JP, 'vmerge' => $matFirst ? 'restart' : 'continue']
                        );

                        // Kolom Semester (vMerge)
                        $cells .= self::tc(
                            $matFirst ? [self::p([self::run((string) $mat['semester'], false, false, 16)], ['align' => 'center', 'after' => 0])] : [],
                            ['w' => self::COL_SEM, 'vmerge' => $matFirst ? 'restart' : 'continue']
                        );

                        $bodyRows .= self::tr($cells);
                        $elFirst = false;
                        $matFirst = false;
                    }
                }
            }
        }

        return self::tbl($cols, $head.$bodyRows, true)
            .'<w:p><w:pPr><w:spacing w:after="160"/></w:pPr></w:p>';
    }

    // ---------- Diagram Alur ATP (Visual Flowchart) ----------

    private static function atpFlowchart(array $atpDiagram): string
    {
        $nodes = $atpDiagram['nodes'] ?? [];
        if (empty($nodes)) {
            return '';
        }

        $byGrade = $atpDiagram['by_grade'] ?? [];
        if (empty($byGrade[7]) && empty($byGrade[8]) && empty($byGrade[9])) {
            foreach ($nodes as $n) {
                $g = (int) ($n['kelas'] ?? 7);
                $g = ($g >= 7 && $g <= 9) ? $g : 7;
                $byGrade[$g][] = $n;
            }
        }

        // Susun baris-baris diagram alur:
        // - Jika satu jenjang memiliki <= 6 node (seperti standar Fase D pada Gambar 1), tetap dalam 1 baris utuh.
        // - Jika satu jenjang memiliki > 6 node (misal guru menyusun khusus Kelas 7 saja dengan banyak TP),
        //   maka otomatis dipecah menjadi 2 baris (Semester Ganjil dan Semester Genap).
        $flowRows = [];

        foreach ([7, 8, 9] as $grade) {
            $gradeNodes = $byGrade[$grade] ?? [];
            if (empty($gradeNodes)) {
                continue;
            }

            $count = count($gradeNodes);

            if ($count <= 6) {
                // Utuh 1 baris per jenjang (identik dengan Gambar 1)
                $flowRows[] = [
                    'grade' => $grade,
                    'is_subrow' => false,
                    'nodes' => $gradeNodes,
                ];
            } else {
                // Dipecah per semester (Ganjil & Genap) agar tidak berjejal
                $ganjil = [];
                $genap = [];
                $curSem = 'Ganjil';

                foreach ($gradeNodes as $n) {
                    $sem = (string) ($n['semester'] ?? '');
                    $judul = (string) ($n['judul'] ?? '');
                    if (stripos($sem, 'Genap') !== false || stripos($judul, 'Semester Genap') !== false) {
                        $curSem = 'Genap';
                    }

                    if ($curSem === 'Genap') {
                        $genap[] = $n;
                    } else {
                        $ganjil[] = $n;
                    }
                }

                $groups = [];
                if (! empty($ganjil)) $groups[] = $ganjil;
                if (! empty($genap)) $groups[] = $genap;
                if (empty($groups)) $groups[] = [$gradeNodes];

                foreach ($groups as $grpIdx => $grp) {
                    $chunks = self::chunkNodesBalanced($grp, 6);
                    foreach ($chunks as $cIdx => $chunk) {
                        $flowRows[] = [
                            'grade' => $grade,
                            'is_subrow' => ($grpIdx > 0 || $cIdx > 0),
                            'nodes' => $chunk,
                        ];
                    }
                }
            }
        }

        if (empty($flowRows)) {
            return '';
        }

        $xml = '';
        $rowCount = count($flowRows);

        foreach ($flowRows as $rIdx => $rowItem) {
            $grade = $rowItem['grade'];
            $rowNodes = $rowItem['nodes'];
            $count = count($rowNodes);

            $widths = self::computeFlowWidths($count, self::CONTENT_W, 320);

            $cellsXml = '';
            foreach ($rowNodes as $idx => $node) {
                if ($idx > 0) {
                    $cellsXml .= self::flowArrowTc(320);
                }

                $type = $node['type'] ?? 'tp';
                $title = trim((string) ($node['judul'] ?? ''));
                $materi = trim((string) ($node['materi'] ?? ''));
                $tpDesc = trim((string) ($node['tp_desc'] ?? ''));
                $code = trim((string) ($node['kode'] ?? ''));

                // Bersihkan nilai tanda hubung '-' agar tidak ada kotak kosong
                if ($title === '-' || $title === '–') $title = '';
                if ($materi === '-' || $materi === '–') $materi = '';
                if ($tpDesc === '-' || $tpDesc === '–') $tpDesc = '';
                if ($code === '-' || $code === '–') $code = '';

                // Fallback tegas agar node start/transisi/akhir selalu memiliki judul yang valid
                if ($type === 'start' && $title === '') {
                    $semName = ! empty($node['semester']) ? $node['semester'] : 'Ganjil';
                    $title = "Awal fase D Kelas {$grade} Semester {$semName}";
                } elseif ($type === 'transition' && $title === '') {
                    $nextG = $grade < 9 ? $grade + 1 : 9;
                    $title = "Capaian Pembelajaran Akhir Fase D Kelas {$grade} menuju pembelajaran fase D kelas {$nextG} Semester Ganjil";
                } elseif ($type === 'end' && $title === '') {
                    $title = "Capaian Pembelajaran Akhir Fase D";
                }

                $nodeStyle = self::resolveNodeStyle($type, $grade);
                $boxW = $widths['box_widths'][$idx] ?? 2000;

                $paragraphs = [];

                if ($type === 'start') {
                    // Kotak awal semester
                    $paragraphs[] = '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/></w:pPr>'
                        .self::run($title, true, false, 14, $nodeStyle['text_color'])
                        .'</w:p>';
                } elseif ($type === 'transition' || $type === 'end') {
                    // Kotak transisi / capaian akhir fase
                    $paragraphs[] = '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/></w:pPr>'
                        .self::run($title, true, false, 13, $nodeStyle['text_color'])
                        .'</w:p>';
                } else {
                    // Kotak Tujuan Pembelajaran: Jabarkan Lingkup Materi, Uraian TP, dan Kode TP
                    $displayMateri = $materi !== '' ? $materi : $title;
                    if ($displayMateri === '' || $displayMateri === '-') {
                        $displayMateri = $tpDesc !== '' ? $tpDesc : ($code !== '' ? "Tujuan Pembelajaran {$code}" : 'Tujuan Pembelajaran');
                    }
                    $displayTp = ($tpDesc !== '' && $tpDesc !== '-' && $tpDesc !== $displayMateri) ? $tpDesc : '';

                    // 1. Lingkup Materi Pokok (dijabarkan jelas)
                    $paragraphs[] = '<w:p><w:pPr><w:spacing w:before="0" w:after="'.($displayTp !== '' || $code !== '' ? '20' : '0').'"/><w:jc w:val="center"/></w:pPr>'
                        .self::run('Materi: '.$displayMateri, true, false, 13, $nodeStyle['text_color'])
                        .'</w:p>';

                    // 2. Uraian Kompetensi Tujuan Pembelajaran
                    if ($displayTp !== '') {
                        $shortTp = Str::limit($displayTp, 85, '...');
                        $paragraphs[] = '<w:p><w:pPr><w:spacing w:before="0" w:after="'.($code !== '' ? '20' : '0').'"/><w:jc w:val="center"/></w:pPr>'
                            .self::run($shortTp, false, false, 12, '334155')
                            .'</w:p>';
                    }

                    // 3. Kode TP
                    if ($code !== '') {
                        $paragraphs[] = '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/></w:pPr>'
                            .self::run('('.$code.')', true, false, 13, $nodeStyle['code_color'])
                            .'</w:p>';
                    }
                }

                $cellsXml .= self::flowBoxTc($paragraphs, $boxW, $nodeStyle);
            }

            $xml .= self::flowTable($widths['total_grid'], $cellsXml, 680);

            // Sambungkan antar-baris dengan panah arah ke bawah HANYA jika baris berikutnya adalah kelanjutan sub-baris dari jenjang yang sama
            if ($rIdx < $rowCount - 1) {
                $nextRow = $flowRows[$rIdx + 1];
                if ($nextRow['grade'] === $grade && $nextRow['is_subrow']) {
                    $xml .= self::flowDownArrow();
                } else {
                    $xml .= '<w:p><w:pPr><w:spacing w:before="40" w:after="80"/></w:pPr></w:p>';
                }
            }
        }

        return $xml.'<w:p><w:pPr><w:spacing w:before="60" w:after="120"/></w:pPr></w:p>';
    }

    /**
     * Bagi sekumpulan node menjadi beberapa baris yang seimbang (maksimal $maxPerRow per baris).
     *
     * @param  array[]  $nodes
     * @return array<int, array[]>
     */
    private static function chunkNodesBalanced(array $nodes, int $maxPerRow = 4): array
    {
        $total = count($nodes);
        if ($total <= 0) {
            return [];
        }

        if ($total <= $maxPerRow) {
            return [$nodes];
        }

        $rowCount = (int) ceil($total / $maxPerRow);
        $baseSize = intdiv($total, $rowCount);
        $extra = $total % $rowCount;

        $rows = [];
        $offset = 0;
        for ($i = 0; $i < $rowCount; $i++) {
            $size = $baseSize + ($i < $extra ? 1 : 0);
            $rows[] = array_slice($nodes, $offset, $size);
            $offset += $size;
        }

        return $rows;
    }

    /**
     * Hitung alokasi lebar kolom tabel flowchart agar pas dengan total lebar halaman.
     * Kotak dengan 1 atau 2 node dibatasi lebarnya agar tidak memanjang berlebihan.
     *
     * @return array{box_widths: int[], arrow_w: int, total_grid: int[]}
     */
    private static function computeFlowWidths(int $nodeCount, int $totalWidth = self::CONTENT_W, int $arrowWidth = 320): array
    {
        if ($nodeCount <= 0) {
            return ['box_widths' => [], 'arrow_w' => $arrowWidth, 'total_grid' => []];
        }

        // Jika hanya 1 node (misal Capaian Akhir Fase), batasi lebarnya ~12cm agar rapi di tengah
        if ($nodeCount === 1) {
            $singleW = 6800;
            return [
                'box_widths' => [$singleW],
                'arrow_w' => $arrowWidth,
                'total_grid' => [$singleW],
            ];
        }

        // Jika 2 node, batasi lebar kotak maksimal 4200 dxa (~7.4 cm) agar tidak terlalu lebar
        if ($nodeCount === 2) {
            $boxW = 4200;
            return [
                'box_widths' => [$boxW, $boxW],
                'arrow_w' => $arrowWidth,
                'total_grid' => [$boxW, $arrowWidth, $boxW],
            ];
        }

        $arrowCount = $nodeCount - 1;
        $totalArrowW = $arrowCount * $arrowWidth;
        $remW = max($nodeCount * 800, $totalWidth - $totalArrowW);

        $baseBoxW = intdiv($remW, $nodeCount);
        $extra = $remW - ($baseBoxW * $nodeCount);

        $boxWidths = [];
        $totalGrid = [];

        for ($i = 0; $i < $nodeCount; $i++) {
            $w = $baseBoxW + ($i < $extra ? 1 : 0);
            $boxWidths[] = $w;
            $totalGrid[] = $w;
            if ($i < $arrowCount) {
                $totalGrid[] = $arrowWidth;
            }
        }

        return [
            'box_widths' => $boxWidths,
            'arrow_w' => $arrowWidth,
            'total_grid' => $totalGrid,
        ];
    }

    /**
     * Tentukan tema warna kotak flowchart sesuai jenis node dan jenjang kelas.
     *
     * @return array{fill: string, border_color: string, border_sz: int, text_color: string, code_color: string}
     */
    private static function resolveNodeStyle(string $type, int $grade): array
    {
        return match (true) {
            $type === 'start' => [
                'fill' => 'EDE9FE',
                'border_color' => '8B5CF6',
                'border_sz' => 12,
                'text_color' => '3730A3',
                'code_color' => '3730A3',
            ],
            $type === 'transition' => [
                'fill' => 'FED7AA',
                'border_color' => 'F97316',
                'border_sz' => 12,
                'text_color' => '9A3412',
                'code_color' => '9A3412',
            ],
            $type === 'end' => [
                'fill' => 'BBF7D0',
                'border_color' => '16A34A',
                'border_sz' => 16,
                'text_color' => '052E16',
                'code_color' => '052E16',
            ],
            $grade === 7 => [
                'fill' => 'FEE2E2',
                'border_color' => 'F87171',
                'border_sz' => 12,
                'text_color' => '881337',
                'code_color' => '991B1B',
            ],
            $grade === 8 => [
                'fill' => 'FFEDD5',
                'border_color' => 'FB923C',
                'border_sz' => 12,
                'text_color' => '7C2D12',
                'code_color' => '9A3412',
            ],
            default => [
                'fill' => 'DCFCE7',
                'border_color' => '4ADE80',
                'border_sz' => 12,
                'text_color' => '14532D',
                'code_color' => '166534',
            ],
        };
    }

    /**
     * Buat sel kotak (node) berwarna dengan border rapi, padding dalam, dan vertical centering.
     *
     * @param  string[]  $paragraphs
     * @param  array{fill: string, border_color: string, border_sz: int}  $style
     */
    private static function flowBoxTc(array $paragraphs, int $w, array $style): string
    {
        $borderSz = (int) ($style['border_sz'] ?? 12);
        $borderColor = (string) ($style['border_color'] ?? '000000');
        $fill = (string) ($style['fill'] ?? 'FFFFFF');

        $tcPr = '<w:tcW w:w="'.$w.'" w:type="dxa"/>'
            .'<w:tcBorders>'
            .'<w:top w:val="single" w:sz="'.$borderSz.'" w:space="0" w:color="'.$borderColor.'"/>'
            .'<w:left w:val="single" w:sz="'.$borderSz.'" w:space="0" w:color="'.$borderColor.'"/>'
            .'<w:bottom w:val="single" w:sz="'.$borderSz.'" w:space="0" w:color="'.$borderColor.'"/>'
            .'<w:right w:val="single" w:sz="'.$borderSz.'" w:space="0" w:color="'.$borderColor.'"/>'
            .'</w:tcBorders>'
            .'<w:shd w:val="clear" w:color="auto" w:fill="'.$fill.'"/>'
            .'<w:tcMar>'
            .'<w:top w:w="90" w:type="dxa"/>'
            .'<w:left w:w="90" w:type="dxa"/>'
            .'<w:bottom w:w="90" w:type="dxa"/>'
            .'<w:right w:w="90" w:type="dxa"/>'
            .'</w:tcMar>'
            .'<w:vAlign w:val="center"/>';

        if (empty($paragraphs)) {
            $paragraphs = ['<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/></w:pPr></w:p>'];
        }

        return '<w:tc><w:tcPr>'.$tcPr.'</w:tcPr>'.implode('', $paragraphs).'</w:tc>';
    }

    /**
     * Buat sel panah ➔ transparan tanpa border di antara kotak flowchart horizontal.
     */
    private static function flowArrowTc(int $w = 320): string
    {
        $tcPr = '<w:tcW w:w="'.$w.'" w:type="dxa"/>'
            .'<w:tcBorders>'
            .'<w:top w:val="none"/>'
            .'<w:left w:val="none"/>'
            .'<w:bottom w:val="none"/>'
            .'<w:right w:val="none"/>'
            .'</w:tcBorders>'
            .'<w:tcMar>'
            .'<w:top w:w="0" w:type="dxa"/>'
            .'<w:left w:w="0" w:type="dxa"/>'
            .'<w:bottom w:w="0" w:type="dxa"/>'
            .'<w:right w:w="0" w:type="dxa"/>'
            .'</w:tcMar>'
            .'<w:vAlign w:val="center"/>';

        $p = '<w:p><w:pPr><w:spacing w:before="0" w:after="0"/><w:jc w:val="center"/></w:pPr>'
            .self::flowArrowRun()
            .'</w:p>';

        return '<w:tc><w:tcPr>'.$tcPr.'</w:tcPr>'.$p.'</w:tc>';
    }

    /**
     * Karakter panah horizontal ➔ dengan font simbol tebal.
     */
    private static function flowArrowRun(): string
    {
        return '<w:r><w:rPr><w:rFonts w:ascii="Segoe UI Symbol" w:hAnsi="Segoe UI Symbol" w:cs="Arial"/><w:b/><w:color w:val="64748B"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr><w:t>➔</w:t></w:r>';
    }

    /**
     * Panah vertikal penghubung antar-baris alur (↓).
     */
    private static function flowDownArrow(): string
    {
        return '<w:p><w:pPr><w:spacing w:before="30" w:after="30"/><w:jc w:val="center"/></w:pPr>'
            .'<w:r><w:rPr><w:rFonts w:ascii="Segoe UI Symbol" w:hAnsi="Segoe UI Symbol" w:cs="Arial"/><w:b/><w:color w:val="64748B"/><w:sz w:val="24"/><w:szCs w:val="24"/></w:rPr><w:t>↓</w:t></w:r>'
            .'</w:p>';
    }

    /**
     * Bungkus satu baris kelas diagram flowchart menjadi tabel Word tanpa border luar.
     *
     * @param  int[]  $gridCols
     */
    private static function flowTable(array $gridCols, string $cellsXml, int $minHeight = 680): string
    {
        $totalW = array_sum($gridCols);

        $grid = '';
        foreach ($gridCols as $w) {
            $grid .= '<w:gridCol w:w="'.$w.'"/>';
        }

        $tr = '<w:tr><w:trPr><w:cantSplit/><w:trHeight w:val="'.$minHeight.'" w:hRule="atLeast"/></w:trPr>'.$cellsXml.'</w:tr>';

        return '<w:tbl>'
            .'<w:tblPr>'
            .'<w:tblW w:w="'.$totalW.'" w:type="dxa"/>'
            .'<w:jc w:val="center"/>'
            .'<w:tblBorders>'
            .'<w:top w:val="none"/>'
            .'<w:left w:val="none"/>'
            .'<w:bottom w:val="none"/>'
            .'<w:right w:val="none"/>'
            .'<w:insideH w:val="none"/>'
            .'<w:insideV w:val="none"/>'
            .'</w:tblBorders>'
            .'<w:tblLayout w:type="fixed"/>'
            .'<w:tblCellMar>'
            .'<w:top w:w="0" w:type="dxa"/>'
            .'<w:left w:w="0" w:type="dxa"/>'
            .'<w:bottom w:w="0" w:type="dxa"/>'
            .'<w:right w:w="0" w:type="dxa"/>'
            .'</w:tblCellMar>'
            .'</w:tblPr>'
            .'<w:tblGrid>'.$grid.'</w:tblGrid>'
            .$tr
            .'</w:tbl>';
    }

    // ---------- Tabel Ringkasan Urutan Alur ATP ----------

    private static function atpSummaryTable(array $summaryRows): string
    {
        if (empty($summaryRows)) {
            return '';
        }

        $cols = [600, 800, 1000, 3000, 1200, 6958, 1000];

        $head = self::tr(
            self::tc([self::p([self::run('NO', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => $cols[0], 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('KELAS', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => $cols[1], 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('SEMESTER', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => $cols[2], 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('LINGKUP MATERI', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => $cols[3], 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('KODE TP', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => $cols[4], 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('TUJUAN PEMBELAJARAN (TP)', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => $cols[5], 'fill' => 'F2F2F2'])
            .self::tc([self::p([self::run('JP', true, false, 16)], ['align' => 'center', 'after' => 0])], ['w' => $cols[6], 'fill' => 'F2F2F2'])
        );

        $bodyRows = '';
        foreach ($summaryRows as $r) {
            $bodyRows .= self::tr(
                self::tc([self::p([self::run((string) $r['no'], false, false, 15)], ['align' => 'center', 'after' => 0])], ['w' => $cols[0]])
                .self::tc([self::p([self::run((string) $r['kelas'], false, false, 15)], ['align' => 'center', 'after' => 0])], ['w' => $cols[1]])
                .self::tc([self::p([self::run((string) $r['semester'], false, false, 15)], ['align' => 'center', 'after' => 0])], ['w' => $cols[2]])
                .self::tc([self::p([self::run((string) $r['lingkup_materi'], true, false, 15)], ['after' => 0])], ['w' => $cols[3]])
                .self::tc([self::p([self::run((string) $r['kode_tp'], true, false, 15)], ['align' => 'center', 'after' => 0])], ['w' => $cols[4]])
                .self::tc([self::p([self::run((string) $r['tp'], false, false, 15)], ['after' => 0])], ['w' => $cols[5]])
                .self::tc([self::p([self::run((string) $r['jp'], false, false, 15)], ['align' => 'center', 'after' => 0])], ['w' => $cols[6]])
            );
        }

        return self::p([self::run('Ringkasan Alur Tujuan Pembelajaran', true, false, 20, '000000')], ['before' => 100, 'after' => 80])
            .self::tbl($cols, $head.$bodyRows, true)
            .'<w:p><w:pPr><w:spacing w:after="160"/></w:pPr></w:p>';
    }

    // ---------- Tanda Tangan ----------

    private static function signature(array $sig): string
    {
        $user = auth()->user();
        $defaultGuruNama = $user?->guru?->nama ?: ($user?->name ?: 'Guru Mata Pelajaran');
        $defaultGuruNik = $user?->guru?->nik ?: ($user?->guru?->nip ?: ($user?->username ?: ''));

        $placeDate = (string) ($sig['place_date'] ?? 'Tanjungpinang, 17 Juli 2026');
        $role = (string) ($sig['role'] ?? 'Guru Mata Pelajaran');

        $name = (string) ($sig['name'] ?? '');
        if ($name === '' || str_contains($name, '[Nama') || str_contains($name, '......')) {
            $name = $defaultGuruNama;
        }

        $nip = (string) ($sig['nip'] ?? '');
        if ($nip === '' || str_contains($nip, '......')) {
            $nip = $defaultGuruNik;
        }

        $nameLine = $name;
        $nipLine = $nip !== '' ? "NIK. {$nip}" : 'NIK. -';

        $wKiri = 9558;
        $wKanan = 5000;

        $rows = self::tr(
            self::tc([self::p([self::run('', false, false, 16)], ['after' => 0])], ['w' => $wKiri, 'borders' => false])
            .self::tc([self::p([self::run($placeDate, false, false, 18)], ['align' => 'center', 'after' => 0])], ['w' => $wKanan, 'borders' => false])
        );

        $rows .= self::tr(
            self::tc([self::p([self::run('', false, false, 16)], ['after' => 0])], ['w' => $wKiri, 'borders' => false])
            .self::tc([self::p([self::run($role, false, false, 18)], ['align' => 'center', 'after' => 600])], ['w' => $wKanan, 'borders' => false])
        );

        $rows .= self::tr(
            self::tc([self::p([self::run('', false, false, 16)], ['after' => 0])], ['w' => $wKiri, 'borders' => false])
            .self::tc([
                self::p([self::run($nameLine, true, false, 18)], ['align' => 'center', 'after' => 0]),
                self::p([self::run($nipLine, false, false, 16)], ['align' => 'center', 'after' => 0]),
            ], ['w' => $wKanan, 'borders' => false])
        );

        return self::tbl([$wKiri, $wKanan], $rows, false);
    }
}
