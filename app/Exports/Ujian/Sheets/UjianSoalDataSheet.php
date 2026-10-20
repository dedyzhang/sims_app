<?php

namespace App\Exports\Ujian\Sheets;

use App\Models\Ujian;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UjianSoalDataSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents, WithStrictNullComparison
{
    public function __construct(protected ?Ujian $ujian = null)
    {
    }

    public function title(): string
    {
        return $this->ujian ? 'Daftar Soal' : 'Format Soal';
    }

    public function headings(): array
    {
        return [
            'No',
            'Tipe Soal',
            'Teks Soal',
            'Poin',
            'Mode Penilaian',
            'Opsi A',
            'Opsi B',
            'Opsi C',
            'Opsi D',
            'Opsi E',
            'Kunci Jawaban',
            'Pasangan Menjodohkan',
            'Pembahasan',
        ];
    }

    public function array(): array
    {
        if ($this->ujian) {
            $rows = [];
            $soalList = $this->ujian->soal()->with('opsi')->orderBy('urutan')->get();

            foreach ($soalList as $idx => $s) {
                $opsi = $s->opsi->values();

                // Tentukan kunci jawaban string
                $kunci = '';
                if ($s->tipe === 'mcq') {
                    foreach ($opsi as $oIdx => $op) {
                        if ($op->is_benar) {
                            $kunci = chr(65 + $oIdx);
                            break;
                        }
                    }
                } elseif ($s->tipe === 'mcq_complex') {
                    $benarLetters = [];
                    foreach ($opsi as $oIdx => $op) {
                        if ($op->is_benar) {
                            $benarLetters[] = chr(65 + $oIdx);
                        }
                    }
                    $kunci = implode(', ', $benarLetters);
                } elseif ($s->tipe === 'true_false') {
                    foreach ($opsi as $oIdx => $op) {
                        if ($op->is_benar) {
                            $kunci = $op->teks_opsi ?: chr(65 + $oIdx);
                            break;
                        }
                    }
                } elseif ($s->tipe === 'essay') {
                    $kunci = $s->meta['kunci_jawaban'] ?? '';
                }

                // Tentukan pasangan menjodohkan string
                $pasanganStr = '';
                if ($s->tipe === 'match' && !empty($s->meta['pairs'])) {
                    $pairLines = [];
                    foreach ($s->meta['pairs'] as $pair) {
                        $pairLines[] = ($pair['left'] ?? '') . ' = ' . ($pair['right'] ?? '');
                    }
                    $pasanganStr = implode("\n", $pairLines);
                }

                $rows[] = [
                    $idx + 1,
                    $s->tipe,
                    $s->teks_soal,
                    $s->poin,
                    $s->skor_mode ?? 'all_or_nothing',
                    $opsi[0]->teks_opsi ?? '',
                    $opsi[1]->teks_opsi ?? '',
                    $opsi[2]->teks_opsi ?? '',
                    $opsi[3]->teks_opsi ?? '',
                    $opsi[4]->teks_opsi ?? '',
                    $kunci,
                    $pasanganStr,
                    $s->penjelasan ?? '',
                ];
            }

            return $rows;
        }

        // Template Soal dengan 5 contoh terstruktur (mcq, mcq_complex, true_false, match, essay)
        return [
            [
                1,
                'mcq',
                '[CONTOH] Apa ibu kota negara Indonesia?',
                1,
                'all_or_nothing',
                'Jakarta',
                'Bandung',
                'Surabaya',
                'Medan',
                'Semarang',
                'A',
                '',
                'Ibu kota negara Indonesia adalah Jakarta.',
            ],
            [
                2,
                'mcq_complex',
                '[CONTOH] Manakah yang termasuk bilangan prima? (Pilihan jawaban lebih dari satu)',
                1,
                'proporsional',
                '2',
                '3',
                '4',
                '5',
                '6',
                'A, B, D',
                '',
                'Bilangan 2, 3, dan 5 hanya memiliki dua faktor pembagi yaitu 1 dan dirinya sendiri.',
            ],
            [
                3,
                'true_false',
                '[CONTOH] Matahari terbit dari sebelah timur.',
                1,
                'all_or_nothing',
                'Benar',
                'Salah',
                '',
                '',
                '',
                'A',
                '',
                'Bumi berotasi dari barat ke timur sehingga matahari tampak terbit dari timur.',
            ],
            [
                4,
                'match',
                '[CONTOH] Pasangkan nama negara dengan ibu kotanya!',
                1,
                'proporsional',
                '',
                '',
                '',
                '',
                '',
                '',
                "Indonesia = Jakarta\nJepang = Tokyo\nInggris = London\nPrancis = Paris",
                'Pasangan ibu kota negara di dunia.',
            ],
            [
                5,
                'essay',
                '[CONTOH] Jelaskan fungsi klorofil pada proses fotosintesis tumbuhan!',
                5,
                'all_or_nothing',
                '',
                '',
                '',
                '',
                '',
                'Klorofil berfungsi untuk menyerap energi cahaya matahari yang diperlukan dalam reaksi fotosintesis untuk menghasilkan glukosa dan oksigen.',
                '',
                'Klorofil adalah zat hijau daun yang penting dalam fotosintesis.',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 16,  // Tipe Soal
            'C' => 45,  // Teks Soal
            'D' => 8,   // Poin
            'E' => 16,  // Mode Penilaian
            'F' => 20,  // Opsi A
            'G' => 20,  // Opsi B
            'H' => 20,  // Opsi C
            'I' => 20,  // Opsi D
            'J' => 20,  // Opsi E
            'K' => 22,  // Kunci Jawaban
            'L' => 35,  // Pasangan Menjodohkan
            'M' => 30,  // Pembahasan
        ];
    }

    public function styles(Worksheet $sheet)
    {
        if (!$this->ujian) {
            // Baris contoh pada template dibuat italic abu-abu
            $sheet->getStyle('A2:M6')->getFont()->setItalic(true)->getColor()->setRGB('64748B');
        }

        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'M';

                // Header styling (baris 1)
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4F46E5'], // Indigo-600
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '4338CA']],
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(32);
                $sheet->freezePane('A2');

                // Text wrapping & top alignment untuk kolom teks dan pasangan
                $highestRow = max(10, $sheet->getHighestRow());
                $sheet->getStyle("C2:C{$highestRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("L2:L{$highestRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("M2:M{$highestRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("A2:A{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("B2:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("D2:D{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("E2:E{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);

                // Dropdown validasi Tipe Soal (kolom B) & Mode Penilaian (kolom E)
                for ($row = 2; $row <= 150; $row++) {
                    $valTipe = $sheet->getCell("B{$row}")->getDataValidation();
                    $valTipe->setType(DataValidation::TYPE_LIST);
                    $valTipe->setErrorStyle(DataValidation::STYLE_STOP);
                    $valTipe->setAllowBlank(false);
                    $valTipe->setShowDropDown(true);
                    $valTipe->setShowErrorMessage(true);
                    $valTipe->setErrorTitle('Tipe Soal Tidak Valid');
                    $valTipe->setError('Pilih tipe soal: mcq, mcq_complex, true_false, match, atau essay.');
                    $valTipe->setFormula1('"mcq,mcq_complex,true_false,match,essay"');

                    $valMode = $sheet->getCell("E{$row}")->getDataValidation();
                    $valMode->setType(DataValidation::TYPE_LIST);
                    $valMode->setErrorStyle(DataValidation::STYLE_STOP);
                    $valMode->setAllowBlank(true);
                    $valMode->setShowDropDown(true);
                    $valMode->setShowErrorMessage(true);
                    $valMode->setErrorTitle('Mode Penilaian Tidak Valid');
                    $valMode->setError('Pilih mode: all_or_nothing atau proporsional.');
                    $valMode->setFormula1('"all_or_nothing,proporsional"');
                }

                $sheet->setAutoFilter("A1:{$lastCol}1");
            },
        ];
    }
}
