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

    /** Halaman wajib daftar wajah sendiri (siswa / guru) setelah login */
    public function self()
    {
        $user = auth()->user();
        $profile = $user->siswa ?: $user->guru;

        // Role tanpa profil siswa/guru (admin, ortu, dll) tidak perlu daftar wajah
        if (!$profile) {
            return redirect()->route('dashboard');
        }
        // Sudah terdaftar → lanjut
        if (!empty($profile->face_descriptor)) {
            return redirect()->route('dashboard')->with('success', 'Wajah Anda sudah terdaftar.');
        }

        $tipe = $user->siswa ? 'siswa' : 'guru';
        return view('face.self', ['nama' => $profile->nama, 'tipe' => $tipe]);
    }

    /** Simpan descriptor wajah milik user yang login */
    public function selfStore(Request $request)
    {
        $request->validate([
            'descriptors'   => 'required|array|min:1|max:5',
            'descriptors.*' => 'array|min:64',
            'photo'         => 'nullable|string',
        ]);

        $user = auth()->user();
        $profile = $user->siswa ?: $user->guru;
        if (!$profile) {
            return response()->json(['message' => 'Profil tidak ditemukan.'], 422);
        }

        $profile->update([
            'face_descriptor'    => $request->descriptors,
            'face_registered_at' => now(),
            'face_photo'         => $request->photo,
        ]);

        return response()->json(['success' => true, 'message' => 'Wajah berhasil didaftarkan.']);
    }
}
