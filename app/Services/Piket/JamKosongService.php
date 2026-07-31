<?php

namespace App\Services\Piket;

use App\Models\Jadwal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Hitung jam pelajaran & kelas yang kosong akibat guru tidak hadir.
 *
 * `jadwals` rekuren per hari-dalam-minggu (kolom `hari`, bukan tanggal spesifik) — dipakai
 * bersama oleh GuruTidakHadirController (Fase 2) dan PenugasanPenggantiController (Fase 3),
 * jadi ditaruh di satu tempat supaya konversi tanggal->hari tidak berbeda antar keduanya.
 */
class JamKosongService
{
    /** @return Collection<int, Jadwal> */
    public function untukGuru(string $idGuru, string $tanggal): Collection
    {
        $hari = Carbon::parse($tanggal)->dayOfWeekIso; // 1=Senin ... 7=Minggu

        return Jadwal::with(['kelas:uuid,tingkat,kelas', 'pelajaran:uuid,nama'])
            ->where('id_guru', $idGuru)
            ->where('hari', $hari)
            ->orderBy('jam_mulai')
            ->get();
    }

    public function format(Jadwal $j): array
    {
        return [
            'id_jadwal' => $j->uuid,
            'jam_ke' => $j->jam_ke,
            'jam_mulai' => $j->jam_mulai,
            'jam_selesai' => $j->jam_selesai,
            'kelas' => trim(($j->kelas?->tingkat ?? '').' '.($j->kelas?->kelas ?? '')) ?: '-',
            'pelajaran' => $j->pelajaran?->nama ?? $j->keterangan ?? '-',
        ];
    }

    /** Guru yang tidak sedang mengajar di hari+jam_ke yang sama, dan tidak sedang tidak-hadir hari itu. */
    public function guruTersediaUntuk(Jadwal $slot, string $tanggal): Collection
    {
        $sibuk = Jadwal::where('hari', $slot->hari)
            ->where('jam_ke', $slot->jam_ke)
            ->whereNotNull('id_guru')
            ->pluck('id_guru');

        $tidakHadir = \App\Models\GuruTidakHadir::where('tanggal', $tanggal)->pluck('id_guru');

        return \App\Models\Guru::whereNotIn('uuid', $sibuk->merge($tidakHadir)->unique())
            ->orderBy('nama')
            ->get(['uuid', 'nama']);
    }
}
