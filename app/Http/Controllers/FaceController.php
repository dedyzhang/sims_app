<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;

class FaceController extends Controller
{
    /**
     * Gate Validasi Wajah: admin/manage_absensi (semua data) ATAU wali kelas (kelasnya saja) —
     * pola sama dgn SiswaController::bisaKelolaWajah(). Return [privileged, id_kelas milik
     * walikelas (null bila privileged/bukan walikelas)].
     */
    private function accessScope(): array
    {
        $user = auth()->user();
        $privileged = $user->canAccess('manage_absensi');
        $idKelasWali = $user->guru?->walikelas?->id_kelas;

        abort_unless($privileged || $idKelasWali, 403, 'AKSES TIDAK DIIZINKAN. Peran Anda belum diberi izin untuk fitur ini.');

        return [$privileged, $privileged ? null : $idKelasWali];
    }

    /** Galeri wajah terdaftar (siswa & guru) untuk validasi visual */
    public function gallery(Request $request)
    {
        [$privileged, $idKelasWali] = $this->accessScope();

        $kelasQuery = Kelas::orderBy('tingkat')->orderBy('kelas');
        if (!$privileged) {
            $kelasQuery->where('uuid', $idKelasWali);
        }
        $kelasList = $kelasQuery->get();

        // Wali kelas cuma punya 1 kelas — abaikan pilihan ?kelas= di luar kelasnya sendiri.
        $selectedKelas = $privileged
            ? ($request->kelas ?: optional($kelasList->first())->uuid)
            : $idKelasWali;

        // Kolom & mesin yg diaudit ikut Setting → Mesin Pengenalan Wajah yg SEDANG AKTIF.
        $siswas = $selectedKelas
            ? Siswa::where('id_kelas', $selectedKelas)->whereFaceRegistered()->orderBy('nama')->get()
            : collect();
        $gurus = Guru::whereFaceRegistered()->orderBy('nama')->get();

        return view('face.gallery', compact('kelasList', 'selectedKelas', 'siswas', 'gurus'));
    }

    /** Laporan wajah ganda — pasangan wajah yang sangat mirip (kemungkinan orang sama) */
    public function duplicates(Request $request)
    {
        [$privileged, $idKelasWali] = $this->accessScope();

        $descCol = \App\Support\FaceEngine::kolomDescriptor();
        $min = (float) ($request->min ?: \App\Support\FaceMatch::thresholdFor($descCol));
        $min = max(0.3, min(0.99, $min));
        $pairs = \App\Support\FaceMatch::duplicatePairs($min, 80, $descCol);

        if (!$privileged) {
            // Wali kelas hanya lihat pasangan yg melibatkan siswa di kelasnya sendiri.
            $pairs = array_values(array_filter($pairs, fn ($p) => $this->involvesKelas($p['a'], $idKelasWali) || $this->involvesKelas($p['b'], $idKelasWali)));
        }

        return view('face.duplicates', compact('pairs', 'min'));
    }

    /** Laporan wajah berpotensi tidak terdeteksi saat scan — data rusak/kosong atau sampel tak konsisten */
    public function unreadable()
    {
        [$privileged, $idKelasWali] = $this->accessScope();

        $items = \App\Support\FaceMatch::unreadableFaces();

        if (!$privileged) {
            // Wali kelas hanya lihat siswa di kelasnya sendiri (guru tidak ditampilkan).
            $items = array_values(array_filter($items, fn ($it) => $this->involvesKelas($it, $idKelasWali)));
        }

        return view('face.unreadable', compact('items'));
    }

    /** true bila $person adalah siswa di kelas $idKelas. */
    private function involvesKelas(array $person, ?string $idKelas): bool
    {
        return $idKelas !== null && ($person['tipe'] ?? null) === 'siswa' && ($person['id_kelas'] ?? null) === $idKelas;
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
        // Sudah terdaftar (di mesin yg AKTIF sekarang) & bukan mode daftar-ulang → lanjut
        if (!$ulang && !empty($profile->{\App\Support\FaceEngine::kolomDescriptor()})) {
            return redirect()->route('dashboard')->with('success', 'Wajah Anda sudah terdaftar.');
        }

        $tipe = $user->siswa ? 'siswa' : 'guru';
        return view('face.self', [
            'nama'          => $profile->nama,
            'tipe'          => $tipe,
            'ulang'         => $ulang,
            'redirectAfter' => $ulang ? route('profile.index') : route('dashboard'),
            'faceEngine'    => \App\Support\FaceEngine::aktif(),
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

        $descCol = \App\Support\FaceEngine::kolomDescriptor();

        // Deteksi wajah ganda: cocok dengan orang lain (di mesin yg sama)?
        $dup = \App\Support\FaceMatch::bestMatch($request->descriptors, $profile->uuid, $descCol);
        if ($dup && $dup['similarity'] >= \App\Support\FaceMatch::thresholdFor($descCol)) {
            return response()->json([
                'duplicate'  => true,
                'nama'       => $dup['nama'],
                'tipe'       => $dup['tipe'],
                'similarity' => round($dup['similarity'] * 100),
                'message'    => 'Wajah ini mirip ' . $dup['nama'] . ' (' . $dup['tipe'] . ').',
            ], 422);
        }

        $update = [$descCol => $request->descriptors];
        // face_registered_at & foto profil dipakai bersama lintas mesin — jangan timpa kalau
        // sudah pernah terisi dari registrasi sebelumnya (mis. via mesin lain), tapi TETAP isi
        // kalau ini pendaftaran pertama orang ini, apa pun mesin yg sedang aktif (dulu hanya diisi
        // saat $descCol==='face_descriptor', jadi orang yg daftar PERTAMA KALI langsung lewat
        // InsightFace tak pernah dapat foto/tanggal sama sekali).
        if ($descCol === 'face_descriptor' || empty($profile->face_registered_at)) {
            $update['face_registered_at'] = now();
            $update['face_photo'] = \App\Support\FaceMatch::saveFromDataUrl($request->photo, $profile->uuid, $profile->face_photo);
        }
        $profile->update($update);

        return response()->json(['success' => true, 'message' => 'Wajah berhasil didaftarkan.']);
    }
}
