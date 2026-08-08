<?php

namespace App\Services\Keuangan;

use App\Models\SppPembayaran;
use App\Support\TahunAjaran;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Antrian prioritas verifikasi SPP (A1) — skor deterministik server-side.
 */
class SppVerificationQueue
{
    /**
     * Ambil pembayaran menunggu verifikasi, diurutkan prioritas tertinggi dulu.
     *
     * @return Collection<int, array{pembayaran:SppPembayaran, skor:int, alasan:array<string,string>}>
     */
    public function prioritized(string $tahunAjaran, ?string $search = null, int $limit = 200): Collection
    {
        $rows = SppPembayaran::with('siswa.kelas')
            ->where('tahun_ajaran', $tahunAjaran)
            ->whereIn('status', [SppPembayaran::STATUS_MENUNGGU, SppPembayaran::STATUS_TERVERIFIKASI])
            ->when($search, function ($q) use ($search) {
                $q->whereHas('siswa', function ($s) use ($search) {
                    $s->where('nama', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhereHas('kelas', fn ($k) => $k->where('tingkat', 'like', "%{$search}%")->orWhere('kelas', 'like', "%{$search}%"));
                });
            })
            ->get();

        return $rows
            ->map(fn (SppPembayaran $p) => $this->score($p))
            ->sortByDesc('skor')
            ->values()
            ->take($limit);
    }

    /**
     * Kelompokkan per batch_id (atau uuid tunggal) setelah diurutkan prioritas.
     *
     * @return Collection<int, Collection<int, array{pembayaran:SppPembayaran, skor:int, alasan:array<string,string>}>>
     */
    public function prioritizedGroups(string $tahunAjaran, ?string $search = null): Collection
    {
        $scored = $this->prioritized($tahunAjaran, $search);

        return $scored
            ->groupBy(fn ($item) => $item['pembayaran']->batch_id ?? $item['pembayaran']->uuid)
            ->sortByDesc(fn ($group) => $group->max('skor'))
            ->values();
    }

    /**
     * @return array{pembayaran:SppPembayaran, skor:int, alasan:array<string,string>}
     */
    public function score(SppPembayaran $p): array
    {
        $cfg = config('keuangan-ai.prioritas');
        $bobot = $cfg['bobot'];
        $alasan = [];

        // 1. Hari terlambat (jatuh tempo lewat)
        $hariTerlambat = 0;
        if ($p->jatuh_tempo && $p->jatuh_tempo->isPast()) {
            $hariTerlambat = (int) $p->jatuh_tempo->diffInDays(now()->startOfDay());
        } else {
            $tglJatuh = TahunAjaran::tanggal($p->tahun_ajaran, $p->bulan)->endOfMonth();
            if ($tglJatuh->isPast()) {
                $hariTerlambat = (int) $tglJatuh->diffInDays(now()->startOfDay());
            }
        }
        $skorTerlambat = min(100, $hariTerlambat * 5);
        if ($hariTerlambat > 0) {
            $alasan['terlambat'] = "{$hariTerlambat} hari lewat jatuh tempo";
        }

        // 2. Nominal (normalisasi ke 0-100)
        $ref = max(1, (int) ($cfg['nominal_referensi'] ?? 500000));
        $skorNominal = min(100, (int) (($p->nominal / $ref) * 100));
        $alasan['nominal'] = 'Rp '.number_format($p->nominal, 0, ',', '.');

        // 3. Usia bukti (sejak updated_at)
        $usiaHari = (int) $p->updated_at->diffInDays(now());
        $maxUsia = max(1, (int) ($cfg['usia_maks_hari'] ?? 14));
        $skorUsia = min(100, (int) (($usiaHari / $maxUsia) * 100));
        if ($usiaHari > 0) {
            $alasan['usia'] = "Bukti menunggu {$usiaHari} hari";
        }

        // 4. Status (menunggu lebih prioritas dari terverifikasi)
        $skorStatus = $p->status === SppPembayaran::STATUS_MENUNGGU ? 100 : 50;
        $alasan['status'] = $p->status === SppPembayaran::STATUS_MENUNGGU
            ? 'Menunggu cek bukti'
            : 'Menunggu validasi rekening koran';

        $totalBobot = array_sum($bobot) ?: 100;
        $skor = (int) round(
            ($skorTerlambat * $bobot['hari_terlambat']
                + $skorNominal * $bobot['nominal']
                + $skorUsia * $bobot['usia_bukti']
                + $skorStatus * $bobot['status']) / $totalBobot
        );

        return [
            'pembayaran' => $p,
            'skor'       => $skor,
            'alasan'     => $alasan,
        ];
    }
}
