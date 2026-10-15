<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Exports\AsetExport;
use App\Sarpras\Exports\KibExport;
use App\Sarpras\Exports\KirExport;
use App\Sarpras\Exports\MutasiExport;
use App\Sarpras\Exports\PeminjamanBarangExport;
use App\Sarpras\Exports\PeminjamanRuanganExport;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\SarprasActivityLog;
use App\Sarpras\Support\Rupiah;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
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

        $total = '0';
        foreach (Aset::pluck('nilai_perolehan') as $n) {
            $total = Rupiah::add($total, (int) $n);
        }

        return view('sarpras.laporan.index', [
            'rekapKondisi' => $rekapKondisi,
            'totalNilaiRp' => Rupiah::format($total),
        ]);
    }

    public function aktivitas(): View
    {
        $logs = SarprasActivityLog::with('pelaku:uuid,username')
            ->latest('created_at')
            ->limit(100)
            ->get();

        return view('sarpras.laporan.aktivitas', compact('logs'));
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

    public function exportKib(Aset $aset)
    {
        return Excel::download(new KibExport($aset), 'KIB-' . $aset->kode . '.xlsx');
    }

    public function exportKir(DenahRuangan $ruangan)
    {
        return Excel::download(new KirExport($ruangan), 'KIR-' . $ruangan->kode . '.xlsx');
    }

    public function exportPeminjamanRuanganExcel(Request $request)
    {
        $filename = 'arsip-peminjaman-ruangan-' . now()->format('Ymd') . '.xlsx';

        return Excel::download(
            new PeminjamanRuanganExport(
                $request->query('dari'),
                $request->query('sampai'),
                $request->query('status'),
                $request->query('ruangan_id')
            ),
            $filename
        );
    }

    public function exportPeminjamanBarangExcel(Request $request)
    {
        $user = auth()->user();
        $canKelola = $user->can('sarpras.peminjaman.setujui') || $user->can('sarpras.peminjaman.kelola');
        $filename = 'arsip-peminjaman-barang-' . now()->format('Ymd') . '.xlsx';

        return Excel::download(
            new PeminjamanBarangExport(
                $request->query('dari'),
                $request->query('sampai'),
                $request->query('status'),
                $user->uuid,
                $canKelola
            ),
            $filename
        );
    }
}
