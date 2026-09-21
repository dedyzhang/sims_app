<?php

namespace App\Http\Controllers;

use App\Models\Ujian;
use App\Models\Siswa;
use App\Models\UjianSusulan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UjianSusulanController extends Controller
{
    public function index(Ujian $ujian)
    {
        $this->authorize('manage', $ujian);

        $susulans = UjianSusulan::with('siswa.kelas')
            ->where('id_ujian', $ujian->uuid)
            ->orderBy('tanggal', 'desc')
            ->get();

        // Get all students eligible for this exam (enrolled in the assigned classes)
        $idKelasAssigned = $ujian->kelas()->pluck('id_kelas');
        $siswas = Siswa::with('kelas')
            ->whereIn('id_kelas', $idKelasAssigned)
            ->orderBy('nama')
            ->get();

        return view('ujian.susulan.index', compact('ujian', 'susulans', 'siswas'));
    }

    public function store(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);

        $request->validate([
            'id_siswa' => 'required|exists:siswas,uuid',
            'tanggal' => 'required|date',
        ]);

        UjianSusulan::updateOrCreate(
            [
                'id_ujian' => $ujian->uuid,
                'id_siswa' => $request->id_siswa,
            ],
            [
                'tanggal' => $request->tanggal,
            ]
        );

        return redirect()->route('ujian.susulan.index', $ujian)
            ->with('success', 'Jadwal susulan berhasil ditambahkan.');
    }

    public function destroy(UjianSusulan $susulan)
    {
        $ujian = $susulan->ujian;
        $this->authorize('manage', $ujian);

        $susulan->delete();

        return redirect()->route('ujian.susulan.index', $ujian)
            ->with('success', 'Jadwal susulan berhasil dihapus.');
    }
}
