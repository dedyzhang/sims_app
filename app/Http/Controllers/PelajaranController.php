<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Ngajar;
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
            'mode_skor_ujian' => 'nullable|in:rata_rata,jumlah',
        ]);
        $data['urutan'] = Pelajaran::max('urutan') + 1;
        $data['mode_skor_ujian'] = $data['mode_skor_ujian'] ?? 'rata_rata';
        Pelajaran::create($data);

        return response()->json(['success' => true, 'message' => 'Pelajaran berhasil ditambah.']);
    }

    public function update(Request $request, string $uuid)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'kode' => 'nullable|string|max:10',
            'mode_skor_ujian' => 'nullable|in:rata_rata,jumlah',
        ]);
        if (empty($data['mode_skor_ujian'])) {
            unset($data['mode_skor_ujian']); // biarkan nilai lama kalau field ini tak dikirim, bukan force ke default.
        }
        Pelajaran::findOrFail($uuid)->update($data);

        return response()->json(['success' => true, 'message' => 'Pelajaran diperbarui.']);
    }

    public function destroy(string $uuid)
    {
        $pelajaran = Pelajaran::findOrFail($uuid);

        // Tanpa guard ini, penugasan guru (Ngajar)/jadwal yg masih memakai mapel ini jadi
        // nyangkut (id_pelajaran valid tapi barisnya sudah tak ada) — halaman Ruang Kelas
        // & Jadwal yg membaca relasi itu lalu crash "Missing required parameter" saat coba
        // bikin link ke mapel yg sudah tak ada.
        if (Ngajar::where('id_pelajaran', $uuid)->exists() || Jadwal::where('id_pelajaran', $uuid)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Mata pelajaran ini masih dipakai di penugasan guru dan/atau jadwal — hapus dulu penugasan/jadwalnya sebelum menghapus mata pelajaran ini.',
            ], 422);
        }

        $pelajaran->delete();
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
