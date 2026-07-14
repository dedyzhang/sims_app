<?php

namespace App\Support;

/**
 * Parser dokumen perangkat ajar (RPM, LKPD, Modul Ajar) hasil generator AI.
 * Mengubah teks polos menjadi struktur kop/identitas/tabel/lampiran supaya
 * export PDF dan Word bisa dirender sebagai tabel formal mengikuti format RPM
 * resmi (Perencanaan Pembelajaran Mendalam). Bila struktur inti tidak
 * ditemukan, `parsed` bernilai false dan pemanggil memakai render teks polos.
 */
final class LearningDocument
{
    private const TITLE = 'PERENCANAAN PEMBELAJARAN MENDALAM';

    private const IDENTITY_LABELS = ['SEKOLAH', 'NAMA GURU', 'MATA PELAJARAN', 'KELAS / SEMESTER', 'KELAS/SEMESTER', 'ALOKASI WAKTU'];

    private const IDENTIFIKASI_LABELS = ['Dimensi Profil Lulusan', 'Murid', 'Materi'];

    private const DESAIN_LABELS = [
        'Capaian Pembelajaran', 'Lintas Disiplin Ilmu', 'Tujuan Pembelajaran', 'Topik Pembelajaran',
        'Praktik Pedagogis', 'Kemitraan Pembelajaran', 'Lingkungan Pembelajaran', 'Pemanfaatan Digital',
    ];

    private const ASESMEN_LABELS = ['Asesmen pada Awal Pembelajaran', 'Asesmen pada Proses Pembelajaran', 'Asesmen pada Akhir Pembelajaran'];

    private const STAGE_HEADINGS = ['AWAL', 'INTI', 'MEMAHAMI', 'MENGAPLIKASI', 'MEREFLEKSI', 'PENUTUP'];

    public static function parse(string $content): array
    {
        $doc = [
            'parsed' => false,
            'text' => '',
            'kop' => [],
            'title' => '',
            'subtitle' => '',
            'identity' => [],
            'identifikasi' => [],
            'desain' => [],
            'pengalaman' => [],
            'asesmen' => [],
            'signature' => ['date' => '', 'rows' => []],
            'lampiran' => [],
        ];

        $lines = self::sanitize($content);
        $doc['text'] = implode("\n", $lines);
        $state = 'kop';
        $row = null; // baris tabel aktif (identifikasi/desain/asesmen)
        $stage = null; // tahap pengalaman belajar aktif
        $lampiran = null;

        $flushRow = function (string $into) use (&$doc, &$row) {
            if ($row !== null) {
                $doc[$into][] = $row;
                $row = null;
            }
        };
        $flushStage = function () use (&$doc, &$stage) {
            if ($stage !== null) {
                $doc['pengalaman'][] = $stage;
                $stage = null;
            }
        };
        $flushLampiran = function () use (&$doc, &$lampiran) {
            if ($lampiran !== null) {
                $doc['lampiran'][] = $lampiran;
                $lampiran = null;
            }
        };
        $flushAll = function () use (&$state, $flushRow, $flushStage, $flushLampiran) {
            match ($state) {
                'identifikasi' => $flushRow('identifikasi'),
                'desain' => $flushRow('desain'),
                'asesmen' => $flushRow('asesmen'),
                'pengalaman' => $flushStage(),
                'lampiran' => $flushLampiran(),
                default => null,
            };
        };

        foreach ($lines as $line) {
            $trimmed = trim($line);
            $upper = mb_strtoupper($trimmed, 'UTF-8');

            if ($trimmed === '') {
                continue;
            }

            // Transisi antar bagian utama berlaku dari state mana pun.
            if (str_starts_with($upper, 'LAMPIRAN') && preg_match('/^LAMPIRAN\s*\d/u', $upper)) {
                $flushAll();
                $lampiran = ['heading' => $trimmed, 'blocks' => []];
                $state = 'lampiran';

                continue;
            }
            $section = match (rtrim($upper, ': ')) {
                'IDENTIFIKASI' => 'identifikasi',
                'DESAIN PEMBELAJARAN' => 'desain',
                'PENGALAMAN BELAJAR' => 'pengalaman',
                'ASESMEN PEMBELAJARAN' => 'asesmen',
                default => null,
            };
            if ($section !== null) {
                $flushAll();
                $state = $section;

                continue;
            }

            if ($state === 'kop') {
                if (str_contains($upper, self::TITLE)) {
                    $doc['title'] = self::TITLE;
                    $state = 'judul';
                } else {
                    $doc['kop'][] = $trimmed;
                }

                continue;
            }

            if ($state === 'judul' || $state === 'identitas') {
                $identityPattern = '~^('.implode('|', array_map(fn ($l) => preg_quote($l, '~'), self::IDENTITY_LABELS)).')\s*:\s*(.*)$~iu';
                if (preg_match($identityPattern, $trimmed, $m)) {
                    $doc['identity'][] = ['label' => mb_strtoupper(trim($m[1]), 'UTF-8'), 'value' => trim($m[2])];
                    $state = 'identitas';
                } elseif ($state === 'judul' && $doc['subtitle'] === '') {
                    $doc['subtitle'] = $trimmed;
                }

                continue;
            }

            if ($state === 'identifikasi' || $state === 'desain' || $state === 'asesmen') {
                $labels = match ($state) {
                    'identifikasi' => self::IDENTIFIKASI_LABELS,
                    'desain' => self::DESAIN_LABELS,
                    default => self::ASESMEN_LABELS,
                };

                // Blok tanda tangan muncul setelah tabel asesmen.
                if ($state === 'asesmen' && (str_starts_with($upper, 'MENGETAHUI') || self::isPlaceDate($trimmed))) {
                    // "Tempat, tanggal" kerap ditulis tepat sebelum "Mengetahui" sehingga
                    // sudah terlanjur masuk sel asesmen terakhir — tarik kembali ke sini.
                    if ($row !== null && $row['lines'] !== [] && self::isPlaceDate(end($row['lines']))) {
                        $doc['signature']['date'] = array_pop($row['lines']);
                    }
                    $flushRow('asesmen');
                    $state = 'ttd';
                    if (! str_starts_with($upper, 'MENGETAHUI')) {
                        $doc['signature']['date'] = $trimmed;

                        continue;
                    }
                }

                if ($state !== 'ttd') {
                    $matched = self::matchLabel($trimmed, $labels);
                    if ($matched !== null) {
                        $flushRow($state);
                        $row = ['label' => $matched['label'], 'lines' => [], 'dpl' => [], 'intro' => []];
                        if ($matched['rest'] !== '') {
                            $row['lines'][] = $matched['rest'];
                        }
                    } elseif ($row !== null) {
                        if ($state === 'identifikasi' && str_starts_with($row['label'], 'Dimensi Profil Lulusan')
                            && preg_match('/^(\S{1,3}\s+)?DPL\s*(\d+)\s*[.:]?\s*(.*)$/u', $trimmed, $m)) {
                            $marker = trim($m[1] ?? '');
                            $checked = $marker !== '' && ! in_array($marker, ['☐', '□', '[]', '[ ]', '-'], true);
                            $row['dpl'][] = ['checked' => $checked, 'label' => 'DPL '.$m[2].' '.trim($m[3])];
                        } elseif ($state === 'identifikasi' && str_starts_with($row['label'], 'Dimensi Profil Lulusan') && $row['dpl'] === []) {
                            $row['intro'][] = $trimmed;
                        } else {
                            // Centang hanya lazim di PENGALAMAN BELAJAR; model kerap membubuhkannya
                            // juga di sel deskriptif — buang agar tabel setia ke format acuan.
                            $row['lines'][] = preg_replace('/^[✓✔]\s*/u', '', $trimmed);
                        }
                    }

                    continue;
                }
            }

            if ($state === 'pengalaman') {
                if (preg_match('/^('.implode('|', self::STAGE_HEADINGS).')\b\s*(.*)$/u', $upper)) {
                    $flushStage();
                    preg_match('/^(\S+)\s*(.*)$/u', $trimmed, $m);
                    $stage = ['heading' => mb_strtoupper($m[1], 'UTF-8'), 'subtitle' => trim($m[2] ?? ''), 'items' => []];
                } elseif ($stage !== null) {
                    if ($stage['items'] === [] && $stage['subtitle'] === '' && preg_match('/^\(.*\)$/u', $trimmed)) {
                        $stage['subtitle'] = $trimmed;
                    } elseif (preg_match('/^[✓✔•\-\*]\s*(.+)$/u', $trimmed, $m)) {
                        $stage['items'][] = ['type' => 'check', 'text' => trim($m[1])];
                    } elseif (preg_match('/^[“"\'‘].*[”"\'’][\s]*$/u', $trimmed)) {
                        $stage['items'][] = ['type' => 'quote', 'text' => $trimmed];
                    } else {
                        $stage['items'][] = ['type' => 'text', 'text' => $trimmed];
                    }
                }

                continue;
            }

            if ($state === 'ttd') {
                if ($doc['signature']['date'] === '' && self::isPlaceDate($trimmed)) {
                    $doc['signature']['date'] = $trimmed;
                } else {
                    $cells = array_map('trim', preg_split('/\s*\|\s*|\t+/u', $trimmed) ?: []);
                    // Baris "Mengetahui," + "Guru Mata Pelajaran" tanpa pemisah tetap 2 kolom
                    // bila dipisah 2+ spasi panjang (pola DOCX/PDF acuan).
                    if (count($cells) === 1 && preg_match('/^(.+?)\s{2,}(.+)$/u', $trimmed, $m)) {
                        $cells = [trim($m[1]), trim($m[2])];
                    }
                    $doc['signature']['rows'][] = [$cells[0] ?? '', $cells[1] ?? ''];
                }

                continue;
            }

            if ($state === 'lampiran' && $lampiran !== null) {
                if (substr_count($trimmed, '|') >= 2) {
                    $cells = array_map('trim', explode('|', $trimmed));

                    // Baris pemisah header tabel Markdown (`--- | --- | ---`) bukan data.
                    if (self::isTableDivider($cells)) {
                        continue;
                    }

                    $last = count($lampiran['blocks']) - 1;
                    if ($last >= 0 && $lampiran['blocks'][$last]['type'] === 'table') {
                        $lampiran['blocks'][$last]['rows'][] = $cells;
                    } else {
                        $lampiran['blocks'][] = ['type' => 'table', 'rows' => [$cells]];
                    }
                } else {
                    $last = count($lampiran['blocks']) - 1;
                    if ($last >= 0 && $lampiran['blocks'][$last]['type'] === 'text') {
                        $lampiran['blocks'][$last]['lines'][] = $trimmed;
                    } else {
                        $lampiran['blocks'][] = ['type' => 'text', 'lines' => [$trimmed]];
                    }
                }
            }
        }

        $flushAll();

        $doc['parsed'] = $doc['identifikasi'] !== [] && $doc['desain'] !== [] && $doc['pengalaman'] !== [];

        return $doc;
    }

    /**
     * Baris "Tempat, tanggal" di atas blok tanda tangan — bisa tanggal sungguhan
     * ("Tanjungpinang, 11 Juni 2026") atau placeholder ("[Tempat], [tanggal]").
     */
    private static function isPlaceDate(string $line): bool
    {
        // "Tanjungpinang, 11 Juni 2026"
        return (bool) preg_match('/^[^|:]{2,40},\s*\d{1,2}\s+\p{L}+\s+\d{4}\.?$/u', $line)
            // "[Tempat], [tanggal]" / "[Nama Kota], [Tanggal Pelaksanaan]"
            || (bool) preg_match('/^\[?[^|:\[\]]{2,40}\]?,\s*\[[^|:\[\]]{2,40}\]\.?$/u', $line);
    }

    /** @param string[] $cells */
    private static function isTableDivider(array $cells): bool
    {
        foreach ($cells as $cell) {
            if ($cell !== '' && ! preg_match('/^:?-{2,}:?$/u', $cell)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Rapikan keluaran AI menjadi baris teks polos: model kerap membubuhkan Markdown
     * (**tebal**, heading #, bullet * / -) dan kalimat basa-basi pembuka meski diminta
     * tidak. Tanpa ini, heading seperti "**IDENTIFIKASI**" tak dikenali sebagai bagian.
     *
     * Dipakai juga oleh QuizDocument yang mengolah keluaran generator lain. Dokumen soal
     * memakai baris garis bawah sebagai ruang jawaban esai, jadi ia meminta baris tersebut
     * dipertahankan lewat $keepUnderline.
     *
     * @return string[]
     */
    public static function sanitize(string $content, bool $keepUnderline = false): array
    {
        $lines = [];
        $rule = $keepUnderline ? '/^([-*]\s*){3,}$/u' : '/^([-*_]\s*){3,}$/u';

        foreach (preg_split('/\R/u', trim($content)) ?: [] as $line) {
            $line = trim($line);

            // Pagar blok kode dan garis pemisah horizontal.
            if (str_starts_with($line, '```') || preg_match($rule, $line)) {
                continue;
            }

            // "__" adalah penanda tebal Markdown, tapi baris garis jawaban esai murni
            // garis bawah — jangan diutak-atik.
            if ($keepUnderline && preg_match('/^_{3,}$/u', $line)) {
                $lines[] = $line;

                continue;
            }

            $line = preg_replace('/^#{1,6}\s*/u', '', $line);       // heading Markdown
            $line = preg_replace('/^[*+-]\s+/u', '• ', $line);      // bullet Markdown
            $line = str_replace(['**', '__'], '', $line);           // tebal
            $line = preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/u', '$1', $line); // miring

            $lines[] = trim($line);
        }

        // Buang basa-basi pembuka sebelum kop sekolah.
        while ($lines !== [] && ($lines[0] === '' || preg_match('/^(berikut|baik|tentu|silakan|ini adalah|semoga)\b/iu', $lines[0]))) {
            array_shift($lines);
        }

        return $lines;
    }

    /**
     * Cocokkan awal baris dengan salah satu label sub-bagian; sisa baris jadi isi
     * pertama. Label hanya dianggap penanda baris tabel bila berdiri sendiri atau
     * langsung diikuti ":" — mencegah kalimat isi yang kebetulan berawalan sama
     * (mis. "Lingkungan Pembelajaran Terintegrasi:") terbaca sebagai label baru.
     */
    private static function matchLabel(string $line, array $labels): ?array
    {
        foreach ($labels as $label) {
            if (preg_match('/^'.preg_quote($label, '/').'(\s*\(DPL\))?\s*(?::\s*(.*))?$/iu', $line, $m)) {
                $display = $label.(isset($m[1]) && trim($m[1]) !== '' ? ' (DPL)' : '');

                return ['label' => $display, 'rest' => trim($m[2] ?? '')];
            }
        }

        return null;
    }
}
