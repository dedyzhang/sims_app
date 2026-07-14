<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;

class AlumniController extends Controller
{
    public function index(Request $request)
    {
        $angkatans = Siswa::where('status', 'lulus')->pluck('tahun_lulus')->filter()->unique()->sortDesc()->values();
        
        $alumnis = Siswa::with('kelas')
            ->where('status', 'lulus')
            ->when($request->search, fn($q) => $q->where('nama', 'like', "%{$request->search}%")->orWhere('nis', 'like', "%{$request->search}%"))
            ->when($request->tahun_lulus, fn($q) => $q->where('tahun_lulus', $request->tahun_lulus))
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        return view('alumni.index', compact('alumnis', 'angkatans'));
    }

    public function luluskan(Request $request)
    {
        $request->validate([
            'tahun_lulus' => 'required|string|max:20',
            'angkatan' => 'required|string|max:20'
        ]);

        // Cari semua kelas tingkat 9
        $kelas9 = Kelas::where('tingkat', 9)->pluck('uuid');
        if ($kelas9->isEmpty()) {
            return back()->with('error', 'Tidak ada Kelas 9 yang ditemukan di sistem.');
        }

        // Ambil semua siswa aktif di kelas 9
        $siswas = Siswa::whereIn('id_kelas', $kelas9)->where('status', 'aktif')->get();

        if ($siswas->isEmpty()) {
            return back()->with('error', 'Tidak ada siswa aktif di Kelas 9 yang bisa diluluskan.');
        }

        // Luluskan mereka
        foreach ($siswas as $s) {
            $s->update([
                'status' => 'lulus',
                'tahun_lulus' => $request->tahun_lulus,
                'angkatan' => $request->angkatan,
                'id_kelas' => null
            ]);
        }

        return back()->with('success', "Berhasil meluluskan {$siswas->count()} siswa kelas 9 menjadi angkatan {$request->angkatan} ({$request->tahun_lulus}).");
    }
}
