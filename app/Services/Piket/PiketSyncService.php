<?php

namespace App\Services\Piket;

use App\Models\GuruTidakHadir;
use App\Models\PenugasanPengganti;
use App\Models\PresensiGuru;
use Illuminate\Support\Facades\DB;

/**
 * Sinkronisasi data piket harian: presensi guru → guru_tidak_hadir → penugasan_pengganti.
 * Dipakai dashboard guru piket dan halaman operasional agar widget tidak kosong.
 */
class PiketSyncService
{
    public function __construct(private JamKosongService $jamKosong) {}

    public function syncHariIni(?string $tanggal = null): void
    {
        $tanggal = $tanggal ?: now()->toDateString();
        $this->syncDariPresensi($tanggal);
        $this->sinkronPenugasanDariGuruTidakHadir($tanggal);
    }

    public function syncDariPresensi(string $tanggal): void
    {
        $presensiTidakHadir = PresensiGuru::whereIn('status', array_keys(GuruTidakHadir::ALASAN_DARI_PRESENSI))
            ->whereDate('tanggal', $tanggal)
            ->get();

        $existingIds = GuruTidakHadir::where('tanggal', $tanggal)
            ->pluck('id_guru')
            ->all();

        foreach ($presensiTidakHadir as $presensi) {
            if (in_array($presensi->id_guru, $existingIds, true)) {
                continue;
            }

            GuruTidakHadir::create([
                'id_guru' => $presensi->id_guru,
                'tanggal' => $tanggal,
                'sumber' => 'otomatis_presensi',
                'alasan' => GuruTidakHadir::ALASAN_DARI_PRESENSI[$presensi->status],
                'keterangan' => $presensi->keterangan,
                'id_presensi_guru' => $presensi->uuid,
            ]);

            $existingIds[] = $presensi->id_guru;
        }
    }

    public function sinkronPenugasanDariGuruTidakHadir(string $tanggal): void
    {
        $guruTidakHadir = GuruTidakHadir::where('tanggal', $tanggal)->get();

        foreach ($guruTidakHadir as $g) {
            $slots = $this->jamKosong->untukGuru($g->id_guru, $tanggal);

            DB::transaction(function () use ($g, $slots) {
                foreach ($slots as $slot) {
                    PenugasanPengganti::firstOrCreate(
                        ['id_guru_tidak_hadir' => $g->uuid, 'id_jadwal' => $slot->uuid],
                        ['status' => 'menunggu']
                    );
                }
            });
        }
    }
}
