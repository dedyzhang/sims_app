<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class SiswaTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    /** Kolom dalam bahasa Indonesia yang rapi. Kolom Wali opsional (diisi bila anak diasuh wali). */
    public array $columns = [
        'Nama Lengkap', 'NIS', 'NISN', 'Jenis Kelamin (L/P)',
        'Tempat Lahir', 'Tanggal Lahir (YYYY-MM-DD)', 'Agama', 'No. HP',
        'Alamat', 'Nama Ayah', 'Pekerjaan Ayah', 'No. HP Ayah',
        'Nama Ibu', 'Pekerjaan Ibu', 'No. HP Ibu',
        'Nama Wali', 'Pekerjaan Wali', 'No. HP Wali',
        'Asal Sekolah', 'SPP',
    ];

    public function title(): string
    {
        return 'Data Siswa';
    }

    public function headings(): array
    {
        return $this->columns;
    }

    /** Dua baris contoh (diawali "CONTOH -" agar otomatis dilewati saat import) */
    public function array(): array
    {
        return [
            ['CONTOH - Ahmad Fauzi', '', '0098765432', 'L', 'Jakarta', '2011-03-12', 'Islam', '081234567890',
             'Jl. Melati No. 10, Jakarta', 'Budi Santoso', 'Wiraswasta', '081200000001',
             'Siti Aminah', 'Ibu Rumah Tangga', '081200000002',
             'Hasan (Paman)', 'Wiraswasta', '081200000003',
             'SD Negeri 1 Jakarta', '350000'],
            ['CONTOH - Putri Lestari', '24001', '0091234567', 'P', 'Bandung', '2011-07-25', 'Kristen', '081298765432',
             'Jl. Mawar No. 5, Bandung', 'Anton Wijaya', 'PNS', '081300000001',
             'Dewi Sartika', 'Guru', '081300000002',
             '', '', '',
             'SD Negeri 3 Bandung', '350000'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 24, 'B' => 14, 'C' => 16, 'D' => 18, 'E' => 16, 'F' => 26,
            'G' => 14, 'H' => 18, 'I' => 32, 'J' => 20, 'K' => 18, 'L' => 18,
            'M' => 20, 'N' => 18, 'O' => 18,
            'P' => 20, 'Q' => 18, 'R' => 18,
            'S' => 24, 'T' => 12,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Baris contoh dibuat italic abu-abu
        $sheet->getStyle('A2:T3')->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'T';

                // ===== Header style (baris 1) =====
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '4F46E5'],
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
                $sheet->getRowDimension(1)->setRowHeight(34);
                $sheet->freezePane('A2');

                // ===== Border untuk area data contoh =====
                $sheet->getStyle("A1:{$lastCol}3")->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                    ],
                ]);

                // ===== Dropdown validasi Jenis Kelamin (kolom D) & Agama (kolom G) =====
                for ($row = 2; $row <= 200; $row++) {
                    $validation = $sheet->getCell("D{$row}")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_STOP);
                    $validation->setAllowBlank(false);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setErrorTitle('Input salah');
                    $validation->setError('Pilih L atau P.');
                    $validation->setFormula1('"L,P"');

                    // Agama boleh kosong; kalau diisi harus salah satu pilihan (dicocokkan
                    // lagi saat impor oleh App\Support\Agama — dua-duanya pakai daftar sama).
                    $validasiAgama = $sheet->getCell("G{$row}")->getDataValidation();
                    $validasiAgama->setType(DataValidation::TYPE_LIST);
                    $validasiAgama->setErrorStyle(DataValidation::STYLE_STOP);
                    $validasiAgama->setAllowBlank(true);
                    $validasiAgama->setShowInputMessage(true);
                    $validasiAgama->setShowErrorMessage(true);
                    $validasiAgama->setShowDropDown(true);
                    $validasiAgama->setErrorTitle('Input salah');
                    $validasiAgama->setError('Pilih salah satu dari daftar agama yang tersedia.');
                    $validasiAgama->setFormula1(\App\Support\Agama::excelFormula());
                }

                // Auto filter header
                $sheet->setAutoFilter("A1:{$lastCol}1");
            },
        ];
    }
}
