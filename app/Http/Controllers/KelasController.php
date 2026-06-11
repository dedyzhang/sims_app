<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Rombel;
use App\Models\Ruang;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Walikelas;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    public function index()
    {
        $kelas = Kelas::with(['walikelas.guru', 'siswa'])
            ->orderBy('tingkat')->orderBy('kelas')
            ->get();
        return view('kelas.index', compact('kelas'));
    }

    public function create()
    {
        return view('kelas.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'tingkat' => 'required|integer|between:1,12',
            'kelas'   => 'required|string|max:5',
        ]);

        Kelas::create($request->only('tingkat', 'kelas'));
        return redirect()->route('kelas.index')->with('success', 'Kelas berhasil ditambah.');
    }

    public function edit(string $uuid)
    {
        $kelas = Kelas::findOrFail($uuid);
        return view('kelas.edit', compact('kelas'));
    }

    public function update(Request $request, string $uuid)
    {
        $request->validate([
            'tingkat' => 'required|integer|between:1,12',
            'kelas'   => 'required|string|max:5',
        ]);
        Kelas::findOrFail($uuid)->update($request->only('tingkat', 'kelas'));
        return redirect()->route('kelas.index')->with('success', 'Kelas diperbarui.');
    }

    public function destroy(string $uuid)
    {
        Kelas::findOrFail($uuid)->delete();
        return redirect()->route('kelas.index')->with('success', 'Kelas dihapus.');
    }

    public function showWalikelas(string $uuid)
    {
        $kelas = Kelas::with('walikelas.guru')->findOrFail($uuid);
        $gurus = Guru::orderBy('nama')->get();
        return view('kelas.walikelas', compact('kelas', 'gurus'));
    }

    public function walikelas(Request $request, string $uuid)
    {
        $request->validate(['id_guru' => 'required|exists:gurus,uuid']);
        Kelas::findOrFail($uuid);

        Walikelas::updateOrCreate(
            ['id_kelas' => $uuid],
            ['id_guru'  => $request->id_guru]
        );

        // Update access ke walikelas
        $guru = Guru::findOrFail($request->id_guru);
        $guru->user?->update(['access' => 'walikelas']);

        return back()->with('success', 'Walikelas berhasil diset.');
    }

    public function setKelasSiswa()
    {
        $semester  = Semester::aktif();
        $kelas     = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $siswaBelumKelas = Siswa::whereNull('id_kelas')->orderBy('nama')->get();

        return view('kelas.set-kelas', compact('semester', 'kelas', 'siswaBelumKelas'));
    }

    public function saveRombel(Request $request, string $uuid)
    {
        $request->validate([
            'siswa_ids'  => 'required|array',
            'siswa_ids.*'=> 'exists:siswa,uuid',
        ]);

        $semester = Semester::aktif();
        $semesterStr = $semester ? "{$semester->semester}/{$semester->tahun}" : '1/2024';

        foreach ($request->siswa_ids as $siswaUuid) {
            $siswa = Siswa::findOrFail($siswaUuid);
            $siswa->update(['id_kelas' => $uuid]);

            Rombel::firstOrCreate([
                'id_siswa'  => $siswaUuid,
                'id_kelas'  => $uuid,
                'semester'  => $semesterStr,
            ]);
        }

        return back()->with('success', 'Siswa berhasil dimasukkan ke kelas.');
    }

    public function historiRombel()
    {
        $rombels = Rombel::with(['siswa', 'kelas'])
            ->orderBy('created_at', 'desc')
            ->paginate(30);
        return view('kelas.histori-rombel', compact('rombels'));
    }

    public function historiHapus(string $uuid)
    {
        Rombel::findOrFail($uuid)->delete();
        return back()->with('success', 'Histori dihapus.');
    }
}
