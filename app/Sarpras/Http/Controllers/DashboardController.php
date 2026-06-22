<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\LaporanKerusakan;
use App\Sarpras\Models\Peminjaman;
use App\Sarpras\Models\Pengadaan;
use App\Sarpras\Support\Rupiah;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Agregat ringkas. Semua sudah ter-scope tenant (school_id) via BelongsToSchool.
        $asetPerKondisi = Aset::query()
            ->selectRaw('kondisi, count(*) as jml')
            ->groupBy('kondisi')->pluck('jml', 'kondisi');

        $asetPerKategori = Aset::query()
            ->with('kategori:id,nama')
            ->selectRaw('kategori_id, count(*) as jml')
            ->groupBy('kategori_id')->get();

        // Nilai total aset: jumlahkan via BCMath (hindari float).
        $nilaiTotal = '0';
        foreach (Aset::query()->pluck('nilai_perolehan') as $n) {
            $nilaiTotal = Rupiah::add($nilaiTotal, (int) $n);
        }

        $data = [
            'totalAset' => Aset::count(),
            'asetPerKondisi' => $asetPerKondisi,
            'asetPerKategori' => $asetPerKategori,
            'nilaiTotal' => $nilaiTotal,
            'nilaiTotalRp' => Rupiah::format($nilaiTotal),
            'kerusakanTerbuka' => LaporanKerusakan::whereIn('status', ['dilaporkan', 'diterima'])->count(),
            'peminjamanAktif' => Peminjaman::whereIn('status', ['disetujui', 'dipinjam', 'terlambat'])->count(),
            'pengadaanPending' => Pengadaan::where('status', 'diajukan')->count(),
            'kerusakanTerbaru' => LaporanKerusakan::with('pelapor:uuid,username')
                ->latest()->limit(5)->get(),
        ];

        return view('sarpras.dashboard', $data);
    }
}
