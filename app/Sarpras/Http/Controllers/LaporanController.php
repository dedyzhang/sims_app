<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Exports\AsetExport;
use App\Sarpras\Exports\MutasiExport;
use App\Sarpras\Models\Aset;
use App\Sarpras\Support\Rupiah;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function index(): View
    {
        $rekapKondisi = Aset::query()
            ->selectRaw('kondisi, count(*) as jml, sum(nilai_perolehan) as nilai')
            ->groupBy('kondisi')->get();

        // Total nilai via BCMath.
        $total = '0';
        foreach (Aset::pluck('nilai_perolehan') as $n) {
            $total = Rupiah::add($total, (int) $n);
        }

        return view('sarpras.laporan.index', [
            'rekapKondisi' => $rekapKondisi,
            'totalNilaiRp' => Rupiah::format($total),
        ]);
    }

    /**
     * Laporan aktivitas: audit log (spatie/activitylog) tidak dipakai di SIMS,
     * jadi halaman ditampilkan kosong agar navigasi tidak putus.
     */
    public function aktivitas(): View
    {
        $aktivitas = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 30);

        return view('sarpras.laporan.aktivitas', compact('aktivitas'));
    }

    public function exportAsetExcel()
    {
        return Excel::download(new AsetExport(), 'laporan-aset-' . now()->format('Ymd') . '.xlsx');
    }

    public function exportAsetPdf()
    {
        $aset = Aset::with(['kategori:id,nama', 'ruangan:id,kode'])->orderBy('kode')->get();
        $total = '0';
        foreach ($aset as $a) {
            $total = Rupiah::add($total, (int) $a->nilai_perolehan);
        }
        $pdf = Pdf::loadView('sarpras.laporan.aset_pdf', [
            'aset' => $aset,
            'totalRp' => Rupiah::format($total),
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('laporan-aset.pdf');
    }

    public function exportMutasiExcel(Request $request)
    {
        return Excel::download(
            new MutasiExport($request->dari, $request->sampai),
            'laporan-mutasi-' . now()->format('Ymd') . '.xlsx'
        );
    }
}
