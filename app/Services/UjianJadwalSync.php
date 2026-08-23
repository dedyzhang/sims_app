<?php

namespace App\Services;

use App\Models\Ujian;
use Illuminate\Support\Carbon;

/**
 * Isi ulang ujian_kelas.dibuka_mulai/dibuka_sampai (kolom yg SUDAH ADA & sudah
 * dibaca UjianPolicy::take()/UjianKelas::isOpenNow(), tapi selama ini tak
 * pernah diisi controller manapun) berdasarkan baris UjianJadwal milik satu
 * Ujian. TIDAK mengubah mekanisme gating yg ada sama sekali — cuma mengisi
 * kolom yg sudah dibaca, jadi gating jam otomatis aktif begitu ini dipanggil.
 */
class UjianJadwalSync
{
    /**
     * Terapkan ke SEMUA UjianKelas milik $ujian. Kalau ujian ini punya >1 baris
     * jadwal (retake/sesi ganda), window efektif = MIN(tanggal+jam_mulai) s.d.
     * MAX(tanggal+jam_selesai) di seluruh baris jadwalnya. Aman dipanggil kapan
     * saja (idempotent) — kalau ujian ini belum punya jadwal sama sekali, no-op
     * (window lama/kosong dibiarkan apa adanya).
     */
    public function terapkan(Ujian $ujian): void
    {
        $jadwal = $ujian->jadwalUjian()->get(); // relasi hasMany UjianJadwal, lihat Ujian::jadwalUjian()
        if ($jadwal->isEmpty()) {
            return;
        }

        $mulai = $jadwal->map(fn ($j) => Carbon::parse($j->tanggal->toDateString() . ' ' . $j->jam_mulai))->min();
        $selesai = $jadwal->map(fn ($j) => Carbon::parse($j->tanggal->toDateString() . ' ' . $j->jam_selesai))->max();

        $ujian->kelas()->update(['dibuka_mulai' => $mulai, 'dibuka_sampai' => $selesai]);
    }
}
