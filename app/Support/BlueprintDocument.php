<?php

namespace App\Support;

use Illuminate\Support\Str;

final class BlueprintDocument
{
    public static function looksLike(string $content): bool
    {
        $normalized = str_replace(['–', '—'], '-', Str::upper($content));

        return str_contains($normalized, 'KISI-KISI') || str_contains($normalized, 'KISI KISI');
    }

    public static function parse(string $content): array
    {
        $normalizedContent = str_replace(['–', '—'], '-', $content);
        $lines = LearningDocument::sanitize($normalizedContent, keepUnderline: true);
        $doc = [
            'parsed' => false,
            'text' => implode("\n", $lines),
            'kop' => [],
            'title' => 'KISI-KISI SOAL',
            'subject' => '',
            'academic_year' => '',
            'identity' => [],
            'identity_map' => [],
            'competencies' => [],
            'table_type' => '7col',
            'rows' => [],
            'legend' => [],
            'note' => '',
            'answer_title' => 'KUNCI JAWABAN',
            'answer_subtitle' => '',
            'answer_sections' => [],
            'recap' => [],
            'recap_note' => '',
            'kepala_sekolah' => SchoolLetterhead::kepalaSekolah(),
            'guru_mapel' => auth()->user()?->name ?? 'Guru Mata Pelajaran',
        ];

        $state = 'top';
        $answerSection = null;
        $flushAnswerSection = function () use (&$doc, &$answerSection) {
            if ($answerSection !== null) {
                // Filter out empty rows that might have snuck in
                $answerSection['rows'] = array_values(array_filter($answerSection['rows'], fn ($r) => ! empty($r)));
                $doc['answer_sections'][] = $answerSection;
            }
            $answerSection = null;
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || $trimmed === '***' || $trimmed === '---') {
                continue;
            }

            // Abaikan percakapan/basa-basi AI di bagian awal
            if ($state === 'top' && (
                preg_match('/^(?:berikut\s+(?:adalah|dokumen|ini)|tentu|baik|halo|dokumen\s+kisi)/iu', $trimmed)
                || (mb_strlen($trimmed) > 80 && ! str_contains(Str::upper($trimmed), 'KISI-KISI') && ! str_contains(Str::upper($trimmed), 'YAYASAN') && ! str_contains(Str::upper($trimmed), 'SMP'))
            )) {
                continue;
            }

            $upper = Str::upper($trimmed);

            // Hanya transisi ke KUNCI JAWABAN atau PEDOMAN PENILAIAN jika merupakan baris judul (bukan kalimat biasa)
            $isHeadingKunci = (preg_match('/^(?:#+\s*)?(?:KUNCI JAWABAN|PEDOMAN PENILAIAN)/iu', $trimmed)
                || ($state !== 'top' && (str_starts_with($upper, 'KUNCI JAWABAN') || str_starts_with($upper, 'PEDOMAN PENILAIAN'))))
                && mb_strlen($trimmed) < 120;

            if ($isHeadingKunci) {
                $flushAnswerSection();
                $doc['answer_title'] = $trimmed;
                $state = 'answer_intro';
                continue;
            }

            // Hanya transisi ke REKAP PENILAIAN jika merupakan baris judul
            $isHeadingRekap = (preg_match('/^(?:#+\s*)?(?:REKAP PENILAIAN|REKAPITULASI)/iu', $trimmed)
                || ($state !== 'top' && (str_starts_with($upper, 'REKAP PENILAIAN') || str_starts_with($upper, 'REKAPITULASI'))))
                && mb_strlen($trimmed) < 120;

            if ($isHeadingRekap) {
                $flushAnswerSection();
                $state = 'recap';
                continue;
            }

            if ($state === 'top') {
                if (str_contains($upper, 'KISI-KISI') || str_contains($upper, 'KISI KISI')) {
                    $doc['title'] = $trimmed;
                    $state = 'subject';
                } else {
                    $doc['kop'][] = $trimmed;
                }
                continue;
            }

            if ($state === 'subject') {
                if (str_starts_with($upper, 'MATA PELAJARAN') || str_starts_with($upper, 'MAPEL')) {
                    $doc['subject'] = $trimmed;
                    continue;
                }
                if (str_starts_with($upper, 'TAHUN PELAJARAN') || str_starts_with($upper, 'TP ')) {
                    $doc['academic_year'] = $trimmed;
                    continue;
                }
                if (! str_contains($trimmed, ':') && ! self::isTableLine($trimmed) && ! str_starts_with($upper, 'TUJUAN')) {
                    if (empty($doc['subject'])) {
                        $doc['subject'] = $trimmed;
                    } elseif (empty($doc['academic_year'])) {
                        $doc['academic_year'] = $trimmed;
                    }
                    continue;
                }
                $state = 'identity';
            }

            if ($state === 'identity') {
                if (str_contains($upper, 'TABEL KISI') || str_contains($upper, 'KISI-KISI SOAL') || str_contains($upper, 'MATRIKS KISI')) {
                    $state = 'blueprint_table';
                    continue;
                }
                if (self::isTableLine($trimmed) && (str_contains($upper, 'INDIKATOR') || str_contains($upper, 'ELEMEN') || str_contains($upper, 'CAPAIAN') || self::looksLikeHeader(self::splitTable($trimmed)))) {
                    $state = 'blueprint_table';
                    $hdrParts = self::splitTable($trimmed);
                    if (count($hdrParts) >= 8 || str_contains($upper, 'CAPAIAN') || str_contains($upper, 'LINGKUP MATERI')) {
                        $doc['table_type'] = '10col';
                    } else {
                        $doc['table_type'] = '7col';
                    }
                    continue;
                }
                if (str_starts_with($upper, 'TUJUAN PEMBELAJARAN') || str_starts_with($upper, 'KOMPETENSI')) {
                    $state = 'competencies';
                    continue;
                }
                if (str_starts_with($upper, 'CATATAN GURU') || str_starts_with($upper, 'CATATAN:')) {
                    $doc['note'] = trim((string) preg_replace('/^Catatan(?:\s*guru)?\s*:\s*/iu', '', $trimmed));
                    $state = 'note_pre_table';
                    continue;
                }

                $identities = self::parseIdentityRows($trimmed);
                foreach ($identities as $idRow) {
                    // Jangan masukkan catatan guru atau tujuan pembelajaran ke dalam tabel identitas
                    $lblUpper = Str::upper($idRow['label']);
                    if (str_contains($lblUpper, 'CATATAN')) {
                        $doc['note'] = trim($doc['note'].' '.$idRow['value']);
                    } elseif (str_contains($lblUpper, 'TUJUAN PEMBELAJARAN') || str_contains($lblUpper, 'KOMPETENSI')) {
                        $doc['competencies'][] = $idRow['value'];
                    } else {
                        $doc['identity'][] = $idRow;
                        $key = Str::slug($idRow['label'], '_');
                        $doc['identity_map'][$key] = $idRow['value'];
                    }
                }
                continue;
            }

            if ($state === 'competencies') {
                if (str_contains($upper, 'TABEL KISI') || str_contains($upper, 'KISI-KISI SOAL') || (self::isTableLine($trimmed) && (str_contains($upper, 'INDIKATOR') || self::looksLikeHeader(self::splitTable($trimmed))))) {
                    $state = 'blueprint_table';
                    continue;
                }
                if (str_starts_with($upper, 'CATATAN GURU') || str_starts_with($upper, 'CATATAN:')) {
                    $doc['note'] = trim((string) preg_replace('/^Catatan(?:\s*guru)?\s*:\s*/iu', '', $trimmed));
                    $state = 'note_pre_table';
                    continue;
                }
                // Hapus penomoran atau bullet di awal
                $cleanComp = trim((string) preg_replace('/^\d+[\.\)]\s*|^[•\-\*]\s*/u', '', $trimmed));
                if ($cleanComp !== '') {
                    $doc['competencies'][] = $cleanComp;
                }
                continue;
            }

            if ($state === 'note_pre_table') {
                if (str_contains($upper, 'TABEL KISI') || str_contains($upper, 'KISI-KISI SOAL') || (self::isTableLine($trimmed) && (str_contains($upper, 'INDIKATOR') || self::looksLikeHeader(self::splitTable($trimmed))))) {
                    $state = 'blueprint_table';
                    continue;
                }
                $doc['note'] = trim($doc['note'].' '.$trimmed);
                continue;
            }

            if ($state === 'blueprint_table') {
                if (str_starts_with($upper, 'LEGENDA') || str_starts_with($upper, 'CATATAN') || str_starts_with($upper, 'TANDA TANGAN') || str_starts_with($upper, 'MENGETAHUI')) {
                    $state = str_starts_with($upper, 'LEGENDA') ? 'legend' : (str_starts_with($upper, 'CATATAN') ? 'note' : 'signatures');
                    if ($state === 'note') {
                        $noteText = trim((string) preg_replace('/^Catatan(?:\s*guru)?\s*:\s*/iu', '', $trimmed));
                        if ($noteText !== '') {
                            $doc['note'] = trim($doc['note'].' '.$noteText);
                        }
                    } elseif ($state === 'legend') {
                        $legendText = trim((string) preg_replace('/^Legenda(?:\s*level\s*kognitif)?\s*:\s*/iu', '', $trimmed));
                        if ($legendText !== '') {
                            $doc['legend'][] = $legendText;
                        }
                    }
                    continue;
                }

                $row = self::blueprintRow($trimmed);
                if ($row !== null) {
                    $doc['rows'][] = $row;
                    if (isset($row['cp']) || isset($row['tp']) || isset($row['kelas'])) {
                        $doc['table_type'] = '10col';
                    }
                }
                continue;
            }

            if ($state === 'legend') {
                if (str_starts_with($upper, 'CATATAN')) {
                    $state = 'note';
                    $noteText = trim((string) preg_replace('/^Catatan(?:\s*guru)?\s*:\s*/iu', '', $trimmed));
                    if ($noteText !== '') {
                        $doc['note'] = trim($doc['note'].' '.$noteText);
                    }
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
                if (preg_match('/\(([^)]+)\)/u', $trimmed, $m)) {
                    $nameInParentheses = trim($m[1]);
                    if (! empty($nameInParentheses) && ! preg_match('/^_+$|^\s*$/u', $nameInParentheses)) {
                        if (empty($doc['kepala_sekolah']) || $doc['kepala_sekolah'] === SchoolLetterhead::kepalaSekolah()) {
                            $doc['kepala_sekolah'] = $nameInParentheses;
                        } else {
                            $doc['guru_mapel'] = $nameInParentheses;
                        }
                    }
                }
                continue;
            }

            if ($state === 'answer_intro') {
                if (preg_match('/^(?:BAGIAN\s+)?([A-Z]|\d+)[\.:\)]\s+(.+)$/iu', $trimmed)) {
                    $flushAnswerSection();
                    $answerSection = ['heading' => $trimmed, 'headers' => [], 'rows' => []];
                    $state = 'answer_section';
                    continue;
                } else {
                    $doc['answer_subtitle'] = trim($doc['answer_subtitle'].' '.$trimmed);
                    continue;
                }
            }

            if ($state === 'answer_section') {
                if (preg_match('/^(?:BAGIAN\s+)?([A-Z]|\d+)[\.:\)]\s+(.+)$/iu', $trimmed)) {
                    $flushAnswerSection();
                    $answerSection = ['heading' => $trimmed, 'headers' => [], 'rows' => []];
                    continue;
                }
                if ($answerSection !== null && self::isTableLine($trimmed)) {
                    $parts = self::splitTable($trimmed);
                    if (empty($parts)) {
                        // Baris pemisah markdown ---|---|---
                        continue;
                    }
                    if ($answerSection['headers'] === [] && self::looksLikeHeader($parts)) {
                        $answerSection['headers'] = $parts;
                    } else {
                        $answerSection['rows'][] = $parts;
                    }
                    continue;
                }
                if ($answerSection !== null) {
                    $parts = self::splitTable($trimmed);
                    if (! empty($parts)) {
                        $answerSection['rows'][] = $parts;
                    }
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
                    if (empty($parts) || self::looksLikeHeader($parts)) {
                        continue;
                    }
                    // Jika kolom rekap hanya 4 (tanpa Bagian), tambahkan placeholder agar format tidak lari
                    if (count($parts) === 4 && ! preg_match('/^[A-E]$/i', $parts[0])) {
                        array_unshift($parts, '-');
                    }
                    $doc['recap'][] = $parts;
                } elseif ($doc['recap_note'] !== '') {
                    $doc['recap_note'] .= ' '.$trimmed;
                }
            }
        }

        $flushAnswerSection();

        // Fallback untuk subject dan academic_year jika belum terisi dari header
        if (empty($doc['subject']) && ! empty($doc['identity_map']['mata_pelajaran'])) {
            $doc['subject'] = 'MATA PELAJARAN ' . Str::upper($doc['identity_map']['mata_pelajaran']);
        }
        if (empty($doc['academic_year']) && ! empty($doc['identity_map']['tahun_pelajaran'])) {
            $doc['academic_year'] = 'TAHUN PELAJARAN ' . Str::upper($doc['identity_map']['tahun_pelajaran']);
        }

        // Tentukan table_type secara global
        $has10ColRow = false;
        foreach ($doc['rows'] as $r) {
            if (isset($r['cp']) || isset($r['tp']) || isset($r['kelas'])) {
                $has10ColRow = true;
                break;
            }
        }
        if ($has10ColRow) {
            $doc['table_type'] = '10col';
        }

        // Jika kop kosong, fallback ke identitas sekolah aplikasi
        if (empty($doc['kop'])) {
            $doc['kop'] = SchoolLetterhead::lines();
        }

        $doc['parsed'] = $doc['rows'] !== [] || $doc['answer_sections'] !== [] || count($doc['identity']) >= 3;

        return $doc;
    }

    /** @return list<array{label:string,value:string}> */
    private static function parseIdentityRows(string $line): array
    {
        $results = [];
        // Cek apakah ada multiple key:value di satu baris (mis. Satuan Pendidikan : X Mata Pelajaran : Y)
        $knownLabels = [
            'Jenjang Pendidikan', 'Satuan Pendidikan', 'Mata Pelajaran', 'Mata pelajaran',
            'Kurikulum', 'Kelas / Semester', 'Kelas/Semester', 'Kelas / Smt', 'Kelas',
            'Tahun Pelajaran', 'Topik / Materi', 'Materi Pokok', 'Bentuk Penilaian', 'Jenis Penilaian',
            'Jumlah Soal', 'Jumlah soal', 'Bentuk Soal', 'Bentuk soal',
            'Alokasi Waktu', 'Fase', 'Penyusun', 'Guru Mata Pelajaran',
        ];

        // Jika baris cocok format label tunggal sederhana
        if (preg_match('/^([^:]{2,50})\s*:\s*(.+)$/u', $line, $m)) {
            $label = trim($m[1]);
            $val = trim($m[2]);

            // Cek apakah di dalam $val ada label lain yang dikenal diikuti ':'
            $foundSecondary = false;
            foreach ($knownLabels as $kl) {
                if (stripos($val, $kl.' :') !== false || stripos($val, $kl.':') !== false) {
                    $foundSecondary = true;
                    break;
                }
            }

            if (! $foundSecondary) {
                return [['label' => $label, 'value' => $val]];
            }
        }

        // Pisahkan jika terdapat multiple key:value
        $pattern = '/('.implode('|', array_map('preg_quote', $knownLabels)).')\s*:\s*(.+?)(?=(?:'.implode('|', array_map('preg_quote', $knownLabels)).')\s*:|$)/iu';
        if (preg_match_all($pattern, $line, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $results[] = [
                    'label' => trim($match[1]),
                    'value' => trim($match[2]),
                ];
            }
        }

        if (empty($results) && preg_match('/^([^:]{2,80})\s*:\s*(.+)$/u', $line, $m)) {
            $results[] = ['label' => trim($m[1]), 'value' => trim($m[2])];
        }

        return $results;
    }

    private static function blueprintRow(string $line): ?array
    {
        $parts = self::splitTable($line);
        if (empty($parts) || self::looksLikeHeader($parts)) {
            return null;
        }

        // Nomor harus ada di kolom pertama
        if (! preg_match('/^\s*(?:No\.?\s*)?(\d+)/iu', $parts[0] ?? '', $m)) {
            return null;
        }
        $no = $m[1];

        // Format 10 kolom resmi:
        // NO | Capaian Pembelajaran | Lingkup Materi | Elemen | Tujuan Pembelajaran | Kelas | Indikator Soal | Level Kognitif | Nomor Soal | Bentuk Soal
        if (count($parts) >= 10) {
            return [
                'no' => $no,
                'cp' => $parts[1] ?? '',
                'material' => $parts[2] ?? '',
                'element' => $parts[3] ?? '',
                'tp' => $parts[4] ?? '',
                'kelas' => $parts[5] ?? '',
                'indicator' => $parts[6] ?? '',
                'level' => $parts[7] ?? '',
                'question_no' => $parts[8] !== '' ? $parts[8] : $no,
                'shape' => $parts[9] ?? '',
            ];
        }

        // Format 9 kolom (tanpa Kelas atau tanpa Elemen)
        if (count($parts) === 9) {
            return [
                'no' => $no,
                'cp' => $parts[1] ?? '',
                'material' => $parts[2] ?? '',
                'element' => $parts[3] ?? '',
                'tp' => $parts[4] ?? '',
                'kelas' => '-',
                'indicator' => $parts[5] ?? '',
                'level' => $parts[6] ?? '',
                'question_no' => $parts[7] !== '' ? $parts[7] : $no,
                'shape' => $parts[8] ?? '',
            ];
        }

        // Format 8 kolom:
        if (count($parts) === 8) {
            return [
                'no' => $no,
                'cp' => $parts[1] ?? '',
                'material' => $parts[2] ?? '',
                'element' => '-',
                'tp' => $parts[3] ?? '',
                'kelas' => '-',
                'indicator' => $parts[4] ?? '',
                'level' => $parts[5] ?? '',
                'question_no' => $parts[6] !== '' ? $parts[6] : $no,
                'shape' => $parts[7] ?? '',
            ];
        }

        // Format standar 7 kolom (Legacy):
        // No | Elemen / Capaian | Materi Pokok | Indikator Soal | Level Kognitif | Bentuk Soal | No. Soal
        if (count($parts) === 7) {
            return [
                'no' => $no,
                'element' => $parts[1] ?? '',
                'material' => $parts[2] ?? '',
                'indicator' => $parts[3] ?? '',
                'level' => $parts[4] ?? '',
                'shape' => $parts[5] ?? '',
                'question_no' => $parts[6] !== '' ? $parts[6] : $no,
            ];
        }

        // 6 kolom (misalnya No. Soal dihilangkan atau Elemen dihilangkan)
        if (count($parts) === 6) {
            return [
                'no' => $no,
                'element' => $parts[1] ?? '',
                'material' => $parts[2] ?? '',
                'indicator' => $parts[3] ?? '',
                'level' => $parts[4] ?? '',
                'shape' => $parts[5] ?? '',
                'question_no' => $no,
            ];
        }

        // 5 kolom (No | Materi | Indikator | Level | Bentuk)
        if (count($parts) === 5) {
            return [
                'no' => $no,
                'element' => '-',
                'material' => $parts[1] ?? '',
                'indicator' => $parts[2] ?? '',
                'level' => $parts[3] ?? '',
                'shape' => $parts[4] ?? '',
                'question_no' => $no,
            ];
        }

        return null;
    }

    private static function isTableLine(string $line): bool
    {
        return substr_count($line, '|') >= 2;
    }

    private static function splitTable(string $line): array
    {
        $trimmed = trim($line);
        if (str_starts_with($trimmed, '|')) {
            $trimmed = substr($trimmed, 1);
        }
        if (str_ends_with($trimmed, '|')) {
            $trimmed = substr($trimmed, 0, -1);
        }

        $parts = array_map('trim', explode('|', $trimmed));

        // Abaikan baris separator seperti ---|---|---
        $nonEmpty = array_filter($parts, fn (string $p) => $p !== '');
        if (! empty($nonEmpty)) {
            $isSeparator = true;
            foreach ($nonEmpty as $p) {
                if (! preg_match('/^-+$/', $p)) {
                    $isSeparator = false;
                    break;
                }
            }
            if ($isSeparator) {
                return [];
            }
        }

        return $parts;
    }

    private static function looksLikeHeader(array $parts): bool
    {
        if (empty($parts)) {
            return false;
        }

        // Jika kolom pertama adalah angka, pasti ini baris data, bukan header!
        if (preg_match('/^\d+$/', trim($parts[0]))) {
            return false;
        }

        $joined = Str::lower(implode(' ', $parts));
        $first = Str::lower(trim($parts[0]));

        $hasNoCol = in_array($first, ['no', 'no.', 'nomor', '#'], true)
            || preg_match('/^(?:no|nomor)\b/u', $first);

        return ($hasNoCol && (
            str_contains($joined, 'indikator')
            || str_contains($joined, 'kunci')
            || str_contains($joined, 'bentuk')
            || str_contains($joined, 'jumlah')
            || str_contains($joined, 'materi')
            || str_contains($joined, 'elemen')
            || str_contains($joined, 'capaian')
            || str_contains($joined, 'level')
            || str_contains($joined, 'tujuan')
        ))
            || (str_contains($joined, 'bagian') && str_contains($joined, 'bentuk') && str_contains($joined, 'jumlah'))
            || (str_contains($joined, 'istilah') && str_contains($joined, 'pasangan'));
    }
}
