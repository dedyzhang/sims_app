<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;

class FaceController extends Controller
{
    /** Galeri wajah terdaftar (siswa & guru) untuk validasi visual */
    public function gallery(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selectedKelas = $request->kelas ?: optional($kelasList->first())->uuid;

        $siswas = $selectedKelas
            ? Siswa::where('id_kelas', $selectedKelas)->whereNotNull('face_descriptor')->orderBy('nama')->get()
            : collect();
        $gurus = Guru::whereNotNull('face_descriptor')->orderBy('nama')->get();

        return view('face.gallery', compact('kelasList', 'selectedKelas', 'siswas', 'gurus'));
    }

    /** Laporan wajah ganda — pasangan wajah yang sangat mirip (kemungkinan orang sama) */
    public function duplicates(Request $request)
    {
        $min = (float) ($request->min ?: \App\Support\FaceMatch::THRESHOLD);
        $min = max(0.5, min(0.99, $min));
        $pairs = \App\Support\FaceMatch::duplicatePairs($min, 80);

        return view('face.duplicates', compact('pairs', 'min'));
    }

    /** Halaman daftar wajah sendiri (siswa / guru) — wajib saat login / atau daftar ulang dari profil */
    public function self(Request $request)
    {
        $user = auth()->user();
        $profile = $user->siswa ?: $user->guru;

        // Role tanpa profil siswa/guru (admin, ortu, dll) tidak perlu daftar wajah
        if (!$profile) {
            return redirect()->route('dashboard');
        }

        $ulang = $request->boolean('ulang');
        // Sudah terdaftar & bukan mode daftar-ulang → lanjut
        if (!$ulang && !empty($profile->face_descriptor)) {
            return redirect()->route('dashboard')->with('success', 'Wajah Anda sudah terdaftar.');
        }

        $tipe = $user->siswa ? 'siswa' : 'guru';
        return view('face.self', [
            'nama'          => $profile->nama,
            'tipe'          => $tipe,
            'ulang'         => $ulang,
            'redirectAfter' => $ulang ? route('profile.index') : route('dashboard'),
        ]);
    }

    /** Simpan descriptor wajah milik user yang login */
    public function selfStore(Request $request)
    {
        $request->validate([
            'descriptors'   => 'required|array|min:3|max:5',
            'descriptors.*' => 'array|min:64',
            'photo'         => 'nullable|string',
        ]);

        $user = auth()->user();
        $profile = $user->siswa ?: $user->guru;
        if (!$profile) {
            return response()->json(['message' => 'Profil tidak ditemukan.'], 422);
        }

        // Deteksi wajah ganda: cocok dengan orang lain?
        $dup = \App\Support\FaceMatch::bestMatch($request->descriptors, $profile->uuid);
        if ($dup && $dup['similarity'] >= \App\Support\FaceMatch::THRESHOLD) {
            return response()->json([
                'duplicate'  => true,
                'nama'       => $dup['nama'],
                'tipe'       => $dup['tipe'],
                'similarity' => round($dup['similarity'] * 100),
                'message'    => 'Wajah ini mirip ' . $dup['nama'] . ' (' . $dup['tipe'] . ').',
            ], 422);
        }

        $profile->update([
            'face_descriptor'    => $request->descriptors,
            'face_registered_at' => now(),
            'face_photo'         => \App\Support\FaceMatch::saveFromDataUrl($request->photo, $profile->uuid, $profile->face_photo),
        ]);

        return response()->json(['success' => true, 'message' => 'Wajah berhasil didaftarkan.']);
    }
}
