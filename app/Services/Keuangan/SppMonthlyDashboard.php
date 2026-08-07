<?php

namespace App\Services\Keuangan;

use App\Models\SppPembayaran;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard pendapatan SPP bulanan (A3) — agregat rule-based, BIGINT.
 */
class SppMonthlyDashboard
{
    /**
     * Ringkasan pendapatan per bulan pada satu tahun ajaran.
     *
     * @return array{
     *     tahun_ajaran: string,
     *     bulan: array<int, array{idx:int, label:string, jumlah:int, total:int}>,
     *     grand_total: int,
     *     grand_jumlah: int
     * }
     */
    public function ringkasanTahun(string $tahunAjaran): array
    {
        $rows = SppPembayaran::where('tahun_ajaran', $tahunAjaran)
            ->where('status', SppPembayaran::STATUS_LUNAS)
            ->select('bulan', DB::raw('COUNT(*) as jumlah'), DB::raw('SUM(nominal) as total'))
            ->groupBy('bulan')
            ->get()
            ->keyBy('bulan');

        $bulan = [];
        $grandTotal = 0;
        $grandJumlah = 0;

        foreach (\App\Support\TahunAjaran::BULAN as $idx => [$label, $calMonth]) {
            $row = $rows->get($idx);
            $jumlah = (int) ($row->jumlah ?? 0);
            $total = (int) ($row->total ?? 0);
            $bulan[$idx] = [
                'idx'    => $idx,
                'label'  => $label,
                'total'  => $total,
                'jumlah' => $jumlah,
            ];
            $grandTotal += $total;
            $grandJumlah += $jumlah;
        }

        return [
            'tahun_ajaran'  => $tahunAjaran,
            'bulan'         => $bulan,
            'grand_total'   => $grandTotal,
            'grand_jumlah'  => $grandJumlah,
        ];
    }

    /**
     * Ringkasan satu bulan kalender (untuk filter bulan/tahun).
     *
     * @return array{jumlah:int, total:int, menunggu:int, terverifikasi:int, ditolak:int}
     */
    public function ringkasanBulanKalender(int $tahun, int $bulan): array
    {
        // Cari tahun ajaran yg mencakup bulan kalender ini
        $ta = $bulan >= 7
            ? "{$tahun}/".($tahun + 1)
            : ($tahun - 1)."/{$tahun}";

        $idxBulan = $bulan >= 7 ? $bulan - 6 : $bulan + 6;

        $base = SppPembayaran::where('tahun_ajaran', $ta)->where('bulan', $idxBulan);

        return [
            'jumlah'        => (int) (clone $base)->where('status', SppPembayaran::STATUS_LUNAS)->count(),
            'total'         => (int) (clone $base)->where('status', SppPembayaran::STATUS_LUNAS)->sum('nominal'),
            'menunggu'      => (int) (clone $base)->where('status', SppPembayaran::STATUS_MENUNGGU)->count(),
            'terverifikasi' => (int) (clone $base)->where('status', SppPembayaran::STATUS_TERVERIFIKASI)->count(),
            'ditolak'       => (int) (clone $base)->where('status', SppPembayaran::STATUS_DITOLAK)->count(),
        ];
    }
}
