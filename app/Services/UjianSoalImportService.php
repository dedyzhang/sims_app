<?php

namespace App\Services;

use App\Models\Ujian;
use App\Models\UjianSoal;
use App\Models\UjianSoalOpsi;
use App\Support\RichText;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class UjianSoalImportService
{
    /**
     * Impor soal dari file (Excel .xlsx/.xls/.csv atau JSON .json).
     *
     * @param Ujian $ujian
     * @param UploadedFile $file
     * @param string $mode 'append' | 'replace'
     * @return int Jumlah soal yang berhasil diimpor
     */
    public function importFile(Ujian $ujian, UploadedFile $file, string $mode = 'append'): int
    {
        $ext = strtolower($file->getClientOriginalExtension());

        if ($ext === 'json') {
            $content = file_get_contents($file->getRealPath());
            return $this->importJson($ujian, $content, $mode);
        }

        if (in_array($ext, ['xlsx', 'xls', 'csv'], true)) {
            return $this->importSpreadsheet($ujian, $file, $mode);
        }

        throw new InvalidArgumentException("Format file tidak didukung: .{$ext}. Harap gunakan file Excel (.xlsx, .xls), CSV, atau JSON.");
    }

    /**
     * Impor soal dari file spreadsheet Excel / CSV.
     */
    public function importSpreadsheet(Ujian $ujian, UploadedFile $file, string $mode = 'append'): int
    {
        $sheets = Excel::toArray(new class implements ToArray {
            public function array(array $array): array
            {
                return $array;
            }
        }, $file);

        $rows = $sheets[0] ?? [];

        if (empty($rows)) {
            throw new InvalidArgumentException('File Excel kosong atau tidak memiliki baris data pada sheet pertama.');
        }

        $items = $this->parseSpreadsheetRows($rows);

        if (empty($items)) {
            throw new InvalidArgumentException('Tidak ditemukan data soal yang valid dalam file Excel tersebut.');
        }

        return $this->importSoalArray($ujian, $items, $mode);
    }

    /**
     * Impor soal dari string JSON.
     */
    public function importJson(Ujian $ujian, string $jsonString, string $mode = 'append'): int
    {
        $decoded = json_decode($jsonString, true);

        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Format JSON tidak valid.');
        }

        // Dukung baik format envelope {soal: [...]} maupun array langsung [...]
        $rawList = isset($decoded['soal']) && is_array($decoded['soal'])
            ? $decoded['soal']
            : (array_is_list($decoded) ? $decoded : []);

        if (empty($rawList)) {
            throw new InvalidArgumentException('Data JSON tidak memuat daftar soal.');
        }

        $items = [];
        foreach ($rawList as $s) {
            if (!is_array($s) || empty($s['teks_soal'])) {
                continue;
            }

            $teksSoal = trim((string) $s['teks_soal']);
            if (Str::startsWith(strtoupper($teksSoal), ['[CONTOH]', 'CONTOH'])) {
                continue;
            }

            $tipe = $this->normalizeTipe($s['tipe'] ?? 'mcq');
            $poin = isset($s['poin']) && is_numeric($s['poin']) ? max(1, (int) $s['poin']) : 1;
            $skorMode = ($s['skor_mode'] ?? 'all_or_nothing') === 'proporsional' ? 'proporsional' : 'all_or_nothing';
            $penjelasan = !empty($s['penjelasan']) ? RichText::clean($s['penjelasan']) : null;
            $meta = is_array($s['meta'] ?? null) ? $s['meta'] : null;

            $opsi = [];
            if (in_array($tipe, ['mcq', 'mcq_complex', 'true_false'], true) && !empty($s['opsi']) && is_array($s['opsi'])) {
                foreach ($s['opsi'] as $o) {
                    if (is_array($o) && isset($o['teks'])) {
                        $opsi[] = [
                            'teks'  => (string) $o['teks'],
                            'benar' => !empty($o['benar']),
                        ];
                    }
                }
            }

            $items[] = [
                'tipe'       => $tipe,
                'teks_soal'  => $teksSoal,
                'poin'       => $poin,
                'skor_mode'  => $skorMode,
                'penjelasan' => $penjelasan,
                'meta'       => $meta,
                'opsi'       => $opsi,
            ];
        }

        if (empty($items)) {
            throw new InvalidArgumentException('Tidak ditemukan data soal valid dalam berkas JSON.');
        }

        return $this->importSoalArray($ujian, $items, $mode);
    }

    /**
     * Salin seluruh soal dari ujian sumber ke ujian tujuan secara independen.
     */
    public function salinDariUjian(Ujian $ujianTujuan, Ujian $ujianSumber, string $mode = 'append'): int
    {
        return DB::transaction(function () use ($ujianTujuan, $ujianSumber, $mode) {
            if ($mode === 'replace') {
                $ujianTujuan->soal()->delete();
                $urutan = 0;
            } else {
                $urutan = (int) $ujianTujuan->soal()->max('urutan');
            }

            $soalSumber = $ujianSumber->soal()->with('opsi')->orderBy('urutan')->get();
            $jumlahDisalin = 0;

            foreach ($soalSumber as $s) {
                $urutan++;
                $soalBaru = UjianSoal::create([
                    'id_ujian'   => $ujianTujuan->uuid,
                    'tipe'       => $s->tipe,
                    'teks_soal'  => $s->teks_soal,
                    'poin'       => $s->poin,
                    'urutan'     => $urutan,
                    'meta'       => $s->meta,
                    'penjelasan' => $s->penjelasan,
                    'skor_mode'  => $s->skor_mode,
                ]);

                foreach ($s->opsi as $o) {
                    UjianSoalOpsi::create([
                        'id_soal'   => $soalBaru->uuid,
                        'teks_opsi' => $o->teks_opsi,
                        'is_benar'  => (bool) $o->is_benar,
                        'urutan'    => $o->urutan,
                    ]);
                }

                $jumlahDisalin++;
            }

            $ujianTujuan->clearSoalCache();

            return $jumlahDisalin;
        });
    }

    /**
     * Parse baris-baris spreadsheet menjadi format item soal ternormalisasi.
     */
    public function parseSpreadsheetRows(array $rows): array
    {
        if (empty($rows)) {
            return [];
        }

        // Deteksi header kolom secara dinamis dari baris 0
        $map = [
            'tipe'       => null,
            'teks_soal'  => null,
            'poin'       => null,
            'skor_mode'  => null,
            'opsi_a'     => null,
            'opsi_b'     => null,
            'opsi_c'     => null,
            'opsi_d'     => null,
            'opsi_e'     => null,
            'kunci'      => null,
            'pasangan'   => null,
            'penjelasan' => null,
        ];

        $headerRow = $rows[0] ?? [];
        $isHeaderDetected = false;

        foreach ($headerRow as $colIdx => $val) {
            $h = strtolower(trim((string) $val));
            if ($h === '') continue;

            if (str_contains($h, 'tipe')) {
                $map['tipe'] = $colIdx;
                $isHeaderDetected = true;
            } elseif (str_contains($h, 'soal') || str_contains($h, 'pertanyaan')) {
                $map['teks_soal'] = $colIdx;
                $isHeaderDetected = true;
            } elseif (str_contains($h, 'poin') || str_contains($h, 'bobot') || (str_contains($h, 'skor') && !str_contains($h, 'mode'))) {
                $map['poin'] = $colIdx;
                $isHeaderDetected = true;
            } elseif (str_contains($h, 'mode')) {
                $map['skor_mode'] = $colIdx;
                $isHeaderDetected = true;
            } elseif (str_contains($h, 'opsi a') || str_contains($h, 'pilihan a')) {
                $map['opsi_a'] = $colIdx;
            } elseif (str_contains($h, 'opsi b') || str_contains($h, 'pilihan b')) {
                $map['opsi_b'] = $colIdx;
            } elseif (str_contains($h, 'opsi c') || str_contains($h, 'pilihan c')) {
                $map['opsi_c'] = $colIdx;
            } elseif (str_contains($h, 'opsi d') || str_contains($h, 'pilihan d')) {
                $map['opsi_d'] = $colIdx;
            } elseif (str_contains($h, 'opsi e') || str_contains($h, 'pilihan e')) {
                $map['opsi_e'] = $colIdx;
            } elseif (str_contains($h, 'kunci')) {
                $map['kunci'] = $colIdx;
                $isHeaderDetected = true;
            } elseif (str_contains($h, 'pasangan') || str_contains($h, 'jodoh') || str_contains($h, 'cocok')) {
                $map['pasangan'] = $colIdx;
                $isHeaderDetected = true;
            } elseif (str_contains($h, 'bahas') || str_contains($h, 'jelas')) {
                $map['penjelasan'] = $colIdx;
                $isHeaderDetected = true;
            }
        }

        // Jika header tidak terdeteksi lengkap, fallback ke urutan kolom default:
        // 0: No, 1: Tipe, 2: Teks Soal, 3: Poin, 4: Skor Mode, 5..9: Opsi A..E, 10: Kunci, 11: Pasangan, 12: Penjelasan
        $idxTipe = $map['tipe'] ?? 1;
        $idxTeksSoal = $map['teks_soal'] ?? 2;
        $idxPoin = $map['poin'] ?? 3;
        $idxSkorMode = $map['skor_mode'] ?? 4;
        $idxOpsiA = $map['opsi_a'] ?? 5;
        $idxOpsiB = $map['opsi_b'] ?? 6;
        $idxOpsiC = $map['opsi_c'] ?? 7;
        $idxOpsiD = $map['opsi_d'] ?? 8;
        $idxOpsiE = $map['opsi_e'] ?? 9;
        $idxKunci = $map['kunci'] ?? 10;
        $idxPasangan = $map['pasangan'] ?? 11;
        $idxPenjelasan = $map['penjelasan'] ?? 12;

        $startRow = $isHeaderDetected ? 1 : 0;
        $items = [];

        for ($i = $startRow; $i < count($rows); $i++) {
            $row = $rows[$i];
            if (!is_array($row) || empty($row)) {
                continue;
            }

            $teksSoal = trim((string) ($row[$idxTeksSoal] ?? ''));
            if ($teksSoal === '') {
                continue;
            }

            // Lewati baris contoh template
            if (Str::startsWith(strtoupper($teksSoal), ['[CONTOH]', 'CONTOH'])) {
                continue;
            }

            $rawTipe = trim((string) ($row[$idxTipe] ?? ''));
            $tipe = $this->normalizeTipe($rawTipe);

            $poinRaw = trim((string) ($row[$idxPoin] ?? ''));
            $poin = is_numeric($poinRaw) ? max(1, (int) $poinRaw) : 1;

            $rawMode = strtolower(trim((string) ($row[$idxSkorMode] ?? '')));
            $skorMode = str_contains($rawMode, 'proporsional') ? 'proporsional' : 'all_or_nothing';

            $rawPenjelasan = trim((string) ($row[$idxPenjelasan] ?? ''));
            $penjelasan = $rawPenjelasan !== '' ? RichText::clean($rawPenjelasan) : null;

            $rawKunci = trim((string) ($row[$idxKunci] ?? ''));
            $rawPasangan = trim((string) ($row[$idxPasangan] ?? ''));

            $meta = null;
            $opsi = [];

            if ($tipe === 'match') {
                // Pasangan menjodohkan
                $pairsText = $rawPasangan !== '' ? $rawPasangan : $rawKunci;
                $lines = preg_split('/[\r\n|]+/', $pairsText);
                $pairs = [];

                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '') continue;

                    $kiri = '';
                    $kanan = '';
                    if (str_contains($line, '=')) {
                        [$kiri, $kanan] = explode('=', $line, 2);
                    } elseif (str_contains($line, '->')) {
                        [$kiri, $kanan] = explode('->', $line, 2);
                    } elseif (str_contains($line, ':')) {
                        [$kiri, $kanan] = explode(':', $line, 2);
                    }

                    $kiri = trim($kiri);
                    $kanan = trim($kanan);

                    if ($kiri !== '' && $kanan !== '') {
                        $pairs[] = [
                            'left'  => RichText::clean($kiri),
                            'right' => RichText::clean($kanan),
                        ];
                    }
                }

                if (!empty($pairs)) {
                    $meta = ['pairs' => $pairs];
                }
            } elseif ($tipe === 'essay') {
                if ($rawKunci !== '') {
                    $meta = ['kunci_jawaban' => RichText::clean($rawKunci)];
                }
            } elseif ($tipe === 'true_false') {
                $opsiA = trim((string) ($row[$idxOpsiA] ?? ''));
                $opsiB = trim((string) ($row[$idxOpsiB] ?? ''));

                if ($opsiA === '') $opsiA = 'Benar';
                if ($opsiB === '') $opsiB = 'Salah';

                $kunciUpper = strtoupper($rawKunci);
                $isABenar = true;

                if ($kunciUpper === 'B' || str_contains(strtolower($rawKunci), 'salah') || str_contains(strtolower($rawKunci), 'false')) {
                    $isABenar = false;
                }

                $opsi = [
                    ['teks' => $opsiA, 'benar' => $isABenar],
                    ['teks' => $opsiB, 'benar' => !$isABenar],
                ];
            } else {
                // mcq dan mcq_complex
                $rawList = [
                    'A' => trim((string) ($row[$idxOpsiA] ?? '')),
                    'B' => trim((string) ($row[$idxOpsiB] ?? '')),
                    'C' => trim((string) ($row[$idxOpsiC] ?? '')),
                    'D' => trim((string) ($row[$idxOpsiD] ?? '')),
                    'E' => trim((string) ($row[$idxOpsiE] ?? '')),
                ];

                $daftarOpsi = [];
                foreach ($rawList as $letter => $val) {
                    if ($val !== '') {
                        $daftarOpsi[$letter] = $val;
                    }
                }

                // Jika opsi kurang dari 2, buat opsi default agar valid
                if (count($daftarOpsi) < 2) {
                    if (!isset($daftarOpsi['A'])) $daftarOpsi['A'] = 'Pilihan A';
                    if (!isset($daftarOpsi['B'])) $daftarOpsi['B'] = 'Pilihan B';
                }

                // Parse huruf kunci jawaban
                preg_match_all('/[A-E]/i', $rawKunci, $matches);
                $hurufKunci = array_map('strtoupper', $matches[0] ?? []);

                if ($tipe === 'mcq') {
                    $pilihanBenar = $hurufKunci[0] ?? null;

                    // Fallback jika kunci ditulis berupa teks opsi (bukan huruf)
                    if (!$pilihanBenar && $rawKunci !== '') {
                        foreach ($daftarOpsi as $huruf => $teks) {
                            if (strcasecmp($teks, $rawKunci) === 0) {
                                $pilihanBenar = $huruf;
                                break;
                            }
                        }
                    }

                    if (!$pilihanBenar) {
                        $keys = array_keys($daftarOpsi);
                        $pilihanBenar = $keys[0] ?? 'A';
                    }

                    foreach ($daftarOpsi as $huruf => $teks) {
                        $opsi[] = [
                            'teks'  => $teks,
                            'benar' => $huruf === $pilihanBenar,
                        ];
                    }
                } else {
                    // mcq_complex
                    if (empty($hurufKunci)) {
                        $hurufKunci = ['A'];
                    }

                    foreach ($daftarOpsi as $huruf => $teks) {
                        $opsi[] = [
                            'teks'  => $teks,
                            'benar' => in_array($huruf, $hurufKunci, true),
                        ];
                    }

                    // Pastikan minimal 1 benar
                    if (collect($opsi)->where('benar', true)->isEmpty()) {
                        $opsi[0]['benar'] = true;
                    }
                }
            }

            $items[] = [
                'tipe'       => $tipe,
                'teks_soal'  => RichText::clean($teksSoal),
                'poin'       => $poin,
                'skor_mode'  => $skorMode,
                'penjelasan' => $penjelasan,
                'meta'       => $meta,
                'opsi'       => $opsi,
            ];
        }

        return $items;
    }

    /**
     * Simpan kumpulan item soal ke database.
     */
    protected function importSoalArray(Ujian $ujian, array $items, string $mode = 'append'): int
    {
        return DB::transaction(function () use ($ujian, $items, $mode) {
            if ($mode === 'replace') {
                $ujian->soal()->delete();
                $urutan = 0;
            } else {
                $urutan = (int) $ujian->soal()->max('urutan');
            }

            $importedCount = 0;

            foreach ($items as $item) {
                $urutan++;

                $soal = UjianSoal::create([
                    'id_ujian'   => $ujian->uuid,
                    'tipe'       => $item['tipe'],
                    'teks_soal'  => $item['teks_soal'],
                    'poin'       => $item['poin'] ?? 1,
                    'urutan'     => $urutan,
                    'meta'       => $item['meta'] ?? null,
                    'penjelasan' => $item['penjelasan'] ?? null,
                    'skor_mode'  => $item['skor_mode'] ?? 'all_or_nothing',
                ]);

                if (!empty($item['opsi']) && in_array($item['tipe'], ['mcq', 'mcq_complex', 'true_false'], true)) {
                    foreach ($item['opsi'] as $idx => $op) {
                        UjianSoalOpsi::create([
                            'id_soal'   => $soal->uuid,
                            'teks_opsi' => RichText::clean($op['teks']),
                            'is_benar'  => !empty($op['benar']),
                            'urutan'    => $idx + 1,
                        ]);
                    }
                }

                $importedCount++;
            }

            $ujian->clearSoalCache();

            return $importedCount;
        });
    }

    /**
     * Normalisasi string tipe soal ke kanonik.
     */
    protected function normalizeTipe(string $raw): string
    {
        $clean = strtolower(str_replace([' ', '-', '_', '/'], '', trim($raw)));

        return match (true) {
            str_contains($clean, 'kompleks') || str_contains($clean, 'complex') || $clean === 'mcqcomplex' => 'mcq_complex',
            str_contains($clean, 'benar') || str_contains($clean, 'true') || $clean === 'bs' => 'true_false',
            str_contains($clean, 'jodoh') || str_contains($clean, 'cocok') || str_contains($clean, 'match') => 'match',
            str_contains($clean, 'esai') || str_contains($clean, 'essay') || str_contains($clean, 'uraian') => 'essay',
            default => 'mcq',
        };
    }
}
