<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Parser dan penata struktur untuk dokumen Tujuan Pembelajaran (TP)
 * dan Alur Tujuan Pembelajaran (ATP) Kurikulum Merdeka.
 * Mengubah keluaran AI menjadi data terstruktur yang siap dirender di layar,
 * diexport ke PDF landscape (identik dengan format acuan sekolah),
 * maupun diexport ke dokumen Word (.docx) formal ber-border rapat.
 */
final class TpAtpDocument
{
    /** Cek apakah teks masukan tampak seperti dokumen TP atau ATP. */
    public static function looksLike(string $content): bool
    {
        $upper = Str::upper($content);

        return str_contains($upper, 'TUJUAN PEMBELAJARAN')
            || str_contains($upper, 'ALUR TUJUAN PEMBELAJARAN')
            || (str_contains($upper, 'KODE TP') && str_contains($upper, 'LINGKUP MATERI'))
            || str_contains($upper, 'DIAGRAM ALUR');
    }

    /**
     * Parse teks keluaran AI menjadi representasi terstruktur.
     *
     * @return array{
     *     parsed: bool,
     *     has_tp: bool,
     *     has_atp: bool,
     *     text: string,
     *     kop: list<string>,
     *     title_tp: string,
     *     title_atp: string,
     *     identity: array{
     *         mapel: string,
     *         fase_kelas: string,
     *         tahun: string,
     *         penyusun: string
     *     },
     *     tp_table: array{
     *         rows: list<array{
     *             elemen: string,
     *             cp: string,
     *             lingkup_materi: string,
     *             tp: string,
     *             kode_tp: string,
     *             jp: string,
     *             semester: string
     *         }>,
     *         elements: list<array{
     *             nama: string,
     *             cp: string,
     *             rowspan: int,
     *             materi_groups: list<array{
     *                 nama: string,
     *                 jp: string,
     *                 semester: string,
     *                 rowspan: int,
     *                 tps: list<array{kode: string, tp: string}>
     *             }>
     *         }>
     *     },
     *     atp_diagram: array{
     *         nodes: list<array{
     *             tahap: string,
     *             kelas: int,
     *             semester: string,
     *             kode: string,
     *             judul: string,
     *             type: string
     *         }>,
     *         by_grade: array<int, list<array{
     *             tahap: string,
     *             kelas: int,
     *             semester: string,
     *             kode: string,
     *             judul: string,
     *             type: string
     *         }>>,
     *         summary_rows: list<array{
     *             no: string,
     *             kelas: string,
     *             semester: string,
     *             lingkup_materi: string,
     *             kode_tp: string,
     *             tp: string,
     *             jp: string
     *         }>
     *     },
     *     signature: array{
     *         place_date: string,
     *         role: string,
     *         name: string,
     *         nip: string
     *     }
     * }
     */
    public static function parse(string $content): array
    {
        $lines = LearningDocument::sanitize($content, keepUnderline: true);
        $fullText = implode("\n", $lines);

        $doc = [
            'parsed' => false,
            'has_tp' => false,
            'has_atp' => false,
            'text' => $fullText,
            'kop' => [],
            'title_tp' => 'TUJUAN PEMBELAJARAN (TP)',
            'title_atp' => 'ALUR TUJUAN PEMBELAJARAN (ATP)',
            'identity' => [
                'mapel' => '',
                'fase_kelas' => 'D/VII-IX',
                'tahun' => '',
                'penyusun' => '',
            ],
            'tp_table' => [
                'rows' => [],
                'elements' => [],
            ],
            'atp_diagram' => [
                'nodes' => [],
                'by_grade' => [7 => [], 8 => [], 9 => []],
                'summary_rows' => [],
            ],
            'signature' => [
                'place_date' => '',
                'role' => 'Guru Mata Pelajaran',
                'name' => '',
                'nip' => '',
            ],
        ];

        $state = 'kop';
        $signatureLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $upper = Str::upper($trimmed);

            // 1. Tanda tangan
            if (str_starts_with($upper, 'TANDA TANGAN') || str_contains($upper, 'GURU MATA PELAJARAN')) {
                $state = 'signature';
                if (! str_starts_with($upper, 'TANDA TANGAN')) {
                    $signatureLines[] = $trimmed;
                }
                continue;
            }

            // 2. Diagram Alur ATP (harus dicek sebelum cek judul umum ALUR TUJUAN PEMBELAJARAN)
            if (str_contains($upper, 'DIAGRAM ALUR') || str_contains($upper, 'FLOWCHART ATP')) {
                $doc['has_atp'] = true;
                $state = 'atp_diagram';
                continue;
            }

            // 3. Tabel Ringkasan Alur ATP (harus sebelum cek judul umum)
            if (str_contains($upper, 'TABEL ALUR') || str_contains($upper, 'RINGKASAN ALUR')) {
                $doc['has_atp'] = true;
                $state = 'atp_summary';
                continue;
            }

            // 4. Judul ALUR TUJUAN PEMBELAJARAN (ATP)
            if (str_contains($upper, 'ALUR TUJUAN PEMBELAJARAN') || (str_contains($upper, 'ATP') && ! str_contains($upper, 'KODE TP'))) {
                $doc['title_atp'] = $trimmed;
                $doc['has_atp'] = true;
                $state = 'atp_identity';
                continue;
            }

            // 5. Tabel Tujuan Pembelajaran (TP)
            if (str_contains($upper, 'TABEL TUJUAN PEMBELAJARAN') || (str_contains($upper, 'ELEMEN') && str_contains($upper, 'CAPAIAN PEMBELAJARAN') && str_contains($upper, 'KODE TP'))) {
                $doc['has_tp'] = true;
                $state = 'tp_table';
                continue;
            }

            // 6. Judul TUJUAN PEMBELAJARAN (TP)
            if (str_contains($upper, 'TUJUAN PEMBELAJARAN (TP)') || (str_starts_with($upper, 'TUJUAN PEMBELAJARAN') && ! str_contains($upper, 'ALUR'))) {
                $doc['title_tp'] = $trimmed;
                $doc['has_tp'] = true;
                $state = 'identity';
                continue;
            }

            // State handler
            if ($state === 'kop') {
                if (preg_match('/^(MATA PELAJARAN|FASE|TAHUN PELAJARAN|GURU|PENYUSUN)\s*:/i', $trimmed)) {
                    $state = 'identity';
                    self::parseIdentityLine($trimmed, $doc['identity']);
                } else {
                    $doc['kop'][] = $trimmed;
                }
                continue;
            }

            if ($state === 'identity') {
                if (str_contains($trimmed, '|')) {
                    $state = 'tp_table';
                    self::parseTpRow($trimmed, $doc['tp_table']['rows']);
                } else {
                    self::parseIdentityLine($trimmed, $doc['identity']);
                }
                continue;
            }

            if ($state === 'atp_identity') {
                if (str_contains($trimmed, '|')) {
                    $state = 'atp_diagram';
                    self::parseAtpNodeRow($trimmed, $doc['atp_diagram']['nodes']);
                } else {
                    self::parseIdentityLine($trimmed, $doc['identity']);
                }
                continue;
            }

            if ($state === 'tp_table') {
                if (preg_match('/^(MATA PELAJARAN|FASE|TAHUN PELAJARAN|GURU|PENYUSUN)\s*:/i', $trimmed)) {
                    self::parseIdentityLine($trimmed, $doc['identity']);
                    continue;
                }
                self::parseTpRow($trimmed, $doc['tp_table']['rows']);
                continue;
            }

            if ($state === 'atp_diagram') {
                self::parseAtpNodeRow($trimmed, $doc['atp_diagram']['nodes']);
                continue;
            }

            if ($state === 'atp_summary') {
                self::parseAtpSummaryRow($trimmed, $doc['atp_diagram']['summary_rows']);
                continue;
            }

            if ($state === 'signature') {
                $signatureLines[] = $trimmed;
                continue;
            }
        }

        // Susun grup berjenjang (rowspan) untuk tabel TP
        if (! empty($doc['tp_table']['rows'])) {
            $doc['tp_table']['elements'] = self::groupTpRows($doc['tp_table']['rows']);
            $doc['tp_table']['pdf_chunks'] = self::chunkElementsForPdf($doc['tp_table']['elements']);
            $doc['has_tp'] = true;
        }

        if (empty($doc['atp_diagram']['nodes']) && $doc['has_atp'] && ! empty($doc['tp_table']['rows'])) {
            // Sintesis node diagram dari TP jika belum ada diagram eksplisit
            $doc['atp_diagram'] = self::synthesizeAtpFromTp($doc['tp_table']['rows']);
        }

        // Perkaya informasi Lingkup Materi dan Uraian TP pada setiap node diagram dari tabel TP & ringkasan
        $tpLookup = [];
        foreach ($doc['tp_table']['rows'] as $r) {
            $c = trim((string) ($r['kode_tp'] ?? ''));
            if ($c !== '') {
                $tpLookup[$c] = [
                    'materi' => trim((string) ($r['lingkup_materi'] ?? '')),
                    'tp' => trim((string) ($r['tp'] ?? '')),
                ];
            }
        }
        foreach ($doc['atp_diagram']['summary_rows'] as $sr) {
            $c = trim((string) ($sr['kode_tp'] ?? ''));
            if ($c !== '') {
                if (! isset($tpLookup[$c])) {
                    $tpLookup[$c] = [
                        'materi' => trim((string) ($sr['lingkup_materi'] ?? '')),
                        'tp' => trim((string) ($sr['tp'] ?? '')),
                    ];
                } else {
                    if (empty($tpLookup[$c]['materi']) && ! empty($sr['lingkup_materi'])) {
                        $tpLookup[$c]['materi'] = trim((string) $sr['lingkup_materi']);
                    }
                    if (empty($tpLookup[$c]['tp']) && ! empty($sr['tp'])) {
                        $tpLookup[$c]['tp'] = trim((string) $sr['tp']);
                    }
                }
            }
        }

        if (! empty($doc['atp_diagram']['nodes'])) {
            $doc['atp_diagram']['by_grade'] = [7 => [], 8 => [], 9 => []];
            foreach ($doc['atp_diagram']['nodes'] as &$node) {
                $c = trim((string) ($node['kode'] ?? ''));
                if ($c !== '' && isset($tpLookup[$c])) {
                    if (empty($node['materi'])) {
                        $node['materi'] = $tpLookup[$c]['materi'];
                    }
                    if (empty($node['tp_desc'])) {
                        $node['tp_desc'] = $tpLookup[$c]['tp'];
                    }
                }
                if (($node['type'] ?? 'tp') === 'tp' && empty($node['materi']) && ! empty($node['judul'])) {
                    $node['materi'] = $node['judul'];
                }

                $grade = $node['kelas'] >= 7 && $node['kelas'] <= 9 ? $node['kelas'] : 7;
                $doc['atp_diagram']['by_grade'][$grade][] = $node;
            }
            unset($node);
            $doc['has_atp'] = true;
        }

        // Tanda tangan
        self::parseSignatureLines($signatureLines, $doc['signature']);

        // Normalisasi alokasi JP per butir TP pada tabel ringkasan ATP
        self::normalizeAtpSummaryJp($doc['atp_diagram']['summary_rows'], $doc['tp_table']['elements'] ?? []);

        // Kop surat dokumen SELALU disesuaikan dengan yang diatur & diunggah di aplikasi SIMS,
        // bukan dari teks contoh yang diunggah pengguna atau hasil karangan AI.
        $doc['kop'] = SchoolLetterhead::lines();
        $doc['kop_html'] = SchoolLetterhead::kopTeksHtml();
        $doc['kop_logo_kiri'] = SchoolLetterhead::logoKiriUrl();
        $doc['kop_logo_kanan'] = SchoolLetterhead::logoKananUrl();
        $doc['kop_logo_kiri_data_uri'] = SchoolLetterhead::logoKiriDataUri();
        $doc['kop_logo_kanan_data_uri'] = SchoolLetterhead::logoKananDataUri();

        // Validasi parsed: minimal memiliki baris TP atau node ATP
        $doc['parsed'] = ! empty($doc['tp_table']['rows']) || ! empty($doc['atp_diagram']['nodes']);

        return $doc;
    }

    /** Parse baris identitas: LABEL : NILAI */
    private static function parseIdentityLine(string $line, array &$identity): void
    {
        $parts = explode(':', $line, 2);
        if (count($parts) < 2) {
            return;
        }

        $label = Str::upper(trim($parts[0]));
        $val = trim($parts[1]);

        if (str_contains($label, 'MATA PELAJARAN') || str_contains($label, 'MAPEL')) {
            $identity['mapel'] = $val;
        } elseif (str_contains($label, 'FASE') || str_contains($label, 'KELAS')) {
            $identity['fase_kelas'] = $val;
        } elseif (str_contains($label, 'TAHUN')) {
            $identity['tahun'] = $val;
        } elseif (str_contains($label, 'GURU') || str_contains($label, 'PENYUSUN')) {
            $identity['penyusun'] = $val;
        }
    }

    /** Parse baris tabel TP berpemisah pipe '|' */
    private static function parseTpRow(string $line, array &$rows): void
    {
        if (! str_contains($line, '|')) {
            return;
        }

        // Abaikan baris separator markdown seperti |---|---|
        if (preg_match('/^[\s\|\-:]+$/', $line)) {
            return;
        }

        $cols = array_map('trim', explode('|', trim($line, '|')));

        // Abaikan baris header
        $firstUpper = Str::upper($cols[0] ?? '');
        if (str_contains($firstUpper, 'ELEMEN') || str_contains($firstUpper, 'CAPAIAN') || str_contains($firstUpper, 'TAHAP') || str_contains($firstUpper, 'NO')) {
            return;
        }

        if (count($cols) < 4) {
            return;
        }

        // Lindungi dari baris nomor tabel ringkasan ATP (kolom 1 angka, kolom 2 kelas)
        if (is_numeric($cols[0]) && is_numeric($cols[1] ?? '')) {
            return;
        }

        // Lindungi dari baris diagram ATP jika tipe node ada di kolom terakhir
        $lastCol = strtolower(end($cols));
        if (in_array($lastCol, ['start', 'transition', 'end'], true)) {
            return;
        }

        // Format kolom: ELEMEN | CAPAIAN PEMBELAJARAN | LINGKUP MATERI | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU (JP) | SEMESTER
        $elemen = $cols[0] ?? '';
        $cp = $cols[1] ?? '';
        $lingkup = $cols[2] ?? '';
        $tp = $cols[3] ?? '';
        $kode = $cols[4] ?? '';
        $jp = $cols[5] ?? '';
        $sem = $cols[6] ?? '';

        // Tangani jika ada kolom bergeser
        if ($kode === '' && preg_match('/\b(P|PK|TP)\.?[0-9\.]+\b/i', $tp, $m)) {
            $kode = $m[0];
        }

        if ($tp !== '') {
            $rows[] = [
                'elemen' => $elemen,
                'cp' => $cp,
                'lingkup_materi' => $lingkup,
                'tp' => $tp,
                'kode_tp' => $kode,
                'jp' => $jp,
                'semester' => $sem !== '' ? $sem : 'Ganjil',
            ];
        }
    }

    /** Kelompokkan baris TP berdasarkan elemen & materi agar rowspan dapat dihitung */
    private static function groupTpRows(array $rows): array
    {
        $elements = [];
        $currentElemen = null;
        $currentMateri = null;

        $lastElemenName = '';
        $lastCpText = '';
        $lastMateriName = '';
        $lastJp = '';
        $lastSemester = 'Ganjil';

        foreach ($rows as $row) {
            $elemenName = $row['elemen'] !== '' ? $row['elemen'] : $lastElemenName;
            $cpText = $row['cp'] !== '' ? $row['cp'] : $lastCpText;
            $materiName = $row['lingkup_materi'] !== '' ? $row['lingkup_materi'] : $lastMateriName;
            $jp = $row['jp'] !== '' ? $row['jp'] : $lastJp;
            $semester = $row['semester'] !== '' ? $row['semester'] : $lastSemester;

            $lastElemenName = $elemenName;
            $lastCpText = $cpText;
            $lastMateriName = $materiName;
            $lastJp = $jp;
            $lastSemester = $semester;

            $normElemen = preg_replace('/\s+/', ' ', trim($elemenName));
            $normCp = preg_replace('/\s+/', ' ', trim($cpText));
            $currentNormElemen = $currentElemen !== null ? preg_replace('/\s+/', ' ', trim($currentElemen['nama'])) : null;
            $currentNormCp = $currentElemen !== null ? preg_replace('/\s+/', ' ', trim($currentElemen['cp'])) : null;

            if ($currentElemen === null || $currentNormElemen !== $normElemen || $currentNormCp !== $normCp) {
                if ($currentElemen !== null) {
                    if ($currentMateri !== null) {
                        $currentMateri['jp'] = self::resolveMateriTotalJp($currentMateri['jp_list'] ?? []);
                        unset($currentMateri['jp_list']);
                        $currentElemen['materi_groups'][] = $currentMateri;
                    }
                    $elements[] = $currentElemen;
                }
                $currentElemen = [
                    'nama' => $elemenName,
                    'cp' => $cpText,
                    'rowspan' => 0,
                    'materi_groups' => [],
                ];
                $currentMateri = null;
            }

            if ($currentMateri === null || $currentMateri['nama'] !== $materiName) {
                if ($currentMateri !== null) {
                    $currentMateri['jp'] = self::resolveMateriTotalJp($currentMateri['jp_list'] ?? []);
                    unset($currentMateri['jp_list']);
                    $currentElemen['materi_groups'][] = $currentMateri;
                }
                $currentMateri = [
                    'nama' => $materiName,
                    'jp' => $jp,
                    'jp_list' => [],
                    'semester' => $semester,
                    'rowspan' => 0,
                    'tps' => [],
                ];
            }

            $currentMateri['jp_list'][] = $row['jp'] !== '' ? $row['jp'] : $jp;
            $currentMateri['tps'][] = [
                'kode' => $row['kode_tp'],
                'tp' => $row['tp'],
            ];
            $currentMateri['rowspan']++;
            $currentElemen['rowspan']++;
        }

        if ($currentElemen !== null) {
            if ($currentMateri !== null) {
                $currentMateri['jp'] = self::resolveMateriTotalJp($currentMateri['jp_list'] ?? []);
                unset($currentMateri['jp_list']);
                $currentElemen['materi_groups'][] = $currentMateri;
            }
            $elements[] = $currentElemen;
        }

        return $elements;
    }

    /**
     * Deteksi jenjang kelas (7, 8, atau 9) dari suatu grup lingkup materi
     * berdasarkan kode TP atau penamaan materi.
     *
     * @param  array{nama?: string, tps?: list<array{kode?: string, tp?: string}>}  $mat
     */
    public static function detectMateriGrade(array $mat): ?int
    {
        foreach ($mat['tps'] ?? [] as $tp) {
            $kode = (string) ($tp['kode'] ?? '');
            if (preg_match('/\b[A-Za-z]*\.?(7|8|9)\.[0-9]+/i', $kode, $m)) {
                return (int) $m[1];
            }
            if (preg_match('/\b(VII|7)\b/i', $kode)) {
                return 7;
            }
            if (preg_match('/\b(VIII|8)\b/i', $kode)) {
                return 8;
            }
            if (preg_match('/\b(IX|9)\b/i', $kode)) {
                return 9;
            }
        }

        $materiNama = (string) ($mat['nama'] ?? '');
        if (preg_match('/\b(?:Kelas|Kls)\s*(?:VII|7)\b/i', $materiNama)) {
            return 7;
        }
        if (preg_match('/\b(?:Kelas|Kls)\s*(?:VIII|8)\b/i', $materiNama)) {
            return 8;
        }
        if (preg_match('/\b(?:Kelas|Kls)\s*(?:IX|9)\b/i', $materiNama)) {
            return 9;
        }

        return null;
    }

    /**
     * Pecah elemen-elemen TP ke dalam beberapa potongan tabel (chunks) yang aman
     * untuk dirender ke halaman A4 Landscape DomPDF tanpa pernah memicu bug pergeseran
     * kolom akibat rowspan melintasi page break.
     *
     * @param  list<array{
     *     nama: string,
     *     cp: string,
     *     rowspan: int,
     *     materi_groups: list<array{
     *         nama: string,
     *         jp: string,
     *         semester: string,
     *         rowspan: int,
     *         tps: list<array{kode: string, tp: string}>
     *     }>
     * }>  $elements
     * @return list<array{
     *     grade: ?int,
     *     grade_label: ?string,
     *     elements: list<array{
     *         nama: string,
     *         cp: string,
     *         rowspan: int,
     *         materi_groups: list<array{
     *             nama: string,
     *             jp: string,
     *             semester: string,
     *             rowspan: int,
     *             tps: list<array{kode: string, tp: string}>
     *         }>
     *     }>
     * }>
     */
    public static function chunkElementsForPdf(array $elements, int $maxFirstPage = 13, int $maxNextPages = 14): array
    {
        if (empty($elements)) {
            return [];
        }

        $totalRows = array_sum(array_column($elements, 'rowspan'));
        if ($totalRows <= $maxFirstPage) {
            return [[
                'grade' => null,
                'grade_label' => null,
                'elements' => $elements,
            ]];
        }

        $gradesFound = [];
        foreach ($elements as $el) {
            foreach ($el['materi_groups'] as $mat) {
                $g = self::detectMateriGrade($mat);
                if ($g !== null) {
                    $gradesFound[$g] = true;
                }
            }
        }

        $isMultiGrade = count($gradesFound) >= 2;

        $chunks = [];
        $currentChunkElements = [];
        $currentRowCount = 0;
        $currentGrade = null;
        $limit = $maxFirstPage;

        $gradeLabels = [
            7 => 'Fase D — Kelas VII',
            8 => 'Fase D — Kelas VIII',
            9 => 'Fase D — Kelas IX',
        ];

        foreach ($elements as $el) {
            $currentElInChunk = [
                'nama' => $el['nama'],
                'cp' => $el['cp'],
                'rowspan' => 0,
                'materi_groups' => [],
            ];

            foreach ($el['materi_groups'] as $mat) {
                $matRows = count($mat['tps'] ?? []);
                if ($matRows === 0) {
                    continue;
                }

                $matGrade = self::detectMateriGrade($mat);

                $gradeBreak = $isMultiGrade && $currentGrade !== null && $matGrade !== null && $matGrade !== $currentGrade;
                $overflowBreak = ($currentRowCount + $matRows) > $limit;

                if ($currentRowCount > 0 && ($gradeBreak || $overflowBreak)) {
                    if ($currentElInChunk['rowspan'] > 0) {
                        $currentChunkElements[] = $currentElInChunk;
                    }
                    $chunks[] = [
                        'grade' => $currentGrade,
                        'grade_label' => $currentGrade ? ($gradeLabels[$currentGrade] ?? "Kelas {$currentGrade}") : null,
                        'elements' => $currentChunkElements,
                    ];
                    $currentChunkElements = [];
                    $currentRowCount = 0;
                    $limit = $maxNextPages;
                    $currentElInChunk = [
                        'nama' => $el['nama'],
                        'cp' => $el['cp'],
                        'rowspan' => 0,
                        'materi_groups' => [],
                    ];
                }

                $currentElInChunk['materi_groups'][] = $mat;
                $currentElInChunk['rowspan'] += $matRows;
                $currentRowCount += $matRows;
                if ($matGrade !== null) {
                    $currentGrade = $matGrade;
                }
            }

            if ($currentElInChunk['rowspan'] > 0) {
                $currentChunkElements[] = $currentElInChunk;
            }
        }

        if (! empty($currentChunkElements)) {
            $chunks[] = [
                'grade' => $currentGrade,
                'grade_label' => $currentGrade ? ($gradeLabels[$currentGrade] ?? "Kelas {$currentGrade}") : null,
                'elements' => $currentChunkElements,
            ];
        }

        return $chunks;
    }

    /**
     * Hitung total alokasi JP untuk satu lingkup materi secara proporsional.
     * Jika baris-baris TP diisi angka pembagian per TP (misal 9, 9, 9), fungsi ini
     * menjumlahkannya menjadi alokasi utuh lingkup materi tersebut (27 JP).
     *
     * @param  list<string>  $jpList
     */
    private static function resolveMateriTotalJp(array $jpList): string
    {
        if (empty($jpList)) {
            return '';
        }

        $numericValues = [];
        foreach ($jpList as $val) {
            $cleaned = trim((string) $val);
            if (preg_match('/(\d+)/', $cleaned, $m)) {
                $numericValues[] = (int) $m[1];
            }
        }

        if (empty($numericValues)) {
            return trim((string) $jpList[0]);
        }

        $first = $numericValues[0];
        $allSame = count(array_unique($numericValues)) === 1;

        // Jika semua baris memiliki angka yang sama:
        if ($allSame) {
            // Jika sudah bernilai alokasi materi utuh (misal >= 16 JP: 18, 20, 24, 27, 30, dst)
            if ($first >= 16) {
                return $first . ' JP';
            }

            // Jika angkanya kecil (< 16, misal 6, 8, 9, 10, 12 JP per butir TP):
            // Ini adalah pembagian per butir TP dari total alokasi materi.
            // Jumlahkan untuk mendapatkan alokasi total materi tersebut.
            return array_sum($numericValues) . ' JP';
        }

        // Jika angka per baris bervariasi tapi semuanya sub-alokasi kecil
        $max = max($numericValues);
        if ($max < 16) {
            return array_sum($numericValues) . ' JP';
        }

        return $first . ' JP';
    }

    /**
     * Hitung pembagian alokasi JP per butir TP dari total JP satu lingkup materi.
     * Contoh:
     * - Total 18 JP, 2 TP => [9, 9] ("9 JP", "9 JP")
     * - Total 27 JP, 3 TP => [9, 9, 9] ("9 JP", "9 JP", "9 JP")
     * - Total 27 JP, 4 TP => [7, 7, 7, 6] ("7 JP", "7 JP", "7 JP", "6 JP")
     * - Total 27 JP, 2 TP => [14, 13] ("14 JP", "13 JP")
     * - Total 24 JP, 3 TP => [8, 8, 8] ("8 JP", "8 JP", "8 JP")
     *
     * @return list<int>
     */
    public static function distributeJpPerTp(int $totalJp, int $tpCount): array
    {
        if ($tpCount <= 0) {
            return [];
        }
        if ($tpCount === 1 || $totalJp <= 0) {
            return array_fill(0, $tpCount, max(0, $totalJp));
        }

        $base = intdiv($totalJp, $tpCount);
        $remainder = $totalJp % $tpCount;

        $result = [];
        for ($i = 0; $i < $tpCount; $i++) {
            $result[] = $base + ($i < $remainder ? 1 : 0);
        }

        return $result;
    }

    /**
     * Normalisasi alokasi JP pada tabel ringkasan ATP agar mencerminkan alokasi
     * PER BUTIR TUJUAN PEMBELAJARAN (bukan mengulang total alokasi materi).
     *
     * Total JP lingkup materi dibagi secara proporsional sesuai jumlah butir TP-nya.
     *
     * @param  list<array{
     *     no: string,
     *     kelas: string,
     *     semester: string,
     *     lingkup_materi: string,
     *     kode_tp: string,
     *     tp: string,
     *     jp: string
     * }>  $summaryRows
     * @param  list<array{
     *     nama: string,
     *     cp: string,
     *     rowspan: int,
     *     materi_groups: list<array{
     *         nama: string,
     *         jp: string,
     *         semester: string,
     *         rowspan: int,
     *         tps: list<array{kode: string, tp: string}>
     *     }>
     * }>  $elements
     */
    public static function normalizeAtpSummaryJp(array &$summaryRows, array $elements = []): void
    {
        if (empty($summaryRows)) {
            return;
        }

        // 1. Bangun peta alokasi terbagi per TP berdasarkan grup materi di tabel TP
        $allocatedByKode = [];
        $allocatedByMateriIdx = [];

        foreach ($elements as $el) {
            foreach ($el['materi_groups'] as $mat) {
                $totalJp = 0;
                if (preg_match('/(\d+)/', (string) ($mat['jp'] ?? ''), $m)) {
                    $totalJp = (int) $m[1];
                }

                $tps = $mat['tps'] ?? [];
                $n = count($tps);
                if ($totalJp > 0 && $n > 0) {
                    $distributed = self::distributeJpPerTp($totalJp, $n);
                    $normMat = preg_replace('/\s+/', ' ', mb_strtolower(trim((string) ($mat['nama'] ?? ''))));

                    foreach ($tps as $i => $tp) {
                        $valStr = $distributed[$i].' JP';
                        $kode = trim((string) ($tp['kode'] ?? ''));
                        if ($kode !== '') {
                            $allocatedByKode[$kode] = $valStr;
                        }
                        $allocatedByMateriIdx[$normMat.'::'.$i] = $valStr;
                    }
                }
            }
        }

        // 2. Jika elemen TP kosong atau tidak cocok, kelompokkan summaryRows berdasarkan lingkup materi
        if (empty($allocatedByKode)) {
            $groups = [];
            foreach ($summaryRows as $idx => $sr) {
                $normMat = preg_replace('/\s+/', ' ', mb_strtolower(trim((string) ($sr['lingkup_materi'] ?? ''))));
                $groups[$normMat][] = $idx;
            }

            foreach ($groups as $normMat => $indices) {
                $count = count($indices);
                if ($count <= 1) {
                    continue;
                }

                $firstJp = 0;
                $allSame = true;
                foreach ($indices as $i => $rowIdx) {
                    $val = preg_match('/(\d+)/', (string) ($summaryRows[$rowIdx]['jp'] ?? ''), $m) ? (int) $m[1] : 0;
                    if ($i === 0) {
                        $firstJp = $val;
                    } elseif ($val !== $firstJp) {
                        $allSame = false;
                    }
                }

                // Jika semua baris memiliki angka yang sama >= 16 (misal 18 JP atau 27 JP berulang):
                // Ini adalah total materi yang belum dibagi. Bagikan ke butir TP!
                if ($allSame && $firstJp >= 16) {
                    $distributed = self::distributeJpPerTp($firstJp, $count);
                    foreach ($indices as $k => $rowIdx) {
                        $summaryRows[$rowIdx]['jp'] = $distributed[$k].' JP';
                    }
                }
            }

            return;
        }

        // 3. Terapkan hasil pembagian ke setiap baris tabel ringkasan ATP
        $materiCounters = [];
        foreach ($summaryRows as &$sr) {
            $kode = trim((string) ($sr['kode_tp'] ?? ''));
            $normMat = preg_replace('/\s+/', ' ', mb_strtolower(trim((string) ($sr['lingkup_materi'] ?? ''))));
            $idxInMat = $materiCounters[$normMat] ?? 0;
            $materiCounters[$normMat] = $idxInMat + 1;

            if ($kode !== '' && isset($allocatedByKode[$kode])) {
                $sr['jp'] = $allocatedByKode[$kode];
            } elseif (isset($allocatedByMateriIdx[$normMat.'::'.$idxInMat])) {
                $sr['jp'] = $allocatedByMateriIdx[$normMat.'::'.$idxInMat];
            }
        }
        unset($sr);
    }

    /** Parse baris diagram alur ATP: TAHAP | KELAS | SEMESTER | KODE | LINGKUP MATERI | TUJUAN PEMBELAJARAN | TIPE */
    private static function parseAtpNodeRow(string $line, array &$nodes): void
    {
        if (! str_contains($line, '|')) {
            return;
        }

        if (preg_match('/^[\s\|\-:]+$/', $line)) {
            return;
        }

        $cols = array_map('trim', explode('|', trim($line, '|')));

        $firstUpper = Str::upper($cols[0] ?? '');
        if (str_contains($firstUpper, 'TAHAP') || str_contains($firstUpper, 'KELAS') || str_contains($firstUpper, 'KODE')) {
            return;
        }

        if (count($cols) < 3) {
            return;
        }

        $clean = function (?string $v): string {
            $t = trim((string) ($v ?? ''));
            return in_array($t, ['-', '–', '—', '', 'null', 'undefined'], true) ? '' : $t;
        };

        $tahap = trim($cols[0] ?? '');
        $kelasRaw = trim($cols[1] ?? '7');
        $semester = trim($cols[2] ?? 'Ganjil');

        // Identifikasi tipe tahapan (start, transition, end, tp)
        $tahapUpper = Str::upper($tahap);
        $lastCol = strtolower(trim(end($cols) ?: 'tp'));
        $type = in_array($lastCol, ['start', 'tp', 'transition', 'end'], true) ? $lastCol : 'tp';

        if (str_starts_with($tahapUpper, 'AWAL') || $tahapUpper === 'START') {
            $type = 'start';
        } elseif (str_contains($tahapUpper, 'TRANSISI') || str_contains($tahapUpper, 'MENUJU') || $tahapUpper === 'MID' || str_contains($tahapUpper, 'REFLEKSI')) {
            $type = 'transition';
        } elseif (str_contains($tahapUpper, 'AKHIR') || $tahapUpper === 'END') {
            $type = 'end';
        }

        $kode = '';
        $materi = '';
        $tpDesc = '';
        $judul = '';

        if (count($cols) >= 7) {
            // Format 7 kolom: TAHAP | KELAS | SEMESTER | KODE | LINGKUP MATERI | TUJUAN PEMBELAJARAN | TIPE
            // Atau: TAHAP | KELAS | SEMESTER | LINGKUP MATERI | KODE | TUJUAN PEMBELAJARAN | TIPE
            $c3 = $clean($cols[3]);
            $c4 = $clean($cols[4]);
            $c5 = $clean($cols[5]);

            if (preg_match('/^[A-Z0-9\.\-]{2,15}$/i', $c3)) {
                $kode = $c3;
                $materi = $c4;
                $tpDesc = $c5;
            } elseif (preg_match('/^[A-Z0-9\.\-]{2,15}$/i', $c4)) {
                $materi = $c3;
                $kode = $c4;
                $tpDesc = $c5;
            } else {
                $kode = '';
                $materi = $c4 !== '' ? $c4 : $c3;
                $tpDesc = $c5;
            }

            if ($type === 'start' || $type === 'transition' || $type === 'end') {
                $judul = $tpDesc !== '' ? $tpDesc : ($materi !== '' ? $materi : '');
            } else {
                $judul = $materi !== '' ? $materi : ($tpDesc !== '' ? $tpDesc : '');
            }
        } else {
            // Format 6 kolom standar: TAHAP | KELAS | SEMESTER | KODE | JUDUL ALUR | TIPE
            $kode = $clean($cols[3] ?? '');
            $rawJudul = $clean($cols[4] ?? ($cols[3] ?? ''));
            $judul = $rawJudul;

            if (str_contains($judul, ':')) {
                [$m, $t] = explode(':', $judul, 2);
                $materi = $clean($m);
                $tpDesc = $clean($t);
                $judul = $materi !== '' ? $materi : $tpDesc;
            } else {
                $materi = ($type === 'tp') ? $judul : '';
                $tpDesc = '';
            }
        }

        // Normalisasi kelas (7, 8, 9)
        preg_match('/\b(7|8|9|VII|VIII|IX)\b/i', $kelasRaw, $km);
        $gradeMap = ['7' => 7, 'VII' => 7, '8' => 8, 'VIII' => 8, '9' => 9, 'IX' => 9];
        $kelas = isset($km[0]) ? ($gradeMap[strtoupper($km[0])] ?? 7) : 7;

        // Auto-detect type dari teks judul jika masih default tp
        $upperJudul = Str::upper($judul);
        if ($type === 'tp') {
            if (str_contains($upperJudul, 'AWAL FASE') || str_starts_with($upperJudul, 'AWAL')) {
                $type = 'start';
            } elseif (str_contains($upperJudul, 'MENUJU PEMBELAJARAN') || str_contains($upperJudul, 'MENUJU') || str_contains($upperJudul, 'TRANSISI') || str_contains($upperJudul, 'REFLEKSI')) {
                $type = 'transition';
            } elseif (str_contains($upperJudul, 'AKHIR FASE') || str_starts_with($upperJudul, 'CAPAIAN PEMBELAJARAN AKHIR FASE')) {
                $type = 'end';
            }
        }

        // Fallback tegas agar judul node start/transisi/akhir tidak pernah bernilai kosong atau tanda hubung '-'
        if ($type === 'start' && $judul === '') {
            $judul = "Awal fase D Kelas {$kelas} Semester {$semester}";
        } elseif ($type === 'transition' && $judul === '') {
            $nextG = $kelas < 9 ? $kelas + 1 : 9;
            $judul = "Capaian Pembelajaran Akhir Fase D Kelas {$kelas} menuju pembelajaran fase D kelas {$nextG} Semester Ganjil";
        } elseif ($type === 'end' && $judul === '') {
            $judul = "Capaian Pembelajaran Akhir Fase D";
        }

        $nodes[] = [
            'tahap' => $tahap,
            'kelas' => $kelas,
            'semester' => $semester,
            'kode' => $kode,
            'judul' => $judul,
            'materi' => $materi,
            'tp_desc' => $tpDesc,
            'type' => $type,
        ];
    }

    /** Parse baris ringkasan tabel ATP */
    private static function parseAtpSummaryRow(string $line, array &$summaryRows): void
    {
        if (! str_contains($line, '|')) {
            return;
        }

        if (preg_match('/^[\s\|\-:]+$/', $line)) {
            return;
        }

        $cols = array_map('trim', explode('|', trim($line, '|')));
        $firstUpper = Str::upper($cols[0] ?? '');
        if (str_contains($firstUpper, 'NO') || str_contains($firstUpper, 'KELAS')) {
            return;
        }

        if (count($cols) < 4) {
            return;
        }

        $summaryRows[] = [
            'no' => $cols[0] ?? '',
            'kelas' => $cols[1] ?? '7',
            'semester' => $cols[2] ?? 'Ganjil',
            'lingkup_materi' => $cols[3] ?? '',
            'kode_tp' => $cols[4] ?? '',
            'tp' => $cols[5] ?? '',
            'jp' => $cols[6] ?? '',
        ];
    }

    /** Sintesis node alur ATP jika dokumen hanya berisi tabel TP */
    private static function synthesizeAtpFromTp(array $tpRows): array
    {
        $nodes = [];
        $byGrade = [7 => [], 8 => [], 9 => []];
        $summary = [];

        $groupedByGradeAndSem = [];
        foreach ($tpRows as $idx => $r) {
            // Ekstrak grade dari kode TP (misal PK.7.1 -> grade 7, P.8.2 -> grade 8)
            $grade = 7;
            if (preg_match('/\b[A-Z]*\.(7|8|9)\.[0-9]+\b/i', $r['kode_tp'], $m)) {
                $grade = (int) $m[1];
            }
            $sem = stripos($r['semester'], 'Genap') !== false ? 'Genap' : 'Ganjil';
            $groupedByGradeAndSem[$grade][$sem][] = $r;

            $summary[] = [
                'no' => (string) ($idx + 1),
                'kelas' => (string) $grade,
                'semester' => $sem,
                'lingkup_materi' => $r['lingkup_materi'],
                'kode_tp' => $r['kode_tp'],
                'tp' => $r['tp'],
                'jp' => $r['jp'],
            ];
        }

        foreach ([7, 8, 9] as $g) {
            if (! isset($groupedByGradeAndSem[$g])) {
                continue;
            }

            // Awal Semester Ganjil
            $nodes[] = [
                'tahap' => 'Awal',
                'kelas' => $g,
                'semester' => 'Ganjil',
                'kode' => '',
                'judul' => "Awal fase D Kelas {$g} Semester Ganjil",
                'type' => 'start',
            ];

            foreach ($groupedByGradeAndSem[$g]['Ganjil'] ?? [] as $item) {
                $materi = trim((string) ($item['lingkup_materi'] ?? ''));
                $tpDesc = trim((string) ($item['tp'] ?? ''));
                $concise = Str::limit($materi ?: $tpDesc, 45, '');
                $nodes[] = [
                    'tahap' => 'TP',
                    'kelas' => $g,
                    'semester' => 'Ganjil',
                    'kode' => $item['kode_tp'],
                    'judul' => $concise,
                    'materi' => $materi,
                    'tp_desc' => $tpDesc,
                    'type' => 'tp',
                ];
            }

            // Awal Semester Genap
            $nodes[] = [
                'tahap' => 'Mid',
                'kelas' => $g,
                'semester' => 'Genap',
                'kode' => '',
                'judul' => "Awal fase D Kelas {$g} Semester Genap",
                'materi' => '',
                'tp_desc' => '',
                'type' => 'start',
            ];

            foreach ($groupedByGradeAndSem[$g]['Genap'] ?? [] as $item) {
                $materi = trim((string) ($item['lingkup_materi'] ?? ''));
                $tpDesc = trim((string) ($item['tp'] ?? ''));
                $concise = Str::limit($materi ?: $tpDesc, 45, '');
                $nodes[] = [
                    'tahap' => 'TP',
                    'kelas' => $g,
                    'semester' => 'Genap',
                    'kode' => $item['kode_tp'],
                    'judul' => $concise,
                    'materi' => $materi,
                    'tp_desc' => $tpDesc,
                    'type' => 'tp',
                ];
            }

            // Transisi antarkelas atau akhir fase
            if ($g < 9) {
                $next = $g + 1;
                $nodes[] = [
                    'tahap' => 'Transisi',
                    'kelas' => $g,
                    'semester' => 'Genap',
                    'kode' => '',
                    'judul' => "Capaian Pembelajaran Akhir Fase D Kelas {$g} menuju pembelajaran fase D kelas {$next} Semester Ganjil",
                    'type' => 'transition',
                ];
            } else {
                $nodes[] = [
                    'tahap' => 'Akhir',
                    'kelas' => 9,
                    'semester' => 'Genap',
                    'kode' => '',
                    'judul' => 'Capaian Pembelajaran Akhir Fase D Kelas 9',
                    'type' => 'end',
                ];
            }
        }

        foreach ($nodes as $n) {
            $byGrade[$n['kelas']][] = $n;
        }

        return [
            'nodes' => $nodes,
            'by_grade' => $byGrade,
            'summary_rows' => $summary,
        ];
    }

    /** Ekstrak informasi tanggal, tempat, dan nama penandatangan */
    private static function parseSignatureLines(array $lines, array &$sig): void
    {
        $lines = array_values(array_filter($lines, fn ($l) => trim($l) !== ''));

        foreach ($lines as $line) {
            // Contoh: "Tanjungpinang, 17 Juli 2026"
            if (preg_match('/^([A-Za-z\s]+),\s*(\d{1,2}\s+[A-Za-z]+\s+\d{4})/u', $line)) {
                $sig['place_date'] = $line;
                continue;
            }

            if (str_contains(Str::upper($line), 'GURU MATA PELAJARAN')) {
                $sig['role'] = $line;
                continue;
            }

            if (preg_match('/^(NIK|NIP)[\.\s:]*(.+)$/i', $line, $m)) {
                $val = trim($m[2]);
                if (! str_contains($val, '......') && ! str_contains($val, '______')) {
                    $sig['nip'] = $val;
                }
                continue;
            }

            // Baris nama guru
            if ($sig['name'] === '' && ! str_contains($line, ',') && ! str_contains(Str::upper($line), 'MENGETAHUI') && ! str_contains(Str::upper($line), 'KEPALA SEKOLAH')) {
                $sig['name'] = $line;
            } elseif ($sig['name'] === '' && preg_match('/^[A-Z][a-zA-Z\s\.,]+$/', $line)) {
                $sig['name'] = $line;
            }
        }

        $user = auth()->user();

        if ($sig['place_date'] === '') {
            $kota = 'Tanjungpinang';
            if (class_exists(\App\Models\Setting::class)) {
                try {
                    $kota = trim((string) \App\Models\Setting::get('kota', 'Tanjungpinang'));
                    if (str_starts_with(strtolower($kota), 'kota ')) {
                        $kota = substr($kota, 5);
                    }
                } catch (\Throwable $e) {
                }
            }
            $kota = trim($kota) ?: 'Tanjungpinang';

            $bulanIndonesia = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
            ];
            $sig['place_date'] = "{$kota}, ".now()->format('j').' '.$bulanIndonesia[(int) now()->format('n')].' '.now()->format('Y');
        }

        if ($sig['name'] === '' || str_contains($sig['name'], '[Nama') || str_contains($sig['name'], '......')) {
            $sig['name'] = $user?->guru?->nama ?: ($user?->name ?: 'Guru Mata Pelajaran');
        }

        if ($sig['nip'] === '' || str_contains($sig['nip'], '......')) {
            $sig['nip'] = $user?->guru?->nik ?: ($user?->guru?->nip ?: ($user?->username ?: ''));
        }
    }
}
