<?php

namespace App\Http\Controllers;

use App\Services\Piket\DashboardPiketService;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class RekapPiketController extends Controller
{
    public function __construct(private DashboardPiketService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(in_array(auth()->user()->access, ['kepala', 'admin', 'superadmin']), 403, 'Hanya kepala sekolah yang dapat mengakses rekap piket.');

        $tahun = $request->tahun ?: now()->year;
        $bulan = $request->bulan ?: now()->month;
        
        $rekap = $this->service->rekapBulanan((int) $tahun, (int) $bulan);
        
        return view('piket.rekap', compact('rekap', 'tahun', 'bulan'));
    }
    
    public function export(Request $request)
    {
        abort_unless(in_array(auth()->user()->access, ['kepala', 'admin', 'superadmin']), 403, 'Hanya kepala sekolah yang dapat mengakses rekap piket.');

        $tahun = $request->tahun ?: now()->year;
        $bulan = $request->bulan ?: now()->month;
        
        $rekap = $this->service->rekapBulanan((int) $tahun, (int) $bulan);

        $pdf = Pdf::loadView('piket.rekap-pdf', compact('rekap', 'tahun', 'bulan'));
        
        $namaBulan = \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM YYYY');
        
        return $pdf->download("Rekap_Piket_Substitusi_{$namaBulan}.pdf");
    }
}
