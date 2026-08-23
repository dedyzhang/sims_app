<?php

namespace App\Support;

use App\Models\Agenda;
use App\Models\Jadwal;
use Carbon\Carbon;

/**
 * Buku Batas: gabungan jadwal mengajar + agenda yang sudah diisi, per kelas per
 * hari, dalam satu rentang tanggal. Dipakai bareng oleh AgendaController (tampilan
 * web) dan BukuBatasExport (unduh Excel) supaya query-nya tidak dobel.
 */
class BukuBatas
{
    /**
     * @return array<int, array{tanggal:string, label:string, slots:array}>
     */
    public static function build(string $idKelas, string $dari, string $sampai): array
    {
        $hari = [];
        $start = Carbon::parse($dari);
        $end = Carbon::parse($sampai);
        for ($d = $start->copy(); $d <= $end; $d->addDay()) {
            if ($d->dayOfWeekIso > 6) continue; // lewati Minggu
            $tgl = $d->toDateString();
            $slots = self::slotHariKelas($idKelas, $tgl);
            if (empty($slots)) continue;
            $hari[] = [
                'tanggal' => $tgl,
                'label'   => $d->locale('id')->isoFormat('dddd, D MMMM Y'),
                'slots'   => $slots,
            ];
        }
        return $hari;
    }

    /** Slot jadwal satu kelas pada satu tanggal (digabung per mapel), beserta agendanya bila sudah diisi. */
    private static function slotHariKelas(string $idKelas, string $tanggal): array
    {
        $hariKe = (int) Carbon::parse($tanggal)->dayOfWeekIso;

        $jadwals = Jadwal::with(['pelajaran', 'guru'])
            ->where('id_kelas', $idKelas)->where('hari', $hariKe)
            ->whereNotNull('id_pelajaran')->get()->sortBy('jam_mulai');

        $agendaMap = Agenda::with('absensi')->where('id_kelas', $idKelas)
            ->whereDate('tanggal', $tanggal)->get()->keyBy('id_pelajaran');

        $grup = [];
        $scheduledPelajaranIds = [];

        foreach ($jadwals as $j) {
            $key = $j->id_pelajaran;
            if (!isset($grup[$key])) {
                $grup[$key] = [
                    'pelajaran'   => $j->pelajaran?->nama ?? '-',
                    'guru'        => $j->guru?->nama ?? '-',
                    'jam_mulai'   => substr((string) $j->jam_mulai, 0, 5),
                    'jam_selesai' => substr((string) $j->jam_selesai, 0, 5),
                    'agenda'      => $agendaMap->get($key),
                    'is_orphaned' => false,
                ];
                $scheduledPelajaranIds[] = $key;
            } else {
                $grup[$key]['jam_selesai'] = substr((string) $j->jam_selesai, 0, 5);
            }
        }

        // Tambahkan agenda yang sudah diisi di masa lalu tapi jadwalnya sudah tidak ada di hari ini
        foreach ($agendaMap as $idPel => $a) {
            if (!in_array($idPel, $scheduledPelajaranIds)) {
                $a->loadMissing(['pelajaran', 'guru', 'jadwal.jam']);
                $j = $a->jadwal;
                $grup['orphaned_' . $idPel] = [
                    'pelajaran'   => $a->pelajaran?->nama ?? '-',
                    'guru'        => $a->guru?->nama ?? '-',
                    'jam_mulai'   => $j && $j->jam ? substr($j->jam->jam_mulai, 0, 5) : '00:00',
                    'jam_selesai' => $j && $j->jam ? substr($j->jam->jam_selesai, 0, 5) : '00:00',
                    'agenda'      => $a,
                    'is_orphaned' => true,
                ];
            }
        }

        return array_values($grup);
    }
}
