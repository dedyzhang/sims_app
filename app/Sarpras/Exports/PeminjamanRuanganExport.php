<?php

namespace App\Sarpras\Exports;

use App\Sarpras\Models\Peminjaman;
use App\Support\CetakExcelStyle;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class PeminjamanRuanganExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithTitle, WithEvents
{
    public const HEADINGS = [
        'No',
        'Kode Booking',
        'Ruangan',
        'Nama Peminjam',
        'Username',
        'Kegiatan / Keperluan',
        'Waktu Mulai',
        'Waktu Selesai',
        'Durasi',
        'Status',
        'Disetujui Oleh',
        'Catatan / Alasan Tolak',
        'Waktu Pengajuan',
    ];

    public function __construct(
        private ?string $dari = null,
        private ?string $sampai = null,
        private ?string $status = null,
        private ?string $ruanganId = null,
    ) {}

    public function collection()
    {
        return Peminjaman::with([
                'ruangan:id,kode,nama,gedung,lantai',
                'peminjam:uuid,username',
                'peminjam.guru:uuid,id_login,nama',
                'penyetuju:uuid,username',
                'penyetuju.guru:uuid,id_login,nama',
            ])
            ->whereNotNull('ruangan_id')
            ->when($this->dari, fn ($q, $v) => $q->whereDate('mulai', '>=', $v))
            ->when($this->sampai, fn ($q, $v) => $q->whereDate('mulai', '<=', $v))
            ->when($this->status, fn ($q, $v) => $q->where('status', $v))
            ->when($this->ruanganId, fn ($q, $v) => $q->where('ruangan_id', $v))
            ->orderByDesc('mulai')
            ->get();
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    public function title(): string
    {
        return 'Peminjaman Ruangan';
    }

    public function map($p): array
    {
        static $no = 0;
        $no++;

        $ruangan = $p->ruangan ? "{$p->ruangan->kode} - {$p->ruangan->nama}" : '-';
        if ($p->ruangan && ($p->ruangan->gedung || $p->ruangan->lantai)) {
            $lokasi = array_filter([
                $p->ruangan->gedung ? "Gedung {$p->ruangan->gedung}" : null,
                $p->ruangan->lantai ? "Lt. {$p->ruangan->lantai}" : null,
            ]);
            if (! empty($lokasi)) {
                $ruangan .= ' (' . implode(', ', $lokasi) . ')';
            }
        }

        $namaPeminjam = $p->peminjam?->name ?: ($p->peminjam?->guru?->nama ?: ($p->peminjam?->username ?: '-'));
        $usernamePeminjam = $p->peminjam?->username ?: '-';

        $waktuMulai = $p->mulai ? $p->mulai->format('d/m/Y H:i') : '-';
        $waktuSelesai = $p->selesai ? $p->selesai->format('d/m/Y H:i') : '-';

        $durasi = '-';
        if ($p->mulai && $p->selesai) {
            $minutes = $p->mulai->diffInMinutes($p->selesai);
            $hours = intdiv($minutes, 60);
            $remMinutes = $minutes % 60;
            $parts = [];
            if ($hours > 0) {
                $parts[] = "{$hours} jam";
            }
            if ($remMinutes > 0) {
                $parts[] = "{$remMinutes} mnt";
            }
            $durasi = ! empty($parts) ? implode(' ', $parts) : '< 1 mnt';
        }

        $status = match ($p->status) {
            'diajukan' => 'Menunggu',
            'dipinjam' => 'Dipinjam',
            'dikembalikan' => 'Selesai',
            'ditolak' => 'Ditolak',
            'terlambat' => 'Terlambat',
            default => ucfirst((string) $p->status),
        };

        $disetujuiOleh = $p->penyetuju?->name ?: ($p->penyetuju?->guru?->nama ?: ($p->penyetuju?->username ?: '-'));
        $alasanTolak = $p->alasan_tolak ?: '-';
        $waktuPengajuan = $p->created_at ? $p->created_at->format('d/m/Y H:i') : '-';

        return [
            $no,
            $p->kode ?: '-',
            $ruangan,
            $namaPeminjam,
            $usernamePeminjam,
            $p->keperluan ?: '-',
            $waktuMulai,
            $waktuSelesai,
            $durasi,
            $status,
            $disetujuiOleh,
            $alasanTolak,
            $waktuPengajuan,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 20,  // Kode Booking
            'C' => 28,  // Ruangan
            'D' => 26,  // Nama Peminjam
            'E' => 16,  // Username
            'F' => 36,  // Kegiatan / Keperluan
            'G' => 18,  // Waktu Mulai
            'H' => 18,  // Waktu Selesai
            'I' => 14,  // Durasi
            'J' => 15,  // Status
            'K' => 24,  // Disetujui Oleh
            'L' => 28,  // Catatan / Alasan Tolak
            'M' => 18,  // Waktu Pengajuan
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $count = $this->collection()->count();
                CetakExcelStyle::kopDanTabel(
                    $event->sheet->getDelegate(),
                    'RIWAYAT PEMINJAMAN RUANGAN',
                    count(self::HEADINGS),
                    $count,
                    'Arsip Jadwal & Peminjaman Ruangan'
                );

                $event->sheet->getDelegate()->getStyle('A:M')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getDelegate()->getStyle('A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('G:I')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('J')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('M')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('F')->getAlignment()->setWrapText(true);
                $event->sheet->getDelegate()->getStyle('L')->getAlignment()->setWrapText(true);
            },
        ];
    }
}
