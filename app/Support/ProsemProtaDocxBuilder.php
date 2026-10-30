<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Penyusun dokumen Word (.docx) untuk Program Tahunan (PROTA) dan Program Semester (PROSEM)
 * Kurikulum Merdeka SMP dengan tata letak landscape profesional, kop surat resmi SIMS,
 * border tabel presisi, cell shading, dan penataan vertikal teks.
 */
final class ProsemProtaDocxBuilder
{
    use DocxXml;

    private const CONTENT_W = 14558; // A4 landscape margin 12mm

    // Lebar kolom tabel PROTA (Total: 14558 dxa)
    private const PROTA_COL_ELEMEN = 1800;
    private const PROTA_COL_CP = 3800;
    private const PROTA_COL_TP = 5158;
    private const PROTA_COL_KODE = 1100;
    private const PROTA_COL_JP = 1300;
    private const PROTA_COL_KET = 1400;

    // Lebar kolom tabel PROSEM (Total: 14558 dxa)
    private const PROSEM_COL_MATERI = 4198;
    private const PROSEM_COL_JP = 1000;
    private const PROSEM_WEEK_COL_W = 360; // 26 minggu x 360 = 9360 dxa (Total: 4198 + 1000 + 9360 = 14558 dxa)

    /**
     * Tulis file .docx untuk PROTA atau PROSEM atau Keduanya.
     *
     * @param string $path Target file path
     * @param array $built Hasil dari ProsemProtaDocument::buildFromTpAtp()
     * @param string $type 'prota', 'prosem_ganjil', 'prosem_genap', atau 'both'
     */
    public static function write(string $path, array $built, string $type = 'both'): bool
    {
        $body = '';

        if ($type === 'both' || $type === 'prota') {
            $body .= self::buildProtaBody($built);
        }

        if ($type === 'both' || str_starts_with($type, 'prosem')) {
            if ($body !== '') {
                $body .= self::pageBreak();
            }

            if ($type === 'both' || $type === 'prosem' || $type === 'prosem_ganjil') {
                $body .= self::buildProsemSemesterBody($built, 'ganjil');
            }

            if ($type === 'both' || $type === 'prosem' || $type === 'prosem_genap') {
                if ($type === 'both' || $type === 'prosem') {
                    $body .= self::pageBreak();
                }
                $body .= self::buildProsemSemesterBody($built, 'genap');
            }
        }

        return self::writeDocxPackage($path, self::documentXmlFor($body, [
            'orientation' => 'landscape',
            'landscape' => true,
            'margin' => ['top' => 1140, 'right' => 1140, 'bottom' => 1140, 'left' => 1140],
        ]));
    }

    /**
     * Susun XML body untuk dokumen PROTA.
     */
    private static function buildProtaBody(array $built): string
    {
        $prota = $built['prota'];
        $meta = $built['meta'];

        $out = '';
        $out .= self::kop();
        $out .= self::titleProta($prota);
        $out .= self::protaTable($prota);
        $out .= self::signature($prota['signature']);

        return $out;
    }

    /**
     * Susun XML body untuk dokumen PROSEM.
     */
    private static function buildProsemSemesterBody(array $built, string $semesterKey): string
    {
        $prosem = $built['prosem'][$semesterKey] ?? $built['prosem']['ganjil'];
        $meta = $built['meta'];

        $out = '';
        $out .= self::kop();
        $out .= self::titleProsem($prosem);
        $out .= self::prosemTable($prosem);
        $out .= self::signature($prosem['signature']);

        return $out;
    }

    /**
     * Kop surat resmi sekolah dari SchoolLetterhead.
     */
    private static function kop(): string
    {
        $lines = SchoolLetterhead::lines();
        $paragraphs = '';

        foreach ($lines as $i => $line) {
            $isCaps = mb_strtoupper($line, 'UTF-8') === $line && ! preg_match('/\d{5}|@|http/u', $line);
            $sz = ($i === 0 || $i === 1) ? 22 : 18;
            $bold = $isCaps || $i <= 1;

            $paragraphs .= self::p([
                self::run($line, bold: $bold, sz: $sz),
            ], ['align' => 'center', 'spaceAfter' => 20]);
        }

        return $paragraphs
            .self::p([], ['bottomBorder' => 'single', 'borderSz' => 12, 'spaceAfter' => 120]);
    }

    /**
     * Judul formal PROTA.
     */
    private static function titleProta(array $prota): string
    {
        $out = '';
        $out .= self::p([self::run('PROGRAM TAHUNAN', bold: true, sz: 24)], ['align' => 'center', 'spaceAfter' => 20]);
        $out .= self::p([self::run($prota['kelas'], bold: true, sz: 22)], ['align' => 'center', 'spaceAfter' => 20]);
        $out .= self::p([self::run("MATA PELAJARAN {$prota['mapel']}", bold: true, sz: 22)], ['align' => 'center', 'spaceAfter' => 20]);
        $out .= self::p([self::run("TAHUN PELAJARAN {$prota['tahun']}", bold: true, sz: 22)], ['align' => 'center', 'spaceAfter' => 140]);

        return $out;
    }

    /**
     * Judul formal PROSEM.
     */
    private static function titleProsem(array $prosem): string
    {
        $out = '';
        $out .= self::p([self::run($prosem['title'], bold: true, sz: 24)], ['align' => 'center', 'spaceAfter' => 20]);
        $out .= self::p([self::run("MATA PELAJARAN {$prosem['mapel']}", bold: true, sz: 22)], ['align' => 'center', 'spaceAfter' => 20]);
        $out .= self::p([self::run("TAHUN PELAJARAN {$prosem['tahun']}", bold: true, sz: 22)], ['align' => 'center', 'spaceAfter' => 140]);

        return $out;
    }

    /**
     * Tabel PROTA formal ber-border rapat.
     */
    private static function protaTable(array $prota): string
    {
        $gridCols = [
            self::PROTA_COL_ELEMEN,
            self::PROTA_COL_CP,
            self::PROTA_COL_TP,
            self::PROTA_COL_KODE,
            self::PROTA_COL_JP,
            self::PROTA_COL_KET,
        ];

        $rowsXml = '';

        // Header Tabel
        $headerCells = self::tc([self::p([self::run('ELEMEN', bold: true, sz: 18)], ['align' => 'center', 'spaceAfter' => 20])], ['w' => self::PROTA_COL_ELEMEN, 'shd' => 'F2F2F2', 'valign' => 'center'])
            .self::tc([self::p([self::run('CAPAIAN PEMBELAJARAN (CP)', bold: true, sz: 18)], ['align' => 'center', 'spaceAfter' => 20])], ['w' => self::PROTA_COL_CP, 'shd' => 'F2F2F2', 'valign' => 'center'])
            .self::tc([self::p([self::run('TUJUAN PEMBELAJARAN (TP)', bold: true, sz: 18)], ['align' => 'center', 'spaceAfter' => 20])], ['w' => self::PROTA_COL_TP, 'shd' => 'F2F2F2', 'valign' => 'center'])
            .self::tc([self::p([self::run('KODE TP', bold: true, sz: 18)], ['align' => 'center', 'spaceAfter' => 20])], ['w' => self::PROTA_COL_KODE, 'shd' => 'F2F2F2', 'valign' => 'center'])
            .self::tc([self::p([self::run('ALOKASI WAKTU', bold: true, sz: 18)], ['align' => 'center', 'spaceAfter' => 20])], ['w' => self::PROTA_COL_JP, 'shd' => 'F2F2F2', 'valign' => 'center'])
            .self::tc([self::p([self::run('KETERANGAN', bold: true, sz: 18)], ['align' => 'center', 'spaceAfter' => 20])], ['w' => self::PROTA_COL_KET, 'shd' => 'F2F2F2', 'valign' => 'center']);
        $rowsXml .= self::tr($headerCells, ['header' => true, 'cantSplit' => true]);

        $isFirstTableRow = true;
        $primaryElemen = $prota['primary_elemen'];
        $primaryCp = $prota['primary_cp'];

        foreach (['semester_1', 'semester_2'] as $sKey) {
            $sem = $prota['semesters'][$sKey];

            // Baris Separator Semester
            $semCells = self::tc([self::p([self::run($isFirstTableRow ? $primaryElemen : '', bold: true, sz: 17)], ['align' => 'left', 'spaceAfter' => 20])], [
                'w' => self::PROTA_COL_ELEMEN,
                'vMerge' => $isFirstTableRow ? 'restart' : 'continue',
                'valign' => 'top',
            ])
            .self::tc([self::p([self::run($isFirstTableRow ? $primaryCp : '', sz: 16)], ['align' => 'left', 'spaceAfter' => 20])], [
                'w' => self::PROTA_COL_CP,
                'vMerge' => $isFirstTableRow ? 'restart' : 'continue',
                'valign' => 'top',
            ])
            .self::tc([self::p([self::run($sem['label'], bold: true, sz: 18)], ['align' => 'center', 'spaceAfter' => 20])], [
                'w' => self::PROTA_COL_TP + self::PROTA_COL_KODE + self::PROTA_COL_JP + self::PROTA_COL_KET,
                'gridSpan' => 4,
                'shd' => 'E2E8F0',
                'valign' => 'center',
            ]);
            $rowsXml .= self::tr($semCells, ['cantSplit' => true]);
            $isFirstTableRow = false;

            // Baris Bab dan TP
            foreach ($sem['babs'] as $b) {
                // Header Bab
                $babCells = self::tc([], ['w' => self::PROTA_COL_ELEMEN, 'vMerge' => 'continue'])
                    .self::tc([], ['w' => self::PROTA_COL_CP, 'vMerge' => 'continue'])
                    .self::tc([self::p([self::run($b['bab_title'], bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                        'w' => self::PROTA_COL_TP + self::PROTA_COL_KODE,
                        'gridSpan' => 2,
                        'shd' => 'F3D0EA',
                        'valign' => 'center',
                    ])
                    .self::tc([], ['w' => self::PROTA_COL_JP])
                    .self::tc([], ['w' => self::PROTA_COL_KET]);
                $rowsXml .= self::tr($babCells, ['cantSplit' => true]);

                // Butir TP dalam Bab
                $tps = $b['tps'];
                $tpCount = count($tps);
                foreach ($tps as $tIdx => $t) {
                    $isFirstTpOfBab = ($tIdx === 0);

                    $tpCells = self::tc([], ['w' => self::PROTA_COL_ELEMEN, 'vMerge' => 'continue'])
                        .self::tc([], ['w' => self::PROTA_COL_CP, 'vMerge' => 'continue'])
                        .self::tc([self::p([self::run($t['tp'], sz: 16)], ['align' => 'left', 'spaceAfter' => 20])], [
                            'w' => self::PROTA_COL_TP,
                            'valign' => 'top',
                        ])
                        .self::tc([self::p([self::run($t['kode'], bold: true, sz: 16)], ['align' => 'center', 'spaceAfter' => 20])], [
                            'w' => self::PROTA_COL_KODE,
                            'valign' => 'center',
                        ])
                        .self::tc([self::p([self::run($isFirstTpOfBab ? (string) ($b['tp_alokasi_jp'] ?? max(1, $b['alokasi_jp'] - ($b['sumatif_jp'] ?? 2))) : '', sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                            'w' => self::PROTA_COL_JP,
                            'vMerge' => $isFirstTpOfBab ? 'restart' : 'continue',
                            'valign' => 'center',
                        ])
                        .self::tc([], ['w' => self::PROTA_COL_KET]);
                    $rowsXml .= self::tr($tpCells, ['cantSplit' => true]);
                }

                // Baris PENILAIAN SUMATIF Bab
                $sumatifCells = self::tc([], ['w' => self::PROTA_COL_ELEMEN, 'vMerge' => 'continue'])
                    .self::tc([], ['w' => self::PROTA_COL_CP, 'vMerge' => 'continue'])
                    .self::tc([self::p([self::run('PENILAIAN SUMATIF', bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                        'w' => self::PROTA_COL_TP + self::PROTA_COL_KODE,
                        'gridSpan' => 2,
                        'shd' => 'FCE7F3',
                        'valign' => 'center',
                    ])
                    .self::tc([self::p([self::run((string) $b['sumatif_jp'], bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                        'w' => self::PROTA_COL_JP,
                        'shd' => 'FCE7F3',
                        'valign' => 'center',
                    ])
                    .self::tc([], ['w' => self::PROTA_COL_KET]);
                $rowsXml .= self::tr($sumatifCells, ['cantSplit' => true]);
            }

            // Baris CADANGAN
            $cadanganCells = self::tc([], ['w' => self::PROTA_COL_ELEMEN, 'vMerge' => 'continue'])
                .self::tc([], ['w' => self::PROTA_COL_CP, 'vMerge' => 'continue'])
                .self::tc([self::p([self::run('CADANGAN', bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                    'w' => self::PROTA_COL_TP + self::PROTA_COL_KODE,
                    'gridSpan' => 2,
                    'valign' => 'center',
                ])
                .self::tc([self::p([self::run($sem['cadangan_jp'] > 0 ? (string) $sem['cadangan_jp'] : '', sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                    'w' => self::PROTA_COL_JP,
                    'valign' => 'center',
                ])
                .self::tc([], ['w' => self::PROTA_COL_KET]);
            $rowsXml .= self::tr($cadanganCells, ['cantSplit' => true]);

            // Baris TOTAL ALOKASI WAKTU SEMESTER
            $totalSemLabel = "TOTAL ALOKASI WAKTU {$sem['label']}";
            $totalCells = self::tc([], ['w' => self::PROTA_COL_ELEMEN, 'vMerge' => 'continue'])
                .self::tc([], ['w' => self::PROTA_COL_CP, 'vMerge' => 'continue'])
                .self::tc([self::p([self::run($totalSemLabel, bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                    'w' => self::PROTA_COL_TP + self::PROTA_COL_KODE,
                    'gridSpan' => 2,
                    'shd' => 'E2E8F0',
                    'valign' => 'center',
                ])
                .self::tc([self::p([self::run("{$sem['total_jp']} JP", bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                    'w' => self::PROTA_COL_JP,
                    'shd' => 'E2E8F0',
                    'valign' => 'center',
                ])
                .self::tc([], ['w' => self::PROTA_COL_KET]);
            $rowsXml .= self::tr($totalCells, ['cantSplit' => true]);
        }

        return self::tbl($gridCols, $rowsXml);
    }

    /**
     * Tabel PROSEM formal 26 kolom kalender dengan event vertikal.
     */
    private static function prosemTable(array $prosem): string
    {
        $months = $prosem['months'];
        $calendarEvents = $prosem['calendar_events'];
        $blockedColMap = $prosem['blocked_col_map'];

        $gridCols = [self::PROSEM_COL_MATERI, self::PROSEM_COL_JP];
        for ($i = 0; $i < 26; $i++) {
            $gridCols[] = self::PROSEM_WEEK_COL_W;
        }

        $rowsXml = '';

        // Row Header 1: Materi | JP | BULAN (span 26)
        $h1Cells = self::tc([self::p([self::run('Materi pokok/ Tujuan Pembelajaran', bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
            'w' => self::PROSEM_COL_MATERI,
            'vMerge' => 'restart',
            'shd' => 'F2F2F2',
            'valign' => 'center',
        ])
        .self::tc([self::p([self::run('Alokasi Waktu', bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
            'w' => self::PROSEM_COL_JP,
            'vMerge' => 'restart',
            'shd' => 'F2F2F2',
            'valign' => 'center',
        ])
        .self::tc([self::p([self::run('BULAN', bold: true, sz: 18)], ['align' => 'center', 'spaceAfter' => 20])], [
            'w' => 26 * self::PROSEM_WEEK_COL_W,
            'gridSpan' => 26,
            'shd' => 'F2F2F2',
            'valign' => 'center',
        ]);
        $rowsXml .= self::tr($h1Cells, ['header' => true, 'cantSplit' => true]);

        // Row Header 2: [Materi continue] | [JP continue] | Bulan 1 .. Bulan 6
        $h2Cells = self::tc([], ['w' => self::PROSEM_COL_MATERI, 'vMerge' => 'continue'])
            .self::tc([], ['w' => self::PROSEM_COL_JP, 'vMerge' => 'continue']);
        foreach ($months as $m) {
            $h2Cells .= self::tc([self::p([self::run($m['name'], bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                'w' => $m['weeks'] * self::PROSEM_WEEK_COL_W,
                'gridSpan' => $m['weeks'],
                'shd' => 'F2F2F2',
                'valign' => 'center',
            ]);
        }
        $rowsXml .= self::tr($h2Cells, ['header' => true, 'cantSplit' => true]);

        // Row Header 3: [Materi continue] | [JP continue] | 1, 2, 3, 4, 5 ...
        $h3Cells = self::tc([], ['w' => self::PROSEM_COL_MATERI, 'vMerge' => 'continue'])
            .self::tc([], ['w' => self::PROSEM_COL_JP, 'vMerge' => 'continue']);
        foreach ($months as $m) {
            for ($w = 1; $w <= $m['weeks']; $w++) {
                $h3Cells .= self::tc([self::p([self::run((string) $w, bold: true, sz: 16)], ['align' => 'center', 'spaceAfter' => 20])], [
                    'w' => self::PROSEM_WEEK_COL_W,
                    'shd' => 'F2F2F2',
                    'valign' => 'center',
                ]);
            }
        }
        $rowsXml .= self::tr($h3Cells, ['header' => true, 'cantSplit' => true]);

        // Data Rows
        $isFirstDataRow = true;

        foreach ($prosem['babs'] as $b) {
            // Baris Header Bab
            $babCells = self::tc([self::p([self::run($b['bab_title'], bold: true, sz: 17)], ['align' => 'left', 'spaceAfter' => 20])], [
                'w' => self::PROSEM_COL_MATERI,
                'shd' => 'F3D0EA',
                'valign' => 'center',
            ])
            .self::tc([], ['w' => self::PROSEM_COL_JP, 'shd' => 'F3D0EA']);

            // Minggu-minggu di baris header bab
            $babCells .= self::renderWeekCellsForDataRow(
                $isFirstDataRow,
                $calendarEvents,
                $blockedColMap,
                [],
                'F3D0EA'
            );
            $rowsXml .= self::tr($babCells, ['cantSplit' => true]);
            $isFirstDataRow = false;

            // Baris-baris TP
            $tps = $b['tps'];
            foreach ($tps as $tIdx => $t) {
                $isFirstTpOfBab = ($tIdx === 0);

                $tpCells = self::tc([self::p([self::run($t['tp'], sz: 16)], ['align' => 'left', 'spaceAfter' => 20])], [
                    'w' => self::PROSEM_COL_MATERI,
                    'valign' => 'top',
                ])
                .self::tc([self::p([self::run($isFirstTpOfBab ? (string) ($b['tp_alokasi_jp'] ?? max(1, $b['alokasi_jp'] - ($b['sumatif_jp'] ?? 2))) : '', sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                    'w' => self::PROSEM_COL_JP,
                    'vMerge' => $isFirstTpOfBab ? 'restart' : 'continue',
                    'valign' => 'center',
                ]);

                $tpCells .= self::renderWeekCellsForDataRow(
                    false,
                    $calendarEvents,
                    $blockedColMap,
                    $t['weeks'] ?? []
                );
                $rowsXml .= self::tr($tpCells, ['cantSplit' => true]);
            }

            // Baris PENILAIAN SUMATIF
            $sumatifCells = self::tc([self::p([self::run('PENILAIAN SUMATIF', bold: true, sz: 17)], ['align' => 'left', 'spaceAfter' => 20])], [
                'w' => self::PROSEM_COL_MATERI,
                'shd' => 'FCE7F3',
                'valign' => 'center',
            ])
            .self::tc([self::p([self::run((string) $prosem['sumatif_jp'], bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
                'w' => self::PROSEM_COL_JP,
                'shd' => 'FCE7F3',
                'valign' => 'center',
            ]);

            $sumatifCells .= self::renderWeekCellsForDataRow(
                false,
                $calendarEvents,
                $blockedColMap,
                $b['sumatif_weeks'] ?? [],
                'FCE7F3'
            );
            $rowsXml .= self::tr($sumatifCells, ['cantSplit' => true]);
        }

        // Baris CADANGAN
        $cadanganCells = self::tc([self::p([self::run('CADANGAN', bold: true, sz: 17)], ['align' => 'left', 'spaceAfter' => 20])], [
            'w' => self::PROSEM_COL_MATERI,
            'valign' => 'center',
        ])
        .self::tc([self::p([self::run($prosem['cadangan_jp'] > 0 ? (string) $prosem['cadangan_jp'] : '', sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
            'w' => self::PROSEM_COL_JP,
            'valign' => 'center',
        ]);
        $cadanganCells .= self::renderWeekCellsForDataRow(false, $calendarEvents, $blockedColMap, []);
        $rowsXml .= self::tr($cadanganCells, ['cantSplit' => true]);

        // Baris TOTAL JP
        $totalCells = self::tc([self::p([self::run('TOTAL JP', bold: true, sz: 17)], ['align' => 'left', 'spaceAfter' => 20])], [
            'w' => self::PROSEM_COL_MATERI,
            'shd' => 'E2E8F0',
            'valign' => 'center',
        ])
        .self::tc([self::p([self::run("{$prosem['total_jp']} JP", bold: true, sz: 17)], ['align' => 'center', 'spaceAfter' => 20])], [
            'w' => self::PROSEM_COL_JP,
            'shd' => 'E2E8F0',
            'valign' => 'center',
        ]);
        $totalCells .= self::renderWeekCellsForDataRow(false, $calendarEvents, $blockedColMap, [], 'E2E8F0');
        $rowsXml .= self::tr($totalCells, ['cantSplit' => true]);

        return self::tbl($gridCols, $rowsXml);
    }

    /**
     * Render sel minggu untuk baris tabel PROSEM, membedakan kolom event non-KBM (vertikal vMerge)
     * dan kolom aktif KBM.
     */
    private static function renderWeekCellsForDataRow(
        bool $isFirstDataRow,
        array $calendarEvents,
        array $blockedColMap,
        array $allocatedWeeks = [],
        ?string $defaultBg = null
    ): string {
        $xml = '';

        // Peta awal event untuk penentuan vMerge='restart' dan gridSpan
        $eventStartMap = [];
        foreach ($calendarEvents as $ev) {
            $eventStartMap[$ev['start_col']] = $ev;
        }

        $c = 0;
        while ($c < 26) {
            if (isset($blockedColMap[$c])) {
                // Kolom tertutup event non-KBM
                $ev = null;
                // Cari event yang menaungi kolom ini
                foreach ($calendarEvents as $cand) {
                    if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                        $ev = $cand;
                        break;
                    }
                }

                $hexColor = ltrim($ev['color'] ?? 'CBD5E1', '#');

                if ($isFirstDataRow) {
                    // Baris data pertama: buat sel vertikal besar dengan gridSpan & vMerge restart
                    $cleanLabel = $ev['label'];
                    $xml .= self::tc([self::p([self::run($cleanLabel, bold: true, sz: 15)], ['align' => 'center', 'spaceAfter' => 20])], [
                        'w' => $ev['col_span'] * self::PROSEM_WEEK_COL_W,
                        'gridSpan' => $ev['col_span'],
                        'vMerge' => 'restart',
                        'shd' => $hexColor,
                        'textDirection' => 'btLr', // Vertical bottom-to-top, left-to-right
                        'valign' => 'center',
                    ]);
                } else {
                    // Baris-baris data selanjutnya: lanjutkan vMerge
                    $xml .= self::tc([], [
                        'w' => $ev['col_span'] * self::PROSEM_WEEK_COL_W,
                        'gridSpan' => $ev['col_span'],
                        'vMerge' => 'continue',
                        'shd' => $hexColor,
                        'valign' => 'center',
                    ]);
                }

                $c += $ev['col_span'];
            } else {
                // Kolom aktif KBM
                $val = isset($allocatedWeeks[$c]) ? (string) $allocatedWeeks[$c] : '';
                $tcOpts = [
                    'w' => self::PROSEM_WEEK_COL_W,
                    'valign' => 'center',
                ];
                if ($defaultBg !== null) {
                    $tcOpts['shd'] = $defaultBg;
                }

                $xml .= self::tc([
                    self::p([self::run($val, bold: ($val !== ''), sz: 16)], ['align' => 'center', 'spaceAfter' => 20]),
                ], $tcOpts);

                $c++;
            }
        }

        return $xml;
    }

    /**
     * Blok tanda tangan formal (Kepala Sekolah di kiri, Guru Mata Pelajaran di kanan).
     */
    private static function signature(array $sig): string
    {
        $resolved = ProsemProtaDocument::resolveSignature($sig);

        $left = self::p([self::run('Mengetahui,', sz: 18)], ['spaceAfter' => 20])
            .self::p([self::run($resolved['jabatan_kepala'], sz: 18)], ['spaceAfter' => 700])
            .self::p([self::run($resolved['kepala_sekolah'], bold: true, underline: true, sz: 18)], ['spaceAfter' => 20])
            .self::p([self::run('NIK. '.$resolved['nip_kepala'], sz: 18)]);

        $right = self::p([self::run($resolved['tempat_tanggal'], sz: 18)], ['align' => 'right', 'spaceAfter' => 20])
            .self::p([self::run($resolved['jabatan_guru'], sz: 18)], ['align' => 'right', 'spaceAfter' => 700])
            .self::p([self::run($resolved['guru'], bold: true, underline: true, sz: 18)], ['align' => 'right', 'spaceAfter' => 20])
            .self::p([self::run('NIK. '.$resolved['nip_guru'], sz: 18)], ['align' => 'right']);

        $gridCols = [7279, 7279];
        $cells = self::tc([$left], ['w' => 7279, 'noBorder' => true])
            .self::tc([$right], ['w' => 7279, 'noBorder' => true]);

        return self::p([], ['spaceAfter' => 140])
            .self::tbl($gridCols, self::tr($cells), bordered: false, trailingP: false);
    }
}
