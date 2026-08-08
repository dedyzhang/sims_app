<?php

namespace App\Services\Keuangan;

use App\Models\SppPembayaran;
use Illuminate\Support\Collection;

/**
 * Paket kerja verifikasi SPP untuk arsip sekolah (Fase C2).
 */
class SppVerifikasiPaketService
{
    /**
     * Baris ekspor antrian & riwayat verifikasi pada satu tahun ajaran.
     *
     * @return Collection<int, SppPembayaran>
     */
    public function baris(string $tahunAjaran, ?string $status = null): Collection
    {
        return SppPembayaran::with(['siswa.kelas', 'verifikator'])
            ->where('tahun_ajaran', $tahunAjaran)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->whereIn('status', [
                SppPembayaran::STATUS_MENUNGGU,
                SppPembayaran::STATUS_TERVERIFIKASI,
                SppPembayaran::STATUS_LUNAS,
                SppPembayaran::STATUS_DITOLAK,
            ])
            ->orderByDesc('updated_at')
            ->get();
    }

    public function labelStatus(string $status): string
    {
        return match ($status) {
            SppPembayaran::STATUS_MENUNGGU      => 'Menunggu verifikasi',
            SppPembayaran::STATUS_TERVERIFIKASI => 'Terverifikasi (tunggu bank)',
            SppPembayaran::STATUS_LUNAS         => 'Lunas',
            SppPembayaran::STATUS_DITOLAK       => 'Ditolak',
            default                             => $status,
        };
    }
}
