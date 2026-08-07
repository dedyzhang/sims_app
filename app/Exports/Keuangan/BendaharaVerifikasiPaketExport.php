<?php

namespace App\Exports\Keuangan;

use App\Models\SppPembayaran;
use App\Services\Keuangan\SppVerifikasiPaketService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;

class BendaharaVerifikasiPaketExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithTitle
{
    public function __construct(
        private Collection $rows,
        private string $tahunAjaran,
        private SppVerifikasiPaketService $labels,
    ) {}

    public static function make(string $tahunAjaran, ?string $status = null): self
    {
        $svc = app(SppVerifikasiPaketService::class);

        return new self($svc->baris($tahunAjaran, $status), $tahunAjaran, $svc);
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'No',
            'NIS',
            'Nama Siswa',
            'Kelas',
            'Bulan Tagihan',
            'Nominal (Rp)',
            'Status',
            'Bank',
            'Tanggal Bayar',
            'Jatuh Tempo',
            'Verifikator',
            'Diverifikasi Pada',
            'Catatan Bendahara',
        ];
    }

    public function title(): string
    {
        return 'Paket Verifikasi SPP';
    }

    /** @param  SppPembayaran  $row */
    public function map($row): array
    {
        static $no = 0;
        $no++;

        $kelas = $row->siswa?->kelas;

        return [
            $no,
            $row->siswa?->nis ?? '-',
            $row->siswa?->nama ?? '-',
            $kelas ? "{$kelas->tingkat}{$kelas->kelas}" : '-',
            $row->label_bulan,
            $row->nominal,
            $this->labels->labelStatus($row->status),
            $row->bank ?? '-',
            $row->tanggal_bayar?->format('d-m-Y') ?? '-',
            $row->jatuh_tempo?->format('d-m-Y') ?? '-',
            $row->verifikator?->username ?? '-',
            $row->diverifikasi_pada?->format('d-m-Y H:i') ?? '-',
            $row->catatan_bendahara ?? '',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 14,
            'C' => 28,
            'D' => 10,
            'E' => 16,
            'F' => 14,
            'G' => 22,
            'H' => 10,
            'I' => 14,
            'J' => 14,
            'K' => 16,
            'L' => 18,
            'M' => 30,
        ];
    }
}
