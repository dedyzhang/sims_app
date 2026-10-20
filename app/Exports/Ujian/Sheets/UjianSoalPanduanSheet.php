<?php

namespace App\Exports\Ujian\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UjianSoalPanduanSheet implements FromArray, WithHeadings, WithStyles, WithColumnWidths, WithTitle, WithEvents
{
    public function title(): string
    {
        return 'Panduan Pengisian';
    }

    public function headings(): array
    {
        return [
            'Kolom',
            'Sifat',
            'Keterangan & Aturan Format',
            'Contoh Nilai',
        ];
    }

    public function array(): array
    {
        return [
            [
                'No',
                'Opsional',
                'Nomor urutan soal di lembar spreadsheet. Urutan soal sebenarnya saat import akan otomatis disesuaikan secara berurutan.',
                '1',
            ],
            [
                'Tipe Soal',
                'Wajib',
                "Pilihan tipe soal yang didukung sistem:\n• mcq : Pilihan Ganda (tepat satu opsi benar)\n• mcq_complex : Pilihan Ganda Kompleks (satu atau lebih opsi benar)\n• true_false : Benar / Salah (otomatis 2 pilihan: Benar dan Salah)\n• match : Menjodohkan / Mencocokkan pasangan item\n• essay : Esai / Uraian",
                'mcq',
            ],
            [
                'Teks Soal',
                'Wajib',
                "Pertanyaan atau teks soal.\nCatatan: Baris yang teks soalnya diawali kata '[CONTOH]' atau 'CONTOH' akan otomatis diabaikan saat import.",
                'Apa ibu kota negara Indonesia?',
            ],
            [
                'Poin',
                'Opsional',
                'Bobot poin untuk soal ini (angka bulat positif, minimal 1). Jika dikosongkan, sistem default memberi nilai 1.',
                '1',
            ],
            [
                'Mode Penilaian',
                'Opsional',
                "Khusus untuk tipe mcq_complex dan match:\n• all_or_nothing : Siswa wajib menjawab semua dengan benar untuk mendapatkan poin penuh.\n• proporsional : Poin dihitung per opsi atau pasangan yang dijawab benar.\nDefault jika kosong: all_or_nothing.",
                'proporsional',
            ],
            [
                'Opsi A - E',
                'Kondisional',
                "Pilihan jawaban untuk tipe 'mcq' dan 'mcq_complex'.\nMinimal terisi Opsi A dan Opsi B. Opsi C, D, E boleh dikosongkan jika pilihan hanya 2, 3, atau 4.\nUntuk tipe true_false, kolom ini boleh dikosongkan (sistem otomatis mengisi Opsi A: Benar, Opsi B: Salah).",
                'Opsi A: Jakarta, Opsi B: Bandung',
            ],
            [
                'Kunci Jawaban',
                'Kondisional',
                "Format kunci jawaban berdasarkan tipe soal:\n• mcq : Isi satu huruf opsi benar (misal: A atau B atau C).\n• mcq_complex : Isi satu atau beberapa huruf opsi benar dipisahkan koma (misal: A, C atau A, B, D).\n• true_false : Isi 'A' atau 'Benar' jika Benar, atau 'B' atau 'Salah' jika Salah.\n• essay : Teks kunci jawaban atau rubrik penilaian referensi guru.\n• match : Boleh dikosongkan (kunci diisi di kolom Pasangan Menjodohkan).",
                'A, B, D',
            ],
            [
                'Pasangan Menjodohkan',
                'Kondisional',
                "Khusus untuk tipe 'match'. Tuliskan pasangan kiri dan kanan dengan format:\nKiri 1 = Kanan 1\nKiri 2 = Kanan 2\nPisahkan tiap pasangan dengan baris baru (tombol Alt + Enter di Excel). Minimal 2 pasang.",
                "Indonesia = Jakarta\nJepang = Tokyo\nInggris = London",
            ],
            [
                'Pembahasan',
                'Opsional',
                'Teks penjelasan atau pembahasan jawaban yang dapat ditampilkan kepada siswa setelah ujian selesai/dibuka.',
                'Penjelasan rinci mengenai materi soal...',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 22, // Kolom
            'B' => 14, // Sifat
            'C' => 55, // Keterangan
            'D' => 30, // Contoh
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Header styling
                $sheet->getStyle('A1:D1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '334155'], // Slate-700
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '1E293B']],
                    ],
                ]);

                $sheet->getRowDimension(1)->setRowHeight(30);

                // Auto wrap text and top align
                $highestRow = $sheet->getHighestRow();
                $sheet->getStyle("A2:D{$highestRow}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("B2:B{$highestRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_TOP);

                $sheet->getStyle("A1:D{$highestRow}")->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                    ],
                ]);
            },
        ];
    }
}
