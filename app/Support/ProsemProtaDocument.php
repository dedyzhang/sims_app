<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Parser dan penyusun data Program Semester (PROSEM) dan Program Tahunan (PROTA)
 * berbasis dokumen Tujuan Pembelajaran (TP) & Alur Tujuan Pembelajaran (ATP)
 * Kurikulum Merdeka SMP.
 */
final class ProsemProtaDocument
{
    /**
     * Susun struktur lengkap PROTA dan PROSEM dari dokumen TP & ATP atau data terstruktur.
     *
     * @param array|string $tpAtpInput Teks dokumen TP/ATP atau array hasil TpAtpDocument::parse() atau teks unified Prosem/Prota
     * @param array $options Opsi penyesuaian (kelas, semester, jp_per_week, sumatif_jp, cadangan_jp, dll.)
     */
    public static function buildFromTpAtp(array|string $tpAtpInput, array $options = []): array
    {
        // 1. Jika input array sudah merupakan hasil build Prosem & Prota terstruktur
        if (is_array($tpAtpInput) && isset($tpAtpInput['prota'], $tpAtpInput['prosem']) && ! empty($tpAtpInput['is_valid'])) {
            if (empty($options['kelas']) && empty($options['jp_per_week']) && empty($options['sumatif_jp']) && empty($options['cadangan_jp_sem1']) && empty($options['cadangan_jp_sem2'])) {
                return $tpAtpInput;
            }
            if (! empty($tpAtpInput['text'])) {
                return self::buildFromUnifiedText($tpAtpInput['text'], $options);
            }
        }

        // 2. Jika input string adalah teks unified Prosem & Prota (misal hasil generate atau simpanan history)
        if (is_string($tpAtpInput) && self::isUnifiedProsemProtaText($tpAtpInput)) {
            return self::buildFromUnifiedText($tpAtpInput, $options);
        }

        // 3. Standar: parsing dari dokumen mentah TP & ATP
        $tpDoc = is_array($tpAtpInput) ? $tpAtpInput : TpAtpDocument::parse($tpAtpInput);

        $meta = array_merge(
            $tpDoc['identity'] ?? [],
            $tpDoc['signature'] ?? [],
            $tpDoc['meta'] ?? []
        );

        if (empty($meta['guru']) && ! empty($tpDoc['signature']['name'])) {
            $meta['guru'] = $tpDoc['signature']['name'];
        }
        if (empty($meta['nip_guru']) && ! empty($tpDoc['signature']['nip'])) {
            $meta['nip_guru'] = $tpDoc['signature']['nip'];
        }
        if (empty($meta['tempat_tanggal']) && ! empty($tpDoc['signature']['place_date'])) {
            $meta['tempat_tanggal'] = $tpDoc['signature']['place_date'];
        }

        $resolvedSignature = self::resolveSignature(array_merge($meta, $options['signature'] ?? [], $options));
        $meta = array_merge($meta, $resolvedSignature);

        $mapel = (string) ($options['mapel'] ?? $meta['mapel'] ?? 'Mata Pelajaran');
        $faseKelas = (string) ($options['fase_kelas'] ?? $meta['fase_kelas'] ?? 'D/VII-IX');
        $tahun = (string) ($options['tahun'] ?? $meta['tahun'] ?? '2026/2027');

        // Normalisasi format tahun (contoh "2026-2027" -> "2026/2027")
        if (preg_match('/^(\d{4})[-_](\d{4})$/', $tahun, $m)) {
            $tahun = "{$m[1]}/{$m[2]}";
        }

        // Tentukan target kelas (default Kelas VII jika tidak ditentukan)
        $kelasRaw = (string) ($options['kelas'] ?? '');
        $kelas = self::normalizeKelas($kelasRaw ?: $faseKelas);

        $jpPerWeek = max(1, (int) ($options['jp_per_week'] ?? 3));
        $sumatifJp = max(1, (int) ($options['sumatif_jp'] ?? 2));
        $cadanganJpSem1 = max(0, (int) ($options['cadangan_jp_sem1'] ?? 0));
        $cadanganJpSem2 = max(0, (int) ($options['cadangan_jp_sem2'] ?? 3));

        // Ekstraksi bab & butir TP yang relevan dengan kelas terpilih
        $extracted = self::extractBabsAndTps($tpDoc, $kelas, $sumatifJp);
        $elements = $extracted['elements'];
        $babs = $extracted['babs'];

        // Bagi bab ke Semester 1 dan Semester 2
        $semesters = self::splitBabsBySemester($babs, $sumatifJp, $cadanganJpSem1, $cadanganJpSem2);

        // Susun dokumen PROTA
        $prota = self::buildProtaStructure([
            'mapel' => $mapel,
            'kelas' => $kelas,
            'tahun' => $tahun,
            'meta' => $meta,
            'elements' => $elements,
            'semesters' => $semesters,
        ]);

        // Susun dokumen PROSEM (Ganjil & Genap)
        $prosemGanjil = self::buildProsemSemesterStructure('ganjil', [
            'mapel' => $mapel,
            'kelas' => $kelas,
            'tahun' => $tahun,
            'meta' => $meta,
            'babs' => $semesters['semester_1']['babs'],
            'sumatif_jp' => $sumatifJp,
            'cadangan_jp' => $cadanganJpSem1,
            'jp_per_week' => $jpPerWeek,
            'calendar_events' => $options['calendar_events_ganjil'] ?? null,
        ]);

        $prosemGenap = self::buildProsemSemesterStructure('genap', [
            'mapel' => $mapel,
            'kelas' => $kelas,
            'tahun' => $tahun,
            'meta' => $meta,
            'babs' => $semesters['semester_2']['babs'],
            'sumatif_jp' => $sumatifJp,
            'cadangan_jp' => $cadanganJpSem2,
            'jp_per_week' => $jpPerWeek,
            'calendar_events' => $options['calendar_events_genap'] ?? null,
        ]);

        $unified = [
            'is_valid' => ! empty($babs),
            'meta' => array_merge($meta, [
                'mapel' => $mapel,
                'kelas' => $kelas,
                'tahun' => $tahun,
                'jp_per_week' => $jpPerWeek,
                'sumatif_jp' => $sumatifJp,
                'calendar_events_ganjil' => $prosemGanjil['calendar_events'],
                'calendar_events_genap' => $prosemGenap['calendar_events'],
            ]),
            'prota' => $prota,
            'prosem' => [
                'ganjil' => $prosemGanjil,
                'genap' => $prosemGenap,
            ],
            'raw_tpatp_meta' => $tpDoc['meta'] ?? [],
        ];

        $unified['text'] = self::toUnifiedText($unified);

        return $unified;
    }

    /**
     * Konversi data terstruktur PROTA dan PROSEM menjadi format teks markdown formal.
     */
    public static function toUnifiedText(array $built): string
    {
        $meta = $built['meta'];
        $prota = $built['prota'];
        $prosemG = $built['prosem']['ganjil'];
        $prosemE = $built['prosem']['genap'];

        $out = [];
        $out[] = "PROGRAM TAHUNAN & PROGRAM SEMESTER";
        $out[] = "MATA PELAJARAN : {$meta['mapel']}";
        $out[] = "KELAS : {$meta['kelas']}";
        $out[] = "TAHUN PELAJARAN : {$meta['tahun']}";
        if (! empty($prota['primary_elemen'])) {
            $out[] = "ELEMEN : {$prota['primary_elemen']}";
        }
        if (! empty($prota['primary_cp'])) {
            $out[] = "CAPAIAN PEMBELAJARAN : {$prota['primary_cp']}";
        }
        $out[] = "";
        $out[] = "=== 1. PROGRAM TAHUNAN (PROTA) ===";
        $out[] = "ELEMEN | CAPAIAN PEMBELAJARAN | TUJUAN PEMBELAJARAN (TP) | KODE TP | ALOKASI WAKTU | KETERANGAN";

        foreach (['semester_1', 'semester_2'] as $sKey) {
            $sem = $prota['semesters'][$sKey];
            $out[] = "--- {$sem['label']} ---";
            foreach ($sem['babs'] as $b) {
                $out[] = "[{$b['bab_title']}] | Alokasi: {$b['alokasi_jp']} JP";
                foreach ($b['tps'] as $t) {
                    $out[] = "{$t['kode']} | {$t['tp']}";
                }
                $sumatifVal = $b['sumatif_jp'] ?? $sem['sumatif_jp_per_bab'] ?? 2;
                $out[] = "PENILAIAN SUMATIF | {$sumatifVal} JP";
            }
            if ($sem['cadangan_jp'] > 0) {
                $out[] = "CADANGAN | {$sem['cadangan_jp']} JP";
            }
            $out[] = "TOTAL ALOKASI WAKTU {$sem['label']} : {$sem['total_jp']} JP";
        }

        $out[] = "";
        $out[] = "=== 2. PROGRAM SEMESTER GANJIL (PROSEM) ===";
        $out[] = "Total Alokasi Waktu: {$prosemG['total_jp']} JP ({$prosemG['jp_per_week']} JP/Minggu)";
        foreach ($prosemG['babs'] as $b) {
            $tpAlokasi = $b['tp_alokasi_jp'] ?? max(1, $b['alokasi_jp'] - ($b['sumatif_jp'] ?? 2));
            $sumatifVal = $b['sumatif_jp'] ?? 2;
            $out[] = "[{$b['bab_title']}] ({$b['alokasi_jp']} JP: TP {$tpAlokasi} JP, Sumatif {$sumatifVal} JP)";
            foreach ($b['tps'] as $t) {
                $wStr = [];
                foreach ($t['weeks'] as $cIdx => $jp) {
                    $wInfo = $prosemG['week_columns'][$cIdx] ?? null;
                    if ($wInfo) {
                        $wStr[] = "{$wInfo['month']} M{$wInfo['week_num']}: {$jp}JP";
                    }
                }
                $out[] = "- {$t['kode']} ({$t['target_jp']} JP): ".implode(', ', $wStr);
            }
            if (! empty($b['sumatif_weeks'])) {
                $wStr = [];
                foreach ($b['sumatif_weeks'] as $cIdx => $jp) {
                    $wInfo = $prosemG['week_columns'][$cIdx] ?? null;
                    if ($wInfo) {
                        $wStr[] = "{$wInfo['month']} M{$wInfo['week_num']}: {$jp}JP";
                    }
                }
                $out[] = "- PENILAIAN SUMATIF ({$sumatifVal} JP): ".implode(', ', $wStr);
            }
        }

        $out[] = "";
        $out[] = "=== 3. PROGRAM SEMESTER GENAP (PROSEM) ===";
        $out[] = "Total Alokasi Waktu: {$prosemE['total_jp']} JP ({$prosemE['jp_per_week']} JP/Minggu)";
        foreach ($prosemE['babs'] as $b) {
            $tpAlokasi = $b['tp_alokasi_jp'] ?? max(1, $b['alokasi_jp'] - ($b['sumatif_jp'] ?? 2));
            $sumatifVal = $b['sumatif_jp'] ?? 2;
            $out[] = "[{$b['bab_title']}] ({$b['alokasi_jp']} JP: TP {$tpAlokasi} JP, Sumatif {$sumatifVal} JP)";
            foreach ($b['tps'] as $t) {
                $wStr = [];
                foreach ($t['weeks'] as $cIdx => $jp) {
                    $wInfo = $prosemE['week_columns'][$cIdx] ?? null;
                    if ($wInfo) {
                        $wStr[] = "{$wInfo['month']} M{$wInfo['week_num']}: {$jp}JP";
                    }
                }
                $out[] = "- {$t['kode']} ({$t['target_jp']} JP): ".implode(', ', $wStr);
            }
            if (! empty($b['sumatif_weeks'])) {
                $wStr = [];
                foreach ($b['sumatif_weeks'] as $cIdx => $jp) {
                    $wInfo = $prosemE['week_columns'][$cIdx] ?? null;
                    if ($wInfo) {
                        $wStr[] = "{$wInfo['month']} M{$wInfo['week_num']}: {$jp}JP";
                    }
                }
                $out[] = "- PENILAIAN SUMATIF ({$sumatifVal} JP): ".implode(', ', $wStr);
            }
        }

        $sig = self::resolveSignature($prota['signature'] ?? []);
        if (! empty($sig)) {
            $out[] = "";
            $out[] = "TANDA TANGAN:";
            $out[] = $sig['tempat_tanggal'];
            $out[] = "Mengetahui, | Guru Mata Pelajaran";
            $out[] = $sig['jabatan_kepala']." | ";
            $out[] = $sig['kepala_sekolah']." | ".$sig['guru'];
            $out[] = "NIK. ".$sig['nip_kepala']." | NIK. ".$sig['nip_guru'];
        }

        return implode("\n", $out);
    }

    /**
     * Periksa apakah suatu teks merupakan teks serialisasi unified Prosem & Prota.
     */
    public static function isUnifiedProsemProtaText(string $content): bool
    {
        return str_contains($content, '=== 1. PROGRAM TAHUNAN (PROTA) ===')
            || str_contains($content, 'PROGRAM TAHUNAN & PROGRAM SEMESTER')
            || (str_contains($content, 'PROGRAM TAHUNAN') && str_contains($content, 'PROGRAM SEMESTER'));
    }

    /**
     * Parse teks serialisasi unified Prosem & Prota menjadi array terstruktur.
     */
    public static function parseUnifiedText(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];
        $mapel = 'Mata Pelajaran';
        $kelas = 'VII';
        $tahun = '2026/2027';
        $jpPerWeek = 3;
        $sumatifJp = 2;
        $cadanganSem1 = 0;
        $cadanganSem2 = 3;
        $primaryElemen = 'Pemahaman Konsep';
        $primaryCp = '';

        $currentSemester = 'SEMESTER I';
        $babs = [];
        $currentBab = null;
        $babCounter = 0;
        $inProtaSection = false;

        $signature = self::resolveSignature();

        $inSignature = false;
        $sigLineIdx = 0;

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            if ($inSignature) {
                if ($sigLineIdx === 0 && ! str_contains($trimmed, '|')) {
                    if (! self::isPlaceholder($trimmed)) {
                        $signature['tempat_tanggal'] = $trimmed;
                    }
                    $sigLineIdx++;
                    continue;
                }
                if (preg_match('/^([^|]+)\s*\|\s*(.*)$/', $trimmed, $sm)) {
                    $left = trim($sm[1]);
                    $right = trim($sm[2]);
                    if (str_starts_with($left, 'NIK.')) {
                        $parsedNikKepala = trim(substr($left, 4));
                        $parsedNikGuru = trim(str_replace('NIK.', '', $right));
                        if (! self::isPlaceholder($parsedNikKepala)) {
                            $signature['nip_kepala'] = $parsedNikKepala;
                        }
                        if (! self::isPlaceholder($parsedNikGuru)) {
                            $signature['nip_guru'] = $parsedNikGuru;
                        }
                    } elseif (! str_contains($left, 'Mengetahui') && ! str_starts_with(strtoupper($left), 'KEPALA ')) {
                        if (! self::isPlaceholder($left)) {
                            $signature['kepala_sekolah'] = $left;
                        }
                        if (! self::isPlaceholder($right)) {
                            $signature['guru'] = $right;
                        }
                    } elseif (str_starts_with(strtoupper($left), 'KEPALA ')) {
                        $signature['jabatan_kepala'] = $left;
                    }
                }
                $sigLineIdx++;
                continue;
            }

            if (preg_match('/^TANDA\s+TANGAN\s*:/i', $trimmed)) {
                $inSignature = true;
                $sigLineIdx = 0;
                continue;
            }

            if (preg_match('/^MATA\s+PELAJARAN\s*:\s*(.+)$/i', $trimmed, $m)) {
                $mapel = trim($m[1]);
                continue;
            }
            if (preg_match('/^KELAS\s*:\s*(.+)$/i', $trimmed, $m)) {
                $kelas = trim($m[1]);
                continue;
            }
            if (preg_match('/^TAHUN\s+PELAJARAN\s*:\s*(.+)$/i', $trimmed, $m)) {
                $tahun = trim($m[1]);
                continue;
            }
            if (preg_match('/^ELEMEN\s*:\s*(.+)$/i', $trimmed, $m)) {
                $primaryElemen = trim($m[1]);
                continue;
            }
            if (preg_match('/^CAPAIAN\s+PEMBELAJARAN\s*:\s*(.+)$/i', $trimmed, $m)) {
                $primaryCp = trim($m[1]);
                continue;
            }
            if (preg_match('/(\d+)\s*JP\/Minggu/i', $trimmed, $m)) {
                $jpPerWeek = (int) $m[1];
                continue;
            }

            if (str_contains($trimmed, '=== 1. PROGRAM TAHUNAN')) {
                $inProtaSection = true;
                continue;
            }

            if (str_contains($trimmed, '=== 2. PROGRAM SEMESTER') || str_contains($trimmed, '=== 3. PROGRAM SEMESTER')) {
                // Saat mencapai bagian Prosem, simpan bab terakhir dan nonaktifkan pengumpulan bab dari Prota
                if ($inProtaSection && $currentBab !== null) {
                    $babs[] = $currentBab;
                    $currentBab = null;
                }
                $inProtaSection = false;
                continue;
            }

            if (! $inProtaSection) {
                continue;
            }

            if (preg_match('/^---\s*(SEMESTER\s+(?:I|1|GANJIL))\s*---/i', $trimmed)) {
                $currentSemester = 'SEMESTER I';
                continue;
            }
            if (preg_match('/^---\s*(SEMESTER\s+(?:II|2|GENAP))\s*---/i', $trimmed)) {
                $currentSemester = 'SEMESTER II';
                continue;
            }

            // Bab format 1: [BAB 1 : Nama Bab] | Alokasi: 27 JP
            if (preg_match('/^\[(.*?)\](?:\s*\|\s*Alokasi:\s*(\d+)\s*JP)?/iu', $trimmed, $m)) {
                if ($currentBab !== null) {
                    $babs[] = $currentBab;
                }
                $babCounter++;
                $rawTitle = trim($m[1]);
                $alokasiJp = ! empty($m[2]) ? (int) $m[2] : 27;
                $babTitle = self::formatBabTitle($rawTitle, $babCounter);
                $materiRaw = preg_replace('/^BAB\s+\d+\s*[:\-]\s*/ui', '', $rawTitle);
                $tpAlokasi = max(1, $alokasiJp - $sumatifJp);

                $currentBab = [
                    'bab_no' => $babCounter,
                    'bab_title' => $babTitle,
                    'materi_raw' => $materiRaw,
                    'alokasi_jp' => $alokasiJp,
                    'tp_alokasi_jp' => $tpAlokasi,
                    'tps' => [],
                    'sumatif_jp' => $sumatifJp,
                    'semester' => $currentSemester,
                    'elemen' => $primaryElemen,
                    'cp' => $primaryCp,
                ];
                continue;
            }

            // Bab format 2: BAB 1 : Nama Bab | Alokasi: 27 JP
            if (preg_match('/^(?:###\s*)?BAB\s*(\d+)\s*[:\-]\s*([^\|]+)(?:\s*\|\s*Alokasi:\s*(\d+)\s*JP)?/iu', $trimmed, $m)) {
                if ($currentBab !== null) {
                    $babs[] = $currentBab;
                }
                $babCounter++;
                $bNum = (int) $m[1];
                $materiRaw = trim($m[2]);
                $alokasiJp = ! empty($m[3]) ? (int) $m[3] : 27;
                $babTitle = "BAB {$bNum} : {$materiRaw}";
                $tpAlokasi = max(1, $alokasiJp - $sumatifJp);

                $currentBab = [
                    'bab_no' => $bNum,
                    'bab_title' => $babTitle,
                    'materi_raw' => $materiRaw,
                    'alokasi_jp' => $alokasiJp,
                    'tp_alokasi_jp' => $tpAlokasi,
                    'tps' => [],
                    'sumatif_jp' => $sumatifJp,
                    'semester' => $currentSemester,
                    'elemen' => $primaryElemen,
                    'cp' => $primaryCp,
                ];
                continue;
            }

            if (preg_match('/^PENILAIAN\s+SUMATIF\s*\|\s*(\d+)\s*JP/i', $trimmed, $m)) {
                if ($currentBab !== null) {
                    $sVal = (int) $m[1];
                    $currentBab['sumatif_jp'] = $sVal;
                    $currentBab['tp_alokasi_jp'] = max(1, $currentBab['alokasi_jp'] - $sVal);
                }
                continue;
            }

            if (preg_match('/^CADANGAN\s*\|\s*(\d+)\s*JP/i', $trimmed, $m)) {
                $cVal = (int) $m[1];
                if ($currentSemester === 'SEMESTER I') {
                    $cadanganSem1 = $cVal;
                } else {
                    $cadanganSem2 = $cVal;
                }
                continue;
            }

            if (str_contains($trimmed, 'TOTAL ALOKASI WAKTU')
                || str_contains($trimmed, 'ELEMEN | CAPAIAN')
                || str_contains($trimmed, 'Total Alokasi Waktu:')) {
                continue;
            }

            // TP baris: PK.7.1 | Menjelaskan... atau - PK.7.1 : Menjelaskan...
            if (preg_match('/^(?:[\*\-\s]+)?([A-Za-z0-9\.\-\_]+)\s*[:\|]\s*(.+)$/u', $trimmed, $m)) {
                $kode = trim($m[1]);
                $tpDesc = trim($m[2]);

                if ($currentBab !== null) {
                    $currentBab['tps'][] = [
                        'kode' => $kode,
                        'tp' => $tpDesc,
                        'jp' => 0,
                        'semester' => $currentSemester,
                    ];
                }
                continue;
            }
        }

        if ($currentBab !== null) {
            $babs[] = $currentBab;
        }

        return [
            'meta' => [
                'mapel' => $mapel,
                'kelas' => $kelas,
                'tahun' => $tahun,
                'jp_per_week' => $jpPerWeek,
                'sumatif_jp' => $sumatifJp,
                'cadangan_jp_sem1' => $cadanganSem1,
                'cadangan_jp_sem2' => $cadanganSem2,
                'primary_elemen' => $primaryElemen,
                'primary_cp' => $primaryCp,
            ],
            'babs' => $babs,
            'signature' => $signature,
        ];
    }

    /**
     * Bangun struktur Prosem & Prota lengkap dari teks serialisasi unified.
     */
    public static function buildFromUnifiedText(string $text, array $options = []): array
    {
        $parsed = self::parseUnifiedText($text);
        $meta = $parsed['meta'];
        $signature = $parsed['signature'];
        $babs = $parsed['babs'];

        $mapel = (string) ($options['mapel'] ?? $meta['mapel'] ?? 'Mata Pelajaran');
        $kelasRaw = (string) ($options['kelas'] ?? $meta['kelas'] ?? 'VII');
        $kelas = self::normalizeKelas($kelasRaw);
        $tahun = (string) ($options['tahun'] ?? $meta['tahun'] ?? '2026/2027');

        if (preg_match('/^(\d{4})[-_](\d{4})$/', $tahun, $m)) {
            $tahun = "{$m[1]}/{$m[2]}";
        }

        $jpPerWeek = max(1, (int) ($options['jp_per_week'] ?? $meta['jp_per_week'] ?? 3));
        $sumatifJp = max(1, (int) ($options['sumatif_jp'] ?? $meta['sumatif_jp'] ?? 2));
        $cadanganJpSem1 = max(0, (int) ($options['cadangan_jp_sem1'] ?? $meta['cadangan_jp_sem1'] ?? 0));
        $cadanganJpSem2 = max(0, (int) ($options['cadangan_jp_sem2'] ?? $meta['cadangan_jp_sem2'] ?? 3));

        // Update sumatif_jp & hitung sisa jam untuk TP (tp_alokasi_jp)
        if (isset($options['sumatif_jp'])) {
            foreach ($babs as &$b) {
                $b['sumatif_jp'] = $sumatifJp;
                $b['tp_alokasi_jp'] = max(1, $b['alokasi_jp'] - $sumatifJp);
            }
            unset($b);
        } else {
            foreach ($babs as &$b) {
                if (! isset($b['tp_alokasi_jp'])) {
                    $b['tp_alokasi_jp'] = max(1, $b['alokasi_jp'] - ($b['sumatif_jp'] ?? $sumatifJp));
                }
            }
            unset($b);
        }

        $semesters = self::splitBabsBySemester($babs, $sumatifJp, $cadanganJpSem1, $cadanganJpSem2);

        $resolvedSignature = self::resolveSignature(array_merge($signature, $options['signature'] ?? [], $options));

        $mergedMeta = array_merge($signature, $resolvedSignature, [
            'mapel' => $mapel,
            'kelas' => $kelas,
            'tahun' => $tahun,
            'jp_per_week' => $jpPerWeek,
            'sumatif_jp' => $sumatifJp,
            'cadangan_jp_sem1' => $cadanganJpSem1,
            'cadangan_jp_sem2' => $cadanganJpSem2,
            'primary_elemen' => $meta['primary_elemen'] ?? 'Pemahaman Konsep',
            'primary_cp' => $meta['primary_cp'] ?? '',
        ]);

        $prota = self::buildProtaStructure([
            'mapel' => $mapel,
            'kelas' => $kelas,
            'tahun' => $tahun,
            'meta' => $mergedMeta,
            'elements' => [
                [
                    'nama' => $meta['primary_elemen'] ?: 'Pemahaman Konsep',
                    'cp' => $meta['primary_cp'] ?: '',
                    'babs' => $babs,
                ],
            ],
            'semesters' => $semesters,
        ]);

        $prosemGanjil = self::buildProsemSemesterStructure('ganjil', [
            'mapel' => $mapel,
            'kelas' => $kelas,
            'tahun' => $tahun,
            'meta' => $mergedMeta,
            'babs' => $semesters['semester_1']['babs'],
            'sumatif_jp' => $sumatifJp,
            'cadangan_jp' => $cadanganJpSem1,
            'jp_per_week' => $jpPerWeek,
            'calendar_events' => $options['calendar_events_ganjil'] ?? null,
        ]);

        $prosemGenap = self::buildProsemSemesterStructure('genap', [
            'mapel' => $mapel,
            'kelas' => $kelas,
            'tahun' => $tahun,
            'meta' => $mergedMeta,
            'babs' => $semesters['semester_2']['babs'],
            'sumatif_jp' => $sumatifJp,
            'cadangan_jp' => $cadanganJpSem2,
            'jp_per_week' => $jpPerWeek,
            'calendar_events' => $options['calendar_events_genap'] ?? null,
        ]);

        $unified = [
            'is_valid' => ! empty($babs),
            'meta' => array_merge($mergedMeta, [
                'calendar_events_ganjil' => $prosemGanjil['calendar_events'],
                'calendar_events_genap' => $prosemGenap['calendar_events'],
            ]),
            'prota' => $prota,
            'prosem' => [
                'ganjil' => $prosemGanjil,
                'genap' => $prosemGenap,
            ],
            'raw_tpatp_meta' => [],
        ];

        $unified['text'] = self::toUnifiedText($unified);

        return $unified;
    }

    /**
     * Normalisasi string kelas menjadi format Romawi formal (VII, VIII, IX).
     */
    public static function normalizeKelas(string $raw): string
    {
        $u = Str::upper($raw);
        if (str_contains($u, 'VII') || str_contains($u, '7')) {
            return 'VII';
        }
        if (str_contains($u, 'VIII') || str_contains($u, '8')) {
            return 'VIII';
        }
        if (str_contains($u, 'IX') || str_contains($u, '9')) {
            return 'IX';
        }

        return 'VII';
    }

    /**
     * Konversi Romawi ke angka digit.
     */
    public static function kelasToDigit(string $kelas): int
    {
        return match (Str::upper($kelas)) {
            'VIII' => 8,
            'IX' => 9,
            default => 7,
        };
    }

    /**
     * Ekstraksi bab dan TP dari elemen dokumen TP & ATP yang sesuai dengan kelas target.
     */
    private static function extractBabsAndTps(array $tpDoc, string $kelas, int $sumatifJp = 2): array
    {
        $targetDigit = self::kelasToDigit($kelas);
        $elements = [];
        $babs = [];
        $babCounter = 1;

        $rawElements = $tpDoc['tp_table']['elements'] ?? $tpDoc['elements'] ?? [];

        foreach ($rawElements as $elIdx => $el) {
            $elNama = trim((string) ($el['nama'] ?? 'Pemahaman Konsep'));
            $elCp = trim((string) ($el['cp'] ?? ''));

            $filteredMateri = [];

            foreach ($el['materi_groups'] ?? [] as $mGroup) {
                $matGrade = TpAtpDocument::detectMateriGrade($mGroup);

                // Jika terdeteksi grade lain yang tidak sesuai dengan target digit, lewati (jika dokumen 3 tingkat)
                if ($matGrade !== null && $matGrade !== $targetDigit && count($rawElements) > 0) {
                    // Cek apakah ada materi yang cocok dengan grade ini di dokumen
                    $hasTargetGradeInDoc = false;
                    foreach ($rawElements as $re) {
                        foreach ($re['materi_groups'] ?? [] as $rm) {
                            if (TpAtpDocument::detectMateriGrade($rm) === $targetDigit) {
                                $hasTargetGradeInDoc = true;
                                break 2;
                            }
                        }
                    }
                    if ($hasTargetGradeInDoc) {
                        continue;
                    }
                }

                $rawMateriNama = trim((string) ($mGroup['nama'] ?? ''));
                // Buat judul Bab formal: "BAB X : [Nama Lingkup Materi]"
                $babTitle = self::formatBabTitle($rawMateriNama, $babCounter);

                $tps = [];
                foreach ($mGroup['tps'] ?? [] as $tp) {
                    $kode = trim((string) ($tp['kode'] ?? ''));
                    // Jika kode kosong, buat kode otomatis
                    if ($kode === '' || $kode === '-') {
                        $prefix = Str::upper(substr(preg_replace('/[^a-zA-Z]/', '', $elNama), 0, 2)) ?: 'TP';
                        $kode = "{$prefix}.{$targetDigit}.".(count($tps) + 1);
                    }

                    $tps[] = [
                        'kode' => $kode,
                        'tp' => trim((string) ($tp['tp'] ?? '')),
                        'jp' => (int) ($tp['jp'] ?? 0),
                        'semester' => (string) ($tp['semester'] ?? ''),
                    ];
                }

                $totalJp = 0;
                if (isset($mGroup['alokasi_jp']) && (int) $mGroup['alokasi_jp'] > 0) {
                    $totalJp = (int) $mGroup['alokasi_jp'];
                } elseif (isset($mGroup['jp']) && preg_match('/(\d+)/', (string) $mGroup['jp'], $jm)) {
                    $totalJp = (int) $jm[1];
                }

                if ($totalJp <= 0) {
                    $totalJp = array_sum(array_column($tps, 'jp')) ?: 27;
                }

                // Sisa jam untuk tujuan pembelajaran: total jam ruang lingkup dikurangi jam sumatif
                $tpAlokasiJp = max(1, $totalJp - $sumatifJp);

                // Wariskan semester grup jika TP belum memilikinya
                $mGroupSem = (string) ($mGroup['semester'] ?? '');
                foreach ($tps as &$tpRef) {
                    if (empty($tpRef['semester']) && ! empty($mGroupSem)) {
                        $tpRef['semester'] = $mGroupSem;
                    }
                }
                unset($tpRef);

                $babItem = [
                    'bab_no' => $babCounter,
                    'bab_title' => $babTitle,
                    'materi_raw' => $rawMateriNama,
                    'alokasi_jp' => $totalJp,
                    'tp_alokasi_jp' => $tpAlokasiJp,
                    'sumatif_jp' => $sumatifJp,
                    'tps' => $tps,
                    'elemen' => $elNama,
                    'cp' => $elCp,
                ];

                $filteredMateri[] = $babItem;
                $babs[] = $babItem;
                $babCounter++;
            }

            if (! empty($filteredMateri)) {
                $elements[] = [
                    'nama' => $elNama,
                    'cp' => $elCp,
                    'babs' => $filteredMateri,
                ];
            }
        }

        // Fallback jika tidak ada materi dari elements: sintesis dari summary rows jika ada
        $summaryRows = $tpDoc['atp_diagram']['summary_rows'] ?? $tpDoc['atpSummaryRows'] ?? [];
        if (empty($babs) && ! empty($summaryRows)) {
            $babs = self::synthesizeBabsFromAtpSummary($summaryRows, $targetDigit);
            if (! empty($babs)) {
                $elements[] = [
                    'nama' => 'Pemahaman Konsep',
                    'cp' => $tpDoc['meta']['cp'] ?? '',
                    'babs' => $babs,
                ];
            }
        }

        return [
            'elements' => $elements,
            'babs' => $babs,
        ];
    }

    /**
     * Format nama bab menjadi "BAB X : [Nama Materi]".
     */
    private static function formatBabTitle(string $rawMateri, int $counter): string
    {
        if (preg_match('/^BAB\s+\d+\s*[:\-]\s*(.*)$/ui', $rawMateri, $m)) {
            return "BAB {$counter} : ".trim($m[1]);
        }
        if (preg_match('/^BAB\s+\d+\b/ui', $rawMateri)) {
            return $rawMateri;
        }

        return "BAB {$counter} : {$rawMateri}";
    }

    /**
     * Bagi bab ke Semester 1 dan Semester 2.
     */
    private static function splitBabsBySemester(array $babs, int $sumatifJp, int $cadanganSem1, int $cadanganSem2): array
    {
        $totalBabs = count($babs);
        $sem1Babs = [];
        $sem2Babs = [];

        // Periksa apakah bab sudah memiliki penanda semester eksplisit
        $hasExplicitSemester = false;
        foreach ($babs as $b) {
            foreach ($b['tps'] as $tp) {
                $s = Str::lower($tp['semester'] ?? '');
                if (str_contains($s, 'ganjil') || str_contains($s, 'genap') || str_contains($s, '1') || str_contains($s, '2')) {
                    $hasExplicitSemester = true;
                    break 2;
                }
            }
        }

        if ($hasExplicitSemester) {
            foreach ($babs as $b) {
                $firstTpSem = Str::lower($b['tps'][0]['semester'] ?? '');
                if (str_contains($firstTpSem, 'genap') || str_contains($firstTpSem, '2')) {
                    $sem2Babs[] = $b;
                } else {
                    $sem1Babs[] = $b;
                }
            }
        } else {
            // Jika tidak ada label semester, bagi proporsional (50:50)
            $mid = (int) ceil($totalBabs / 2);
            $sem1Babs = array_slice($babs, 0, $mid);
            $sem2Babs = array_slice($babs, $mid);
        }

        // Pastikan tidak ada semester yang kosong jika total bab >= 2
        if (empty($sem1Babs) && ! empty($sem2Babs)) {
            $sem1Babs[] = array_shift($sem2Babs);
        } elseif (empty($sem2Babs) && count($sem1Babs) > 1) {
            $sem2Babs[] = array_pop($sem1Babs);
        }

        // Hitung total JP per semester (alokasi_jp per bab sudah mencakup tp_alokasi_jp + sumatif_jp)
        $calcSemTotal = function (array $semesterBabs, int $cadangan) {
            $materiJp = array_sum(array_column($semesterBabs, 'alokasi_jp'));

            return $materiJp + $cadangan;
        };

        return [
            'semester_1' => [
                'label' => 'SEMESTER I',
                'semester_name' => 'Ganjil',
                'babs' => $sem1Babs,
                'sumatif_jp_per_bab' => $sumatifJp,
                'cadangan_jp' => $cadanganSem1,
                'total_jp' => $calcSemTotal($sem1Babs, $cadanganSem1),
            ],
            'semester_2' => [
                'label' => 'SEMESTER II',
                'semester_name' => 'Genap',
                'babs' => $sem2Babs,
                'sumatif_jp_per_bab' => $sumatifJp,
                'cadangan_jp' => $cadanganSem2,
                'total_jp' => $calcSemTotal($sem2Babs, $cadanganSem2),
            ],
        ];
    }

    /**
     * Susun array data untuk tabel PROTA.
     */
    private static function buildProtaStructure(array $data): array
    {
        $meta = $data['meta'];
        $mapel = $data['mapel'];
        $kelas = $data['kelas'];
        $tahun = $data['tahun'];
        $semesters = $data['semesters'];

        // Tentukan apakah Elemen dan CP di-rowspan sepanjang seluruh tabel (seperti di format acuan)
        $uniqueElements = [];
        foreach ($data['elements'] as $el) {
            $k = trim($el['nama']).'|'.trim($el['cp']);
            $uniqueElements[$k] = $el;
        }

        $isSingleElemenCp = count($uniqueElements) <= 1;
        $primaryElemen = ! empty($uniqueElements) ? reset($uniqueElements)['nama'] : 'Pemahaman Konsep';
        $primaryCp = ! empty($uniqueElements) ? reset($uniqueElements)['cp'] : '';

        // Hitung total baris data untuk rowspan
        $countRowsInSemester = function (array $sem) {
            $rows = 1; // Separator "SEMESTER X"
            foreach ($sem['babs'] as $b) {
                $rows += 1; // Baris header BAB
                $rows += count($b['tps']); // Baris butir TP
                $rows += 1; // Baris PENILAIAN SUMATIF
            }
            if ($sem['cadangan_jp'] > 0) {
                $rows += 1; // Baris CADANGAN
            }
            $rows += 1; // Baris TOTAL ALOKASI WAKTU

            return $rows;
        };

        $sem1Rows = $countRowsInSemester($semesters['semester_1']);
        $sem2Rows = $countRowsInSemester($semesters['semester_2']);
        $totalTableRows = $sem1Rows + $sem2Rows;

        $signature = self::resolveSignature($meta);

        return [
            'title' => 'PROGRAM TAHUNAN',
            'kelas' => "KELAS {$kelas}",
            'mapel' => $mapel,
            'tahun' => $tahun,
            'is_single_elemen_cp' => $isSingleElemenCp,
            'primary_elemen' => $primaryElemen,
            'primary_cp' => $primaryCp,
            'total_table_rows' => $totalTableRows,
            'semesters' => $semesters,
            'signature' => $signature,
        ];
    }

    /**
     * Susun array data untuk tabel PROSEM (Program Semester).
     */
    private static function buildProsemSemesterStructure(string $semesterType, array $data): array
    {
        $isGanjil = strtolower($semesterType) === 'ganjil';
        $semesterLabel = $isGanjil ? 'PROGRAM SEMESTER GANJIL' : 'PROGRAM SEMESTER GENAP';
        $semesterRoman = $isGanjil ? 'SEMESTER I' : 'SEMESTER II';

        $mapel = $data['mapel'];
        $kelas = $data['kelas'];
        $tahun = $data['tahun'];
        $babs = $data['babs'];
        $sumatifJp = $data['sumatif_jp'];
        $cadanganJp = $data['cadangan_jp'];
        $jpPerWeek = $data['jp_per_week'];

        // Konfigurasi 6 bulan (masing-masing jumlah minggu: 4 atau 5)
        $months = $isGanjil ? [
            ['name' => 'Juli',      'weeks' => 5],
            ['name' => 'Agustus',   'weeks' => 4],
            ['name' => 'September', 'weeks' => 5],
            ['name' => 'Oktober',   'weeks' => 4],
            ['name' => 'November',  'weeks' => 4],
            ['name' => 'Desember',  'weeks' => 4],
        ] : [
            ['name' => 'Januari',   'weeks' => 5],
            ['name' => 'Februari',  'weeks' => 4],
            ['name' => 'Maret',     'weeks' => 4],
            ['name' => 'April',     'weeks' => 4],
            ['name' => 'Mei',       'weeks' => 5],
            ['name' => 'Juni',      'weeks' => 4],
        ];

        $totalWeekCols = array_sum(array_column($months, 'weeks')); // 26 minggu

        // Buat daftar minggu (flat index 0..25) beserta label bulan & minggu ke-
        $weekColumns = [];
        $colIdx = 0;
        foreach ($months as $m) {
            for ($w = 1; $w <= $m['weeks']; $w++) {
                $weekColumns[$colIdx] = [
                    'col_index' => $colIdx,
                    'month' => $m['name'],
                    'week_num' => $w,
                    'label' => "{$m['name']} M{$w}",
                ];
                $colIdx++;
            }
        }

        // Tentukan blok event non-KBM (vertikal berarsir/warna)
        $calendarEvents = ! empty($data['calendar_events']) && is_array($data['calendar_events'])
            ? self::normalizeCustomCalendarEvents($data['calendar_events'], $totalWeekCols)
            : self::getDefaultCalendarEvents($isGanjil, $tahun);

        // Identifikasi kolom minggu yang aktif untuk KBM (tidak tertutup event non-KBM)
        $blockedColMap = [];
        foreach ($calendarEvents as $ev) {
            for ($i = $ev['start_col']; $i < $ev['start_col'] + $ev['col_span']; $i++) {
                $blockedColMap[$i] = $ev['label'];
            }
        }

        $activeWeekCols = [];
        for ($i = 0; $i < $totalWeekCols; $i++) {
            if (! isset($blockedColMap[$i])) {
                $activeWeekCols[] = $i;
            }
        }

        // Distribusi JP mingguan ke setiap baris TP dan Sumatif
        $distribution = self::distributeWeeklyJp(
            $babs,
            $activeWeekCols,
            $jpPerWeek,
            $sumatifJp
        );

        $totalSemesterJp = array_sum(array_column($babs, 'alokasi_jp')) + $cadanganJp;

        $signature = self::resolveSignature($data['meta'] ?? [], $semesterType);

        return [
            'semester_type' => $semesterType,
            'title' => "{$semesterLabel} KELAS {$kelas}",
            'mapel' => $mapel,
            'tahun' => $tahun,
            'semester_roman' => $semesterRoman,
            'months' => $months,
            'total_week_cols' => $totalWeekCols,
            'week_columns' => $weekColumns,
            'calendar_events' => $calendarEvents,
            'blocked_col_map' => $blockedColMap,
            'active_week_cols' => $activeWeekCols,
            'jp_per_week' => $jpPerWeek,
            'sumatif_jp' => $sumatifJp,
            'cadangan_jp' => $cadanganJp,
            'babs' => $distribution['babs'],
            'total_jp' => $totalSemesterJp,
            'signature' => $signature,
        ];
    }

    /**
     * Dapatkan daftar kolom minggu kalender (26 minggu) beserta label bulan dan minggu ke-.
     */
    public static function getWeekColumns(bool $isGanjil): array
    {
        $months = $isGanjil ? [
            ['name' => 'Juli',      'weeks' => 5],
            ['name' => 'Agustus',   'weeks' => 4],
            ['name' => 'September', 'weeks' => 5],
            ['name' => 'Oktober',   'weeks' => 4],
            ['name' => 'November',  'weeks' => 4],
            ['name' => 'Desember',  'weeks' => 4],
        ] : [
            ['name' => 'Januari',   'weeks' => 5],
            ['name' => 'Februari',  'weeks' => 4],
            ['name' => 'Maret',     'weeks' => 4],
            ['name' => 'April',     'weeks' => 4],
            ['name' => 'Mei',       'weeks' => 5],
            ['name' => 'Juni',      'weeks' => 4],
        ];

        $columns = [];
        $colIdx = 0;
        foreach ($months as $m) {
            for ($w = 1; $w <= $m['weeks']; $w++) {
                $columns[] = [
                    'col_index' => $colIdx,
                    'month' => $m['name'],
                    'week_num' => $w,
                    'label' => "{$m['name']} M{$w}",
                    'full_label' => "{$m['name']} - Minggu {$w}",
                ];
                $colIdx++;
            }
        }

        return $columns;
    }

    /**
     * Normalisasi dan validasi array event kalender kustom dari pengguna.
     */
    public static function normalizeCustomCalendarEvents(array $events, int $totalCols = 26): array
    {
        $normalized = [];

        foreach ($events as $ev) {
            $label = trim((string) ($ev['label'] ?? ''));
            if ($label === '') {
                continue;
            }

            $start = max(0, min($totalCols - 1, (int) ($ev['start_col'] ?? 0)));
            $span = isset($ev['col_span'])
                ? max(1, min($totalCols - $start, (int) $ev['col_span']))
                : (isset($ev['end_col']) ? max(1, min($totalCols - $start, (int) $ev['end_col'] - $start + 1)) : 1);

            $color = (string) ($ev['color'] ?? '#cbd5e1');
            if (! str_starts_with($color, '#')) {
                $color = '#'.$color;
            }
            $textColor = (string) ($ev['text_color'] ?? '#1e293b');
            if (! str_starts_with($textColor, '#')) {
                $textColor = '#'.$textColor;
            }

            $normalized[] = [
                'start_col' => $start,
                'col_span' => $span,
                'end_col' => $start + $span - 1,
                'label' => $label,
                'color' => $color,
                'text_color' => $textColor,
            ];
        }

        usort($normalized, fn ($a, $b) => $a['start_col'] <=> $b['start_col']);

        return $normalized;
    }

    /**
     * Daftar event kalender pendidikan non-KBM bawaan (standar acuan Kurikulum Merdeka).
     */
    public static function getDefaultCalendarEvents(bool $isGanjil, string $tahun = '2026/2027'): array
    {
        if ($isGanjil) {
            // Ekstrak tahun sebelumnya (misal 2026/2027 -> 2025/2026)
            $tahunPrev = '2025/2026';
            $tahunCurr = '2026/2027';
            if (preg_match('/^(\d{4})\/(\d{4})$/', $tahun, $m)) {
                $y1 = (int) $m[1];
                $y2 = (int) $m[2];
                $tahunPrev = ($y1 - 1).'/'.($y2 - 1);
                $tahunCurr = "{$y1}/{$y2}";
            }

            return [
                [
                    'start_col' => 0,
                    'col_span' => 4, // Juli M1 - M4
                    'end_col' => 3,
                    'label' => "LIBUR SEMESTER GENAP T.P. {$tahunPrev} + PPDB T.P. {$tahunCurr}",
                    'color' => '#f8c4cb',
                    'text_color' => '#881337',
                ],
                [
                    'start_col' => 4,
                    'col_span' => 1, // Juli M5
                    'end_col' => 4,
                    'label' => "MPLS & Persiapan Semester Ganjil T.P. {$tahunCurr}",
                    'color' => '#cbd5e1',
                    'text_color' => '#1e293b',
                ],
                [
                    'start_col' => 22,
                    'col_span' => 1, // Desember M1 (indeks 22)
                    'end_col' => 22,
                    'label' => 'PENILAIAN AKHIR SEMESTER GANJIL',
                    'color' => '#a7f3d0',
                    'text_color' => '#064e3b',
                ],
                [
                    'start_col' => 23,
                    'col_span' => 2, // Desember M2 & M3 (indeks 23, 24)
                    'end_col' => 24,
                    'label' => 'CLASS MEETING + PENGOLAHAN NILAI + PEMBAGIAN RAPOR',
                    'color' => '#fed7aa',
                    'text_color' => '#7c2d12',
                ],
                [
                    'start_col' => 25,
                    'col_span' => 1, // Desember M4 (indeks 25)
                    'end_col' => 25,
                    'label' => 'LIBUR SEMESTER GANJIL + LIBUR NATAL',
                    'color' => '#fef08a',
                    'text_color' => '#713f12',
                ],
            ];
        }

        // Semester Genap
        return [
            [
                'start_col' => 0,
                'col_span' => 1, // Januari M1
                'end_col' => 0,
                'label' => 'PERSIAPAN KBM SEMESTER GENAP',
                'color' => '#cbd5e1',
                'text_color' => '#1e293b',
            ],
            [
                'start_col' => 11,
                'col_span' => 1, // Maret M3
                'end_col' => 11,
                'label' => 'SUMATIF TENGAH SEMESTER GENAP',
                'color' => '#fed7aa',
                'text_color' => '#7c2d12',
            ],
            [
                'start_col' => 14,
                'col_span' => 2, // April M2 - M3
                'end_col' => 15,
                'label' => 'LIBUR HARI RAYA IDUL FITRI',
                'color' => '#a7f3d0',
                'text_color' => '#064e3b',
            ],
            [
                'start_col' => 22,
                'col_span' => 1, // Juni M1
                'end_col' => 22,
                'label' => 'PENILAIAN AKHIR TAHUN / PSAJ',
                'color' => '#bbf7d0',
                'text_color' => '#064e3b',
            ],
            [
                'start_col' => 23,
                'col_span' => 1, // Juni M2
                'end_col' => 23,
                'label' => 'PENGOLAHAN NILAI & RAPOR',
                'color' => '#fed7aa',
                'text_color' => '#7c2d12',
            ],
            [
                'start_col' => 24,
                'col_span' => 2, // Juni M3 - M4
                'end_col' => 25,
                'label' => 'LIBUR AKHIR SEMESTER GENAP',
                'color' => '#f8c4cb',
                'text_color' => '#881337',
            ],
        ];
    }

    /**
     * Distribusikan alokasi JP per minggu secara berurutan ke baris TP dan Sumatif.
     */
    private static function distributeWeeklyJp(
        array $babs,
        array $activeWeekCols,
        int $jpPerWeek,
        int $sumatifJp
    ): array {
        $activeIdx = 0;
        $totalActive = count($activeWeekCols);
        $currentWeekRemaining = $jpPerWeek;

        $processedBabs = [];

        foreach ($babs as $bIndex => $bab) {
            $tps = $bab['tps'];
            $tpCount = count($tps);
            $sumatifVal = (int) ($bab['sumatif_jp'] ?? $sumatifJp);
            // Sisa jam untuk tujuan pembelajaran: total jam ruang lingkup dikurangi jam sumatif
            $totalMateriJp = $bab['tp_alokasi_jp'] ?? max(1, $bab['alokasi_jp'] - $sumatifVal);

            // Bagikan JP materi ke masing-masing TP secara proporsional dan terstruktur rapi per minggu utuh
            $tpJpList = self::calculateCleanTpJpList($totalMateriJp, $tpCount, $jpPerWeek, $sumatifVal);

            $processedTps = [];
            foreach ($tps as $tIndex => $tp) {
                $neededJp = $tpJpList[$tIndex] ?? $jpPerWeek;
                $weeks = [];

                while ($neededJp > 0 && $activeIdx < $totalActive) {
                    $col = $activeWeekCols[$activeIdx];
                    $allocated = min($neededJp, $currentWeekRemaining);

                    $weeks[$col] = ($weeks[$col] ?? 0) + $allocated;
                    $neededJp -= $allocated;
                    $currentWeekRemaining -= $allocated;

                    if ($currentWeekRemaining <= 0) {
                        $activeIdx++;
                        $currentWeekRemaining = $jpPerWeek;
                    }
                }

                $processedTps[] = array_merge($tp, [
                    'target_jp' => $tpJpList[$tIndex] ?? 0,
                    'weeks' => $weeks,
                ]);
            }

            // Alokasikan baris PENILAIAN SUMATIF bab tersebut
            $sumatifNeeded = $sumatifVal;
            $sumatifWeeks = [];
            while ($sumatifNeeded > 0 && $activeIdx < $totalActive) {
                $col = $activeWeekCols[$activeIdx];
                $allocated = min($sumatifNeeded, $currentWeekRemaining);

                $sumatifWeeks[$col] = ($sumatifWeeks[$col] ?? 0) + $allocated;
                $sumatifNeeded -= $allocated;
                $currentWeekRemaining -= $allocated;

                if ($currentWeekRemaining <= 0) {
                    $activeIdx++;
                    $currentWeekRemaining = $jpPerWeek;
                }
            }

            $processedBabs[] = array_merge($bab, [
                'tp_alokasi_jp' => $totalMateriJp,
                'sumatif_jp' => $sumatifVal,
                'tps' => $processedTps,
                'sumatif_weeks' => $sumatifWeeks,
            ]);
        }

        return [
            'babs' => $processedBabs,
            'remaining_active_weeks' => array_slice($activeWeekCols, $activeIdx),
        ];
    }

    /**
     * Sintesis data Bab dari atpSummaryRows jika tidak ada elemen terstruktur.
     */
    private static function synthesizeBabsFromAtpSummary(array $summaryRows, int $targetGrade): array
    {
        $grouped = [];
        foreach ($summaryRows as $r) {
            $kelasDigit = (int) preg_replace('/[^\d]/', '', (string) ($r['kelas'] ?? ''));
            if ($kelasDigit !== 0 && $kelasDigit !== $targetGrade) {
                continue;
            }

            $materi = trim((string) ($r['lingkup_materi'] ?? 'Materi Pokok'));
            if (! isset($grouped[$materi])) {
                $grouped[$materi] = [];
            }
            $grouped[$materi][] = $r;
        }

        $babs = [];
        $counter = 1;
        foreach ($grouped as $matName => $rows) {
            $tps = [];
            $totalJp = 0;
            foreach ($rows as $r) {
                $jp = (int) ($r['jp'] ?? 0);
                $totalJp += $jp;
                $tps[] = [
                    'kode' => $r['kode_tp'] ?? "TP.{$targetGrade}.".($counter),
                    'tp' => $r['tp'] ?? '',
                    'jp' => $jp,
                    'semester' => $r['semester'] ?? '',
                ];
            }

            $alokasiVal = $totalJp ?: 27;
            $tpVal = max(1, $alokasiVal - 2);

            $babs[] = [
                'bab_no' => $counter,
                'bab_title' => self::formatBabTitle($matName, $counter),
                'materi_raw' => $matName,
                'alokasi_jp' => $alokasiVal,
                'tp_alokasi_jp' => $tpVal,
                'sumatif_jp' => 2,
                'tps' => $tps,
                'elemen' => 'Pemahaman Konsep',
                'cp' => '',
            ];
            $counter++;
        }

        return $babs;
    }

    /**
     * Hitung alokasi JP per butir TP untuk Prosem dengan memprioritaskan minggu-minggu utuh KBM (kelipatan jp_per_week)
     * dan mengalokasikan sisa jam non-utuh ke TP terakhir yang berbagi minggu dengan Penilaian Sumatif.
     */
    public static function calculateCleanTpJpList(int $totalMateriJp, int $tpCount, int $jpPerWeek, int $sumatifJp): array
    {
        if ($tpCount <= 0) {
            return [];
        }
        if ($tpCount === 1) {
            return [$totalMateriJp];
        }

        // Jam pelajaran pada minggu asesmen sumatif yang tersisa untuk KBM
        $examWeekInstructionJp = ($jpPerWeek - ($sumatifJp % $jpPerWeek)) % $jpPerWeek;

        // Jam KBM untuk minggu-minggu utuh
        $fullWeeksJp = $totalMateriJp - $examWeekInstructionJp;

        if ($fullWeeksJp > 0 && $jpPerWeek > 0) {
            $numFullWeeks = intdiv($fullWeeksJp, $jpPerWeek);
            $baseWeeks = intdiv($numFullWeeks, $tpCount);
            $remWeeks = $numFullWeeks % $tpCount;

            $result = [];
            for ($i = 0; $i < $tpCount; $i++) {
                $weeks = $baseWeeks + ($i < $remWeeks ? 1 : 0);
                $jp = $weeks * $jpPerWeek;
                if ($i === $tpCount - 1) {
                    $jp += $examWeekInstructionJp;
                }
                $result[] = $jp;
            }

            if (array_sum($result) === $totalMateriJp) {
                return $result;
            }
        }

        return TpAtpDocument::distributeJpPerTp($totalMateriJp, $tpCount);
    }

    /**
     * Resolusi blok data tanda tangan (Kepala Sekolah & Guru Mata Pelajaran)
     * secara dinamis dari database aplikasi (Guru kepala sekolah, Setting, dan User login saat ini).
     *
     * @param array $options Opsi tambahan/kustom dari pemanggil (bisa berisi signature, kepala_sekolah, guru, dll)
     * @param string|null $semesterType Jenis semester ('ganjil' atau 'genap') untuk menentukan default tanggal
     * @return array{tempat_tanggal: string, kepala_sekolah: string, nip_kepala: string, jabatan_kepala: string, guru: string, nip_guru: string, jabatan_guru: string}
     */
    public static function resolveSignature(array $options = [], ?string $semesterType = null): array
    {
        $custom = array_merge($options['signature'] ?? [], $options);

        // 1. Data Kepala Sekolah (dari Guru dengan access 'kepala' atau Setting)
        $kepalaGuru = null;
        try {
            if (class_exists(\App\Models\Guru::class)) {
                $kepalaGuru = \App\Models\Guru::whereHas('user', fn ($q) => $q->where('access', 'kepala'))->first();
            }
        } catch (\Throwable $e) {
        }

        $appKepalaNama = $kepalaGuru?->nama;
        if (empty($appKepalaNama) && class_exists(\App\Models\Setting::class)) {
            try {
                $appKepalaNama = (string) \App\Models\Setting::get('kepala_sekolah');
            } catch (\Throwable $e) {
            }
        }

        $appKepalaNik = $kepalaGuru?->nik ?: $kepalaGuru?->nip;
        if (empty($appKepalaNik) && class_exists(\App\Models\Setting::class)) {
            try {
                $appKepalaNik = (string) \App\Models\Setting::get('nip_kepala');
            } catch (\Throwable $e) {
            }
        }

        $kepalaNama = ! empty($custom['kepala_sekolah']) && ! self::isPlaceholder($custom['kepala_sekolah'])
            ? (string) $custom['kepala_sekolah']
            : ($appKepalaNama ?: 'Dra. Megawati.');
        $kepalaNama = trim($kepalaNama) ?: 'Dra. Megawati.';

        $kepalaNik = ! empty($custom['nip_kepala']) && ! self::isPlaceholder($custom['nip_kepala'])
            ? (string) $custom['nip_kepala']
            : ($appKepalaNik ?: '10010070010');
        $kepalaNik = trim($kepalaNik) ?: '10010070010';

        $namaSekolah = '';
        if (class_exists(\App\Models\Setting::class)) {
            try {
                $namaSekolah = (string) \App\Models\Setting::get('nama_sekolah', 'SMPS Maitreyawira Tanjungpinang');
            } catch (\Throwable $e) {
            }
        }
        $namaSekolah = trim($namaSekolah) ?: 'SMPS Maitreyawira Tanjungpinang';
        $jabatanKepala = ! empty($custom['jabatan_kepala'])
            ? (string) $custom['jabatan_kepala']
            : "Kepala {$namaSekolah}";

        // 2. Data Guru Mata Pelajaran (dari User login saat ini atau custom options)
        $user = null;
        try {
            $user = auth()->user();
        } catch (\Throwable $e) {
        }

        $userGuruNama = $user?->guru?->nama ?: $user?->name;
        $userGuruNik = $user?->guru?->nik ?: ($user?->guru?->nip ?: $user?->username);

        if ($user !== null) {
            $hasCustomGuru = ! empty($custom['guru']) && ! self::isPlaceholder($custom['guru']) && $custom['guru'] !== 'Desmalini, S.E., Gr.';
            $guruNama = $hasCustomGuru ? (string) $custom['guru'] : ($userGuruNama ?: 'Guru Mata Pelajaran');

            $hasCustomNik = ! empty($custom['nip_guru']) && ! self::isPlaceholder($custom['nip_guru']) && $custom['nip_guru'] !== '10090150308';
            $guruNik = $hasCustomNik ? (string) $custom['nip_guru'] : ($userGuruNik ?: '-');
        } else {
            $guruNama = ! empty($custom['guru']) && ! self::isPlaceholder($custom['guru'])
                ? (string) $custom['guru']
                : (! empty($custom['name']) && ! self::isPlaceholder($custom['name']) ? (string) $custom['name'] : 'Desmalini, S.E., Gr.');

            $guruNik = ! empty($custom['nip_guru']) && ! self::isPlaceholder($custom['nip_guru'])
                ? (string) $custom['nip_guru']
                : (! empty($custom['nip']) && ! self::isPlaceholder($custom['nip']) ? (string) $custom['nip'] : '10090150308');
        }

        $guruNama = trim($guruNama) ?: 'Guru Mata Pelajaran';
        $guruNik = trim($guruNik) ?: '-';

        $jabatanGuru = ! empty($custom['jabatan_guru'])
            ? (string) $custom['jabatan_guru']
            : 'Guru Mata Pelajaran';

        // 3. Tempat dan Tanggal
        $kota = 'Tanjungpinang';
        if (class_exists(\App\Models\Setting::class)) {
            try {
                $kota = trim((string) \App\Models\Setting::get('kota', 'Tanjungpinang'));
            } catch (\Throwable $e) {
            }
        }
        if (str_starts_with(strtolower($kota), 'kota ')) {
            $kota = substr($kota, 5);
        }
        $kota = trim($kota) ?: 'Tanjungpinang';

        $defaultTanggal = (strtolower((string) $semesterType) === 'genap')
            ? "{$kota}, 05 Januari 2027"
            : "{$kota}, 17 Juli 2026";

        $tempatTanggal = ! empty($custom['tempat_tanggal']) && ! self::isPlaceholder($custom['tempat_tanggal'])
            ? (string) $custom['tempat_tanggal']
            : (! empty($custom['place_date']) && ! self::isPlaceholder($custom['place_date']) ? (string) $custom['place_date'] : $defaultTanggal);

        return [
            'tempat_tanggal' => $tempatTanggal,
            'kepala_sekolah' => $kepalaNama,
            'nip_kepala' => $kepalaNik,
            'jabatan_kepala' => $jabatanKepala,
            'guru' => $guruNama,
            'nip_guru' => $guruNik,
            'jabatan_guru' => $jabatanGuru,
        ];
    }

    /**
     * Cek apakah teks merupakan placeholder kosong/titik-titik/garis bawah.
     */
    public static function isPlaceholder(?string $val): bool
    {
        if ($val === null) {
            return true;
        }
        $t = trim($val);

        return $t === ''
            || str_contains($t, '..........')
            || str_contains($t, '__________')
            || str_contains($t, '[Nama ')
            || str_contains($t, '[nomor');
    }
}
