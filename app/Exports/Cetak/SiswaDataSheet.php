<?php

namespace App\Exports\Cetak;

use App\Models\Siswa;
use App\Support\CetakExcelStyle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class SiswaDataSheet implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithTitle, WithEvents
{
    public const HEADINGS = [
        'No', 'Nama', 'NIS', 'NISN', 'JK', 'Kelas', 'Tempat Lahir', 'Tanggal Lahir', 'Agama', 'Alamat', 'No HP',
        'Nama Ayah', 'Pekerjaan Ayah', 'No Telp Ayah',
        'Nama Ibu', 'Pekerjaan Ibu', 'No Telp Ibu',
        'Nama Wali', 'Pekerjaan Wali', 'No Telp Wali',
        'Sekolah Asal',
    ];

    /** @param string $idKelas uuid Kelas, atau 'semua' */
    public function __construct(private string $idKelas)
    {
    }

    public function collection()
    {
        $q = Siswa::with('kelas')->orderBy('nama');
        if ($this->idKelas !== 'semua') {
            $q->where('id_kelas', $this->idKelas);
        }
        return $q->get();
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    public function title(): string
    {
        return 'Data Siswa';
    }

    public function map($s): array
    {
        static $no = 0;
        $no++;

        return [
            $no,
            $s->nama,
            $s->nis,
            $s->nisn,
            $s->jk,
            $s->kelas ? "{$s->kelas->tingkat}{$s->kelas->kelas}" : '-',
            $s->tempat_lahir,
            $s->tanggal_lahir,
            $s->agama,
            $s->alamat,
            $s->no_handphone,
            $s->nama_ayah,
            $s->pekerjaan_ayah,
            $s->no_telp_ayah,
            $s->nama_ibu,
            $s->pekerjaan_ibu,
            $s->no_telp_ibu,
            $s->nama_wali,
            $s->pekerjaan_wali,
            $s->no_telp_wali,
            $s->sekolah_asal,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5, 'B' => 24, 'C' => 12, 'D' => 12, 'E' => 6, 'F' => 8,
            'G' => 16, 'H' => 14, 'I' => 10, 'J' => 28, 'K' => 14,
            'L' => 22, 'M' => 18, 'N' => 14,
            'O' => 22, 'P' => 18, 'Q' => 14,
            'R' => 22, 'S' => 18, 'T' => 14, 'U' => 22,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                CetakExcelStyle::kopDanTabel($event->sheet->getDelegate(), 'DATA SISWA', count(self::HEADINGS), $this->collection()->count());
            },
        ];
    }
}
