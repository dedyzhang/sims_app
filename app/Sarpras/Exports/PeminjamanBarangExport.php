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

class PeminjamanBarangExport implements FromCollection, WithHeadings, WithMapping, WithColumnWidths, WithTitle, WithEvents
{
    public const HEADINGS = [
        'No',
        'Kode Peminjaman',
        'Nama Peminjam',
        'Username',
        'Daftar Barang Dipinjam',
        'Total Unit',
        'Keperluan',
        'Tgl Pinjam / Mulai',
        'Rencana Kembali',
        'Tgl Kembali Aktual',
        'Status',
        'Disetujui Oleh',
        'Catatan / Alasan Tolak',
        'Waktu Pengajuan',
    ];

    public function __construct(
        private ?string $dari = null,
        private ?string $sampai = null,
        private ?string $status = null,
        private ?string $userId = null,
        private bool $canKelola = true,
    ) {}

    public function collection()
    {
        return Peminjaman::with([
                'items.aset:id,kode,nama',
                'peminjam:uuid,username',
                'peminjam.guru:uuid,id_login,nama',
                'penyetuju:uuid,username',
                'penyetuju.guru:uuid,id_login,nama',
            ])
            ->where(fn ($q) => $q->has('items')->orWhereNull('ruangan_id'))
            ->when(! $this->canKelola && $this->userId, fn ($q) => $q->where('peminjam_id', $this->userId))
            ->when($this->dari, fn ($q, $v) => $q->where(fn ($sub) => $sub->whereDate('mulai', '>=', $v)->orWhereDate('tgl_pinjam', '>=', $v)))
            ->when($this->sampai, fn ($q, $v) => $q->where(fn ($sub) => $sub->whereDate('mulai', '<=', $v)->orWhereDate('tgl_pinjam', '<=', $v)))
            ->when($this->status, fn ($q, $v) => $q->where('status', $v))
            ->latest()
            ->get();
    }

    public function headings(): array
    {
        return self::HEADINGS;
    }

    public function title(): string
    {
        return 'Peminjaman Barang';
    }

    public function map($p): array
    {
        static $no = 0;
        $no++;

        $namaPeminjam = $p->peminjam?->name ?: ($p->peminjam?->guru?->nama ?: ($p->peminjam?->username ?: '-'));
        $usernamePeminjam = $p->peminjam?->username ?: '-';

        $itemsText = $p->items->map(function ($it, $idx) {
            $nama = $it->aset?->nama ?? 'Barang';
            $kode = $it->aset?->kode ? "[{$it->aset->kode}]" : '';
            $kondisi = $it->kondisi_pinjam ? " (Kondisi: {$it->kondisi_pinjam})" : '';
            return ($idx + 1) . ". {$nama} {$kode} - {$it->qty} unit{$kondisi}";
        })->implode("\n");

        if (empty($itemsText)) {
            $itemsText = '-';
        }

        $totalUnit = $p->items->sum('qty') ?: ($p->items->count() ?: 0);

        $tglPinjam = $p->mulai ? $p->mulai->format('d/m/Y H:i') : ($p->tgl_pinjam ? $p->tgl_pinjam->format('d/m/Y') : '-');
        $rencanaKembali = $p->selesai ? $p->selesai->format('d/m/Y H:i') : ($p->tgl_kembali_rencana ? $p->tgl_kembali_rencana->format('d/m/Y') : '-');
        $kembaliAktual = $p->tgl_kembali_aktual ? $p->tgl_kembali_aktual->format('d/m/Y') : '-';

        $status = match ($p->status) {
            'diajukan' => 'Menunggu',
            'dipinjam' => 'Dipinjam',
            'dikembalikan' => 'Selesai',
            'ditolak' => 'Ditolak',
            'terlambat' => 'Terlambat',
            default => ucfirst((string) $p->status),
        };

        $disetujuiOleh = $p->penyetuju?->name ?: ($p->penyetuju?->guru?->nama ?: ($p->penyetuju?->username ?: '-'));
        $catatan = $p->alasan_tolak ?: '-';
        $waktuPengajuan = $p->created_at ? $p->created_at->format('d/m/Y H:i') : '-';

        return [
            $no,
            $p->kode ?: '-',
            $namaPeminjam,
            $usernamePeminjam,
            $itemsText,
            $totalUnit,
            $p->keperluan ?: '-',
            $tglPinjam,
            $rencanaKembali,
            $kembaliAktual,
            $status,
            $disetujuiOleh,
            $catatan,
            $waktuPengajuan,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,   // No
            'B' => 20,  // Kode Peminjaman
            'C' => 26,  // Nama Peminjam
            'D' => 16,  // Username
            'E' => 44,  // Daftar Barang
            'F' => 12,  // Total Unit
            'G' => 36,  // Keperluan
            'H' => 18,  // Tgl Pinjam
            'I' => 18,  // Rencana Kembali
            'J' => 18,  // Kembali Aktual
            'K' => 15,  // Status
            'L' => 24,  // Disetujui Oleh
            'M' => 28,  // Catatan
            'N' => 18,  // Waktu Pengajuan
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $count = $this->collection()->count();
                CetakExcelStyle::kopDanTabel(
                    $event->sheet->getDelegate(),
                    'RIWAYAT PEMINJAMAN BARANG INVENTARIS',
                    count(self::HEADINGS),
                    $count,
                    'Arsip Peminjaman Sarpras'
                );

                $event->sheet->getDelegate()->getStyle('A:N')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $event->sheet->getDelegate()->getStyle('A')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('F')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('H:K')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('N')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $event->sheet->getDelegate()->getStyle('E')->getAlignment()->setWrapText(true);
                $event->sheet->getDelegate()->getStyle('G')->getAlignment()->setWrapText(true);
                $event->sheet->getDelegate()->getStyle('M')->getAlignment()->setWrapText(true);
            },
        ];
    }
}
