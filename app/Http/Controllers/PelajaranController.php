<?php

namespace App\Http\Controllers;

use App\Models\Pelajaran;
use Illuminate\Http\Request;

class PelajaranController extends Controller
{
    public function index()
    {
        $pelajarans = Pelajaran::orderBy('urutan')->orderBy('nama')->get();
        return view('pelajaran.index', compact('pelajarans'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'kode' => 'nullable|string|max:10',
        ]);
        $data['urutan'] = Pelajaran::max('urutan') + 1;
        Pelajaran::create($data);

        return response()->json(['success' => true, 'message' => 'Pelajaran berhasil ditambah.']);
    }

    public function update(Request $request, string $uuid)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'kode' => 'nullable|string|max:10',
        ]);
        Pelajaran::findOrFail($uuid)->update($data);

        return response()->json(['success' => true, 'message' => 'Pelajaran diperbarui.']);
    }

    public function destroy(string $uuid)
    {
        Pelajaran::findOrFail($uuid)->delete();
        return response()->json(['success' => true, 'message' => 'Pelajaran dihapus.']);
    }

    public function sorting(Request $request)
    {
        $request->validate(['urutans' => 'required|array']);
        foreach ($request->urutans as $uuid => $urutan) {
            Pelajaran::where('uuid', $uuid)->update(['urutan' => (int)$urutan]);
        }
        return response()->json(['success' => true]);
    }
}
