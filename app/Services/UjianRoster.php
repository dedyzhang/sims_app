<?php

namespace App\Services;

use App\Models\Siswa;
use App\Models\UjianAttempt;
use App\Models\UjianKelas;
use Illuminate\Support\Collection;

/**
 * Roster siswa per kelas ujian, dipadukan dgn attempt-nya (kalau ada) — SEMUA siswa kelas
 * itu selalu punya baris, bukan cuma yg sudah mulai mengerjakan. Dipusatkan di sini supaya
 * dipakai bareng oleh Pemantauan Live, Hasil & Transfer, dan Export Excel Analisis (Fase 6),
 * jadi query "siswa kelas ini + status attempt-nya" tak ditulis ulang 3x terpisah & berisiko
 * beda hasil antar tempat.
 */
class UjianRoster
{
    /**
     * @param  Collection<int, UjianKelas>  $ujianKelasList  Wajib sudah eager-load relasi 'kelas'.
     * @return Collection<int, array{siswa: Siswa, ujianKelas: ?UjianKelas, attempt: ?UjianAttempt}>
     */
    public function untukKelas(Collection $ujianKelasList): Collection
    {
        if ($ujianKelasList->isEmpty()) {
            return collect();
        }

        $siswaList = Siswa::whereIn('id_kelas', $ujianKelasList->pluck('id_kelas'))->get();

        // attempts.id_siswa menyimpan uuid USER (bukan uuid Siswa) — dipakai sbg kunci
        // penjodoh lewat Siswa.id_login. 'dibatalkan' dikecualikan krn siswa ybs SUDAH bisa
        // mulai attempt baru dgn token yg sama (lihat UjianMonitorController::resetAttempt) —
        // baris lama yg dibatalkan bukan lagi status "terkini" siswa itu.
        $attempts = UjianAttempt::whereIn('id_ujian_kelas', $ujianKelasList->pluck('uuid'))
            ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
            ->get();

        $attemptsBySiswa = $attempts->groupBy('id_siswa');
        $ujianKelasByKelas = $ujianKelasList->keyBy('id_kelas');

        return $siswaList->map(function (Siswa $siswa) use ($ujianKelasByKelas, $attemptsBySiswa) {
            $att = null;
            if ($siswa->id_login && $attemptsBySiswa->has($siswa->id_login)) {
                $att = $attemptsBySiswa->get($siswa->id_login)->last();
            }
            if (!$att && $attemptsBySiswa->has($siswa->uuid)) {
                $att = $attemptsBySiswa->get($siswa->uuid)->last();
            }

            return [
                'siswa'      => $siswa,
                'ujianKelas' => $ujianKelasByKelas->get($siswa->id_kelas),
                'attempt'    => $att,
            ];
        })->sortBy(fn ($row) => $row['siswa']->nama)->values();
    }
}
