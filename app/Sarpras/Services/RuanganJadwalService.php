<?php

namespace App\Sarpras\Services;

use App\Sarpras\Models\Peminjaman;
use Illuminate\Support\Carbon;

/**
 * Pengecekan bentrok jadwal ruangan — satu sumber untuk peminjaman terpadu.
 */
class RuanganJadwalService
{
    /**
     * @param  Carbon|string  $mulai
     * @param  Carbon|string  $selesai
     */
    public function bentrok(string $ruanganId, $mulai, $selesai, ?string $kecualiPeminjamanId = null): bool
    {
        $mulai = Carbon::parse($mulai);
        $selesai = Carbon::parse($selesai);

        return Peminjaman::query()
            ->bentrok($ruanganId, $mulai, $selesai, $kecualiPeminjamanId)
            ->exists();
    }
}
