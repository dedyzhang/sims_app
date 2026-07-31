<?php

namespace App\Http\Controllers;

use App\Services\Piket\DashboardPiketService;
use Illuminate\Http\Request;

class DashboardPiketController extends Controller
{
    public function __construct(private DashboardPiketService $service)
    {
    }

    public function index(Request $request)
    {
        abort_unless(in_array(auth()->user()->access, ['kepala', 'kurikulum', 'admin', 'superadmin']), 403, 'Anda tidak memiliki akses ke dashboard piket.');

        $tanggal = $request->tanggal && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->tanggal)
            ? $request->tanggal : now()->toDateString();
            
        $summary = $this->service->summaryRealTime($tanggal);
        
        return view('piket.dashboard', compact('summary', 'tanggal'));
    }
}
