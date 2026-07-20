<?php

namespace App\Exports;

use App\Support\CetakExcelStyle;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Kredensial login (username+password PLAINTEXT) siswa & orang tua yang baru
 * dibuat lewat satu batch Import Siswa. Hanya bisa dibuat SEKALI tepat setelah
 * import — setelah itu password sudah ter-hash & tidak bisa diambil lagi.
 */
class SiswaImportKredensialExport implements FromArray, WithColumnWidths, WithTitle, WithEvents
{
    private const HEADINGS = ['No', 'Nama', 'NIS', 'Username Siswa', 'Password Siswa', 'Username Ortu', 'Password Ortu'];

    public function __construct(private array $kredensial, private string $judul = 'KREDENSIAL LOGIN SISWA BARU (IMPORT)')
    {
    }

    public function array(): array
    {
        $rows = [self::HEADINGS];
        foreach ($this->kredensial as $i => $k) {
            $rows[] = [
                $i + 1,
                $k['nama'],
                $k['nis'],
                $k['username_siswa'],
                $k['password_siswa'],
                $k['username_ortu'],
                $k['password_ortu'],
            ];
        }
        return $rows;
    }

    public function title(): string
    {
        return 'Kredensial Siswa Baru';
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 26, 'C' => 12, 'D' => 18, 'E' => 16, 'F' => 18, 'G' => 16];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                CetakExcelStyle::kopDanTabel(
                    $event->sheet->getDelegate(),
                    $this->judul,
                    count(self::HEADINGS),
                    count($this->kredensial),
                    'Simpan file ini dengan aman — password tidak bisa ditampilkan ulang setelah ini'
                );
            },
        ];
    }
}
