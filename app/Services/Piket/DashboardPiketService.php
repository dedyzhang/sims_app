<?php

namespace App\Services\Piket;

use App\Models\Guru;
use App\Models\GuruTidakHadir;
use App\Models\JadwalPiket;
use App\Models\PenugasanPengganti;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardPiketService
{
    public function summaryRealTime(string $tanggal = null): array
    {
        $tanggal = $tanggal ?: now()->toDateString();
        
        // 1. Siapa piket hari ini
        $piketHariIni = JadwalPiket::with('guru:uuid,nama')
            ->whereDate('tanggal', $tanggal)
            ->where('status', 'aktif')
            ->get();
            
        $idGuruTidakHadir = GuruTidakHadir::whereDate('tanggal', $tanggal)->pluck('id_guru');
        
        // 2. Jumlah guru tidak hadir
        $jumlahTidakHadir = $idGuruTidakHadir->count();
        
        // 3. Status cover jam kosong (sudah vs belum)
        // Dari penugasan_pengganti: kalau ada penugasan (selesai/belum terserah, atau mungkin kita lihat dari jam kosong yang belum ada penugasan)
        // Jam kosong total: total Jadwal dari guru tidak hadir pada hari tsb.
        $hari = Carbon::parse($tanggal)->dayOfWeekIso;
        
        $totalJamKosong = \App\Models\Jadwal::whereIn('id_guru', $idGuruTidakHadir)
            ->where('hari', $hari)
            ->count();
            
        // Tercover = statusnya sudah 'ditugaskan'/'selesai' (bukan 'menunggu' lagi) — mencakup baik
        // assign ke guru pengganti maupun piket ambil alih sendiri (keduanya set status di luar 'menunggu').
        $tercoverFull = PenugasanPengganti::whereHas('guruTidakHadir', function ($q) use ($tanggal) {
                $q->where('tanggal', $tanggal);
            })
            ->where('status', '!=', 'menunggu')
            ->count();

        return [
            'piket' => $piketHariIni,
            'tidak_hadir' => $jumlahTidakHadir,
            'jam_kosong' => $totalJamKosong,
            'tercover' => $tercoverFull,
            'belum_tercover' => max(0, $totalJamKosong - $tercoverFull),
        ];
    }
    
    public function rekapBulanan(int $tahun, int $bulan): Collection
    {
        // Rekap per guru: 
        // 1. Frekuensi tidak hadir
        // 2. Frekuensi jadi pengganti
        $start = Carbon::createFromDate($tahun, $bulan, 1)->startOfMonth()->toDateString();
        $end = Carbon::createFromDate($tahun, $bulan, 1)->endOfMonth()->toDateString();
        
        $tidakHadir = GuruTidakHadir::whereBetween('tanggal', [$start, $end])
            ->select('id_guru', 'alasan', DB::raw('count(*) as total'))
            ->groupBy('id_guru', 'alasan')
            ->get();
            
        // "Jadi pengganti" mencakup dua bentuk: ditugaskan sebagai guru pengganti (id_guru_pengganti)
        // ATAU piket ambil alih sendiri (id_guru_piket) — keduanya beban mengajar ekstra yang sama-sama
        // relevan untuk evaluasi beban kerja tidak merata (lihat PRD §3 Fase 5).
        $penggantiBiasa = PenugasanPengganti::whereHas('guruTidakHadir', function ($q) use ($start, $end) {
                $q->whereBetween('tanggal', [$start, $end]);
            })
            ->whereNotNull('id_guru_pengganti')
            ->select('id_guru_pengganti as id_guru', DB::raw('count(*) as total_ganti'))
            ->groupBy('id_guru_pengganti')
            ->get();

        $piketAmbilAlih = PenugasanPengganti::whereHas('guruTidakHadir', function ($q) use ($start, $end) {
                $q->whereBetween('tanggal', [$start, $end]);
            })
            ->whereNotNull('id_guru_piket')
            ->select('id_guru_piket as id_guru', DB::raw('count(*) as total_ganti'))
            ->groupBy('id_guru_piket')
            ->get();

        $pengganti = $penggantiBiasa->concat($piketAmbilAlih)
            ->groupBy('id_guru')
            ->map(fn ($rows) => $rows->sum('total_ganti'));

        $guruList = Guru::orderBy('nama')->get();

        $rekap = $guruList->map(function ($guru) use ($tidakHadir, $pengganti) {
            $thGuru = $tidakHadir->where('id_guru', $guru->uuid);
            return [
                'id_guru' => $guru->uuid,
                'nama' => $guru->nama,
                'sakit' => $thGuru->where('alasan', 'sakit')->sum('total'),
                'izin' => $thGuru->where('alasan', 'izin')->sum('total'),
                'dinas_luar' => $thGuru->where('alasan', 'dinas_luar')->sum('total'),
                'alpa' => $thGuru->where('alasan', 'alpa')->sum('total'),
                'total_tidak_hadir' => $thGuru->sum('total'),
                'total_mengganti' => $pengganti->get($guru->uuid, 0),
            ];
        });
        
        // Filter out those with 0 on both? Optional. Let's keep all or only those with > 0.
        // Usually it's better to show only those who have activity, to keep table clean.
        return $rekap->filter(function ($item) {
            return $item['total_tidak_hadir'] > 0 || $item['total_mengganti'] > 0;
        })->values();
    }
}
