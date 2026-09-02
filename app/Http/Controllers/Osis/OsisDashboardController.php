<?php

namespace App\Http\Controllers\Osis;

use App\Http\Controllers\Controller;
use App\Models\OsisPaslon;
use App\Models\OsisPemilih;
use App\Models\OsisPemilihan;

class OsisDashboardController extends Controller
{
    public function dashboard(OsisPemilihan $pemilihan)
    {
        return view('osis.admin.dashboard', compact('pemilihan'));
    }

    /**
     * Polling ringan (setInterval ~5 detik di view, pola sama
     * UjianRuanganMonitorController::poll()) — HANYA angka agregat, TIDAK PERNAH
     * memuat baris osis_pemilih satu-satu ke PHP, berapa pun jumlah pemilih.
     * Total selalu 3 query, KONSTAN thd jumlah data.
     */
    public function dashboardData(OsisPemilihan $pemilihan)
    {
        $siswa = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->where('tipe_pemilih', 'siswa')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN sudah_memilih_at IS NOT NULL THEN 1 ELSE 0 END) as sudah')
            ->first();

        $guru = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->where('tipe_pemilih', 'guru')
            ->selectRaw('COUNT(*) as total, SUM(CASE WHEN sudah_memilih_at IS NOT NULL THEN 1 ELSE 0 END) as sudah')
            ->first();

        // Rekap per kelas: 1 query JOIN+GROUP BY (bukan loop per kelas).
        $perKelas = OsisPemilih::query()
            ->join('siswa', 'siswa.uuid', '=', 'osis_pemilih.id_siswa')
            ->join('kelas', 'kelas.uuid', '=', 'siswa.id_kelas')
            ->where('osis_pemilih.id_pemilihan', $pemilihan->uuid)
            ->where('osis_pemilih.tipe_pemilih', 'siswa')
            ->groupBy('kelas.uuid', 'kelas.tingkat', 'kelas.kelas')
            ->orderBy('kelas.tingkat')->orderBy('kelas.kelas')
            ->selectRaw('kelas.uuid as id_kelas, kelas.tingkat, kelas.kelas,
                COUNT(*) as total, SUM(CASE WHEN osis_pemilih.sudah_memilih_at IS NOT NULL THEN 1 ELSE 0 END) as sudah')
            ->get();

        return response()->json([
            'ok' => true,
            'siswa' => ['total' => (int) $siswa->total, 'sudah' => (int) $siswa->sudah],
            'guru' => ['total' => (int) $guru->total, 'sudah' => (int) $guru->sudah],
            'per_kelas' => $perKelas,
            'updated_at' => now()->toIso8601String(),
        ]);
    }

    public function hasil(OsisPemilihan $pemilihan)
    {
        return view('osis.admin.hasil', compact('pemilihan'));
    }

    /** Data 2 chart (siswa & guru) — GROUP BY id_paslon_dipilih, 3 query total, konstan thd jumlah pemilih. */
    public function hasilData(OsisPemilihan $pemilihan)
    {
        $paslonList = OsisPaslon::where('id_pemilihan', $pemilihan->uuid)
            ->orderBy('urutan_tampil')->orderBy('nomor_urut')
            ->get(['uuid', 'nomor_urut', 'nama_ketua', 'nama_wakil']);

        $suaraSiswa = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->where('tipe_pemilih', 'siswa')
            ->whereNotNull('id_paslon_dipilih')
            ->selectRaw('id_paslon_dipilih, COUNT(*) as jumlah')->groupBy('id_paslon_dipilih')
            ->pluck('jumlah', 'id_paslon_dipilih');

        $suaraGuru = OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->where('tipe_pemilih', 'guru')
            ->whereNotNull('id_paslon_dipilih')
            ->selectRaw('id_paslon_dipilih, COUNT(*) as jumlah')->groupBy('id_paslon_dipilih')
            ->pluck('jumlah', 'id_paslon_dipilih');

        return response()->json([
            'ok' => true,
            // Label chart cukup "No.X" — nama lengkap bikin label sumbu-x kepanjangan/tumpang
            // tindih; nomor paslon sudah cukup dikenali karena kartu paslon di halaman lain
            // (kelola paslon/hasil) sudah menampilkan nama lengkapnya.
            'labels' => $paslonList->map(fn ($p) => "No.{$p->nomor_urut}")->values(),
            'siswa' => $paslonList->map(fn ($p) => (int) ($suaraSiswa[$p->uuid] ?? 0))->values(),
            'guru' => $paslonList->map(fn ($p) => (int) ($suaraGuru[$p->uuid] ?? 0))->values(),
        ]);
    }
}
