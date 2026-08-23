<?php

namespace App\Http\Controllers;

use App\Exports\Ujian\UjianAnalisisExport;
use App\Models\Ujian;
use App\Models\UjianKelas;
use App\Policies\UjianPolicy;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Analisis Hasil Ujian per kelas — admin lihat semua kelas ujian ini, guru pengampu
 * cuma bisa unduh kelas yang dia ampu (mengikuti batasan yang sama dgn Nilai Esai/Hasil).
 */
class UjianAnalisisController extends Controller
{
    public function index(Request $request, Ujian $ujian)
    {
        $this->authorize('gradeEssay', $ujian);
        $ujian->load('kelas.kelas', 'kelas.guruPengampu');

        $kelasBisaDiunduh = $ujian->kelas
            ->filter(fn ($uk) => app(UjianPolicy::class)->mengampuiKelas($request->user(), $uk))
            ->sortBy(fn ($uk) => [$uk->kelas?->tingkat, $uk->kelas?->kelas])
            ->values();

        return view('ujian.analisis.index', compact('ujian', 'kelasBisaDiunduh'));
    }

    public function unduh(Request $request, Ujian $ujian, UjianKelas $ujianKelas)
    {
        abort_unless($ujianKelas->id_ujian === $ujian->uuid, 404);
        $this->authorize('mengampuiKelas', $ujianKelas);

        $ujianKelas->load('kelas');
        $kelasLabel = trim(($ujianKelas->kelas?->tingkat ?? '') . ($ujianKelas->kelas?->kelas ?? ''));
        $namaFile = 'Analisis Hasil Ujian - ' . $ujian->judul . ' Kelas ' . $kelasLabel . '.xlsx';

        return Excel::download(new UjianAnalisisExport($ujian, $ujianKelas), $namaFile);
    }
}
