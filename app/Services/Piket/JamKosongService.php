<?php

namespace App\Services\Piket;

use App\Models\Jadwal;
use App\Models\PenugasanPengganti;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * Guru yang kosong pada slot jadwal sekolah tersebut, tidak hadir, dan belum
     * dipakai untuk menggantikan kelas lain pada waktu yang sama.
     */
    public function guruTersediaUntuk(Jadwal $slot, string $tanggal): Collection
    {
        $sibuk = $this->slotQuery(Jadwal::query(), $slot)
            ->whereNotNull('id_guru')
            ->pluck('id_guru');

        $tidakHadir = \App\Models\GuruTidakHadir::whereDate('tanggal', $tanggal)->pluck('id_guru');
        $sudahDitugaskan = PenugasanPengganti::query()
            ->whereIn('status', ['ditugaskan', 'selesai'])
            ->whereHas('guruTidakHadir', fn ($query) => $query->whereDate('tanggal', $tanggal))
            ->whereHas('jadwal', fn ($query) => $this->slotQuery($query, $slot))
            ->get(['id_guru_pengganti', 'id_guru_piket'])
            ->flatMap(fn (PenugasanPengganti $penugasan) => [
                $penugasan->id_guru_pengganti,
                $penugasan->id_guru_piket,
            ])
            ->filter()
            ->unique();

        return \App\Models\Guru::whereNotIn('uuid', $sibuk->merge($tidakHadir)->merge($sudahDitugaskan)->unique())
            ->orderBy('nama')
            ->get(['uuid', 'nama']);
    }

    /** Cocokkan slot dengan struktur jadwal sekolah, bukan hanya nomor jam legacy. */
    private function slotQuery(Builder $query, Jadwal $slot): Builder
    {
        $query->where('hari', $slot->hari);

        if ($slot->id_jam) {
            return $query->where('id_jam', $slot->id_jam);
        }

        if ($slot->jam_ke !== null) {
            return $query->where('jam_ke', $slot->jam_ke);
        }

        return $query
            ->where('jam_mulai', $slot->jam_mulai)
            ->where('jam_selesai', $slot->jam_selesai);
    }
}
