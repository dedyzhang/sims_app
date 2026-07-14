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

class GuruTemplateExport implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    /** Kolom dalam bahasa Indonesia yang rapi */
    public array $columns = [
        'Nama Lengkap', 'NIK (Nomor Induk Karyawan)', 'NIP (opsional)', 'Jenis Kelamin (L/P)',
        'Tempat Lahir', 'Tanggal Lahir (YYYY-MM-DD)', 'Agama', 'Alamat',
        'Tingkat Studi', 'Program Studi', 'Universitas', 'Tahun Tamat',
        'TMT Mengajar (YYYY-MM-DD)', 'TMT SMP (YYYY-MM-DD)', 'No. Telp',
    ];

    public function title(): string
    {
        return 'Data Guru';
    }

    public function headings(): array
    {
        return $this->columns;
    }

    /** Dua baris contoh (diawali "CONTOH -" agar otomatis dilewati saat import) */
    public function array(): array
    {
        return [
            ['CONTOH - Budi Santoso, S.Pd', '1234567890', '198001012005011001', 'L', 'Jakarta', '1980-01-01', 'Islam', 'Jl. Sudirman No. 10',
             'S1', 'Pendidikan Matematika', 'Universitas Negeri Jakarta', '2004',
             '2005-01-01', '2010-07-01', '081234567890'],
            ['CONTOH - Siti Aminah, S.E', '0987654321', '', 'P', 'Bandung', '1985-05-15', 'Islam', 'Jl. Melati No. 5',
             'S1', 'Akuntansi', 'Universitas Padjadjaran', '2007',
             '2008-01-01', '2015-07-01', '081298765432'],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28, 'B' => 20, 'C' => 22, 'D' => 18, 'E' => 16, 'F' => 26,
            'G' => 14, 'H' => 32, 'I' => 14, 'J' => 24, 'K' => 28, 'L' => 14,
            'M' => 26, 'N' => 24, 'O' => 18,
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Baris contoh dibuat italic abu-abu
        $sheet->getStyle('A2:O3')->getFont()->setItalic(true)->getColor()->setRGB('94A3B8');
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = 'O';

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
