<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Nis;
use App\Models\Orangtua;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SiswaImport;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SiswaController extends Controller
{
    public function index(Request $request)
    {
        $kelas  = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $siswas = Siswa::with(['kelas', 'user'])
            ->when($request->search, fn($q) => $q->where('nama', 'like', "%{$request->search}%")->orWhere('nis', 'like', "%{$request->search}%"))
            ->when($request->id_kelas, fn($q) => $q->where('id_kelas', $request->id_kelas))
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        return view('siswa.index', compact('siswas', 'kelas'));
    }

    public function create()
    {
        $kelas = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        return view('siswa.create', compact('kelas'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'              => 'required|string|max:100',
            'nis'               => 'nullable|string|max:30|unique:siswa,nis',
            'nisn'              => 'nullable|string|max:20',
            'id_kelas'          => 'nullable|exists:kelas,uuid',
            'jk'                => 'required|in:L,P',
            'tempat_lahir'      => 'nullable|string',
            'tanggal_lahir'     => 'nullable|date',
            'agama'             => 'nullable|string',
            'alamat'            => 'nullable|string',
            'no_handphone'      => 'nullable|string|max:20',
            'nama_ayah'         => 'nullable|string',
            'pekerjaan_ayah'    => 'nullable|string',
            'no_telp_ayah'      => 'nullable|string',
            'nama_ibu'          => 'nullable|string',
            'pekerjaan_ibu'     => 'nullable|string',
            'no_telp_ibu'       => 'nullable|string',
            'nama_wali'         => 'nullable|string',
            'pekerjaan_wali'    => 'nullable|string',
            'no_telp_wali'      => 'nullable|string',
            'sekolah_asal'      => 'nullable|string',
            'nama_ijazah'       => 'nullable|string',
            'ortu_ijazah'       => 'nullable|string',
            'tempat_lahir_ijazah' => 'nullable|string',
            'tanggal_lahir_ijazah' => 'nullable|date',
            'va'                => 'nullable|string',
            'spp'               => 'nullable|integer',
        ], [
            'nis.unique' => 'NIS tersebut sudah digunakan siswa lain. Gunakan NIS yang unik.',
        ]);

        // NIS: pakai input manual jika ada, jika kosong generate otomatis dari counter
        if (empty($data['nis'])) {
            $nisRecord = Nis::firstOrCreate([], ['kode' => 1]);
            do {
                $nis = str_pad($nisRecord->kode, 5, '0', STR_PAD_LEFT);
                $nisRecord->increment('kode');
            } while (Siswa::where('nis', $nis)->exists());
            $data['nis'] = $nis;
        }
        $nis = $data['nis'];

        // Akun siswa
        $username = $nis;
        $password = Str::random(8);

        $userSiswa = User::create([
            'username'   => $username,
            'identifier' => $nis,
            'password'   => $password,
            'access'     => 'siswa',
            'must_change_password' => true,
        ]);
        $data['id_login'] = $userSiswa->uuid;
        $siswa = Siswa::create($data);

        // Akun orang tua
        $usernameOrtu = 'P.' . $nis;
        $passwordOrtu = Str::random(8);
        $userOrtu = User::create([
            'username'   => $usernameOrtu,
            'identifier' => $nis . '-ortu',
            'password'   => $passwordOrtu,
            'access'     => 'orangtua',
            'must_change_password' => true,
        ]);
        Orangtua::create([
            'id_siswa' => $siswa->uuid,
            'id_login' => $userOrtu->uuid,
        ]);

        return redirect()->route('siswa.index')
            ->with('success', "Siswa ditambah. NIS: {$nis} | Login Siswa: {$username}/{$password} | Login Ortu: {$usernameOrtu}/{$passwordOrtu}");
    }

    public function show(string $uuid)
    {
        $siswa = Siswa::with(['kelas', 'user', 'orangtua.user'])->findOrFail($uuid);
        return view('siswa.show', compact('siswa'));
    }

    public function edit(string $uuid)
    {
        $siswa = Siswa::findOrFail($uuid);
        $kelas = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        return view('siswa.edit', compact('siswa', 'kelas'));
    }

    public function update(Request $request, string $uuid)
    {
        $siswa = Siswa::findOrFail($uuid);
        $data  = $request->validate([
            'nama'           => 'required|string|max:100',
            'nis'            => 'nullable|string|max:30|unique:siswa,nis,' . $uuid . ',uuid',
            'nisn'           => 'nullable|string|max:20',
            'id_kelas'       => 'nullable|exists:kelas,uuid',
            'jk'             => 'required|in:L,P',
            'tempat_lahir'   => 'nullable|string',
            'tanggal_lahir'  => 'nullable|date',
            'agama'          => 'nullable|string',
            'alamat'         => 'nullable|string',
            'no_handphone'   => 'nullable|string|max:20',
            'nama_ayah'      => 'nullable|string',
            'pekerjaan_ayah' => 'nullable|string',
            'no_telp_ayah'   => 'nullable|string',
            'nama_ibu'       => 'nullable|string',
            'pekerjaan_ibu'  => 'nullable|string',
            'no_telp_ibu'    => 'nullable|string',
            'nama_wali'      => 'nullable|string',
            'pekerjaan_wali' => 'nullable|string',
            'no_telp_wali'   => 'nullable|string',
            'sekolah_asal'   => 'nullable|string',
            'nama_ijazah'    => 'nullable|string',
            'ortu_ijazah'    => 'nullable|string',
            'tempat_lahir_ijazah' => 'nullable|string',
            'tanggal_lahir_ijazah' => 'nullable|date',
            'va'             => 'nullable|string',
            'spp'            => 'nullable|integer',
        ], [
            'nis.unique' => 'NIS tersebut sudah digunakan siswa lain. Gunakan NIS yang unik.',
        ]);

        // Jangan kosongkan NIS jika input dibiarkan kosong saat edit
        if (empty($data['nis'])) {
            unset($data['nis']);
        }

        // Sinkronkan identifier akun siswa dengan NIS baru bila berubah
        if (!empty($data['nis']) && $data['nis'] !== $siswa->nis) {
            $siswa->user?->update(['identifier' => $data['nis']]);
        }

        $siswa->update($data);
        return redirect()->route('siswa.show', $uuid)->with('success', 'Data siswa diperbarui.');
    }

    public function destroy(string $uuid)
    {
        $siswa = Siswa::findOrFail($uuid);
        $siswa->user?->delete();
        $siswa->orangtua?->user?->delete();
        $siswa->orangtua?->delete();
        $siswa->delete();

        return redirect()->route('siswa.index')->with('success', 'Siswa dihapus.');
    }

    /** Simpan data wajah (face descriptors) untuk pengenalan absensi */
    public function storeFace(Request $request, string $uuid)
    {
        $request->validate([
            'descriptors'   => 'required|array|min:3|max:5',
            'descriptors.*' => 'array|min:64',   // embedding (face-api 128 / Human ~1024)
            'photo'         => 'nullable|string',
        ]);

        // Deteksi wajah ganda: cocok dengan orang lain yang sudah terdaftar?
        if (!$request->boolean('force')) {
            $dup = \App\Support\FaceMatch::bestMatch($request->descriptors, $uuid);
            if ($dup && $dup['similarity'] >= \App\Support\FaceMatch::THRESHOLD) {
                return response()->json([
                    'duplicate'  => true,
                    'nama'       => $dup['nama'],
                    'tipe'       => $dup['tipe'],
                    'similarity' => round($dup['similarity'] * 100),
                    'message'    => 'Wajah ini mirip ' . $dup['nama'] . ' (' . $dup['tipe'] . ').',
                ], 422);
            }
        }

        $siswa = Siswa::findOrFail($uuid);
        $siswa->update([
            'face_descriptor'    => $request->descriptors,
            'face_registered_at' => now(),
            'face_photo'         => \App\Support\FaceMatch::saveFromDataUrl($request->photo, $siswa->uuid, $siswa->face_photo),
        ]);
        return response()->json(['success' => true, 'message' => 'Wajah ' . $siswa->nama . ' terdaftar.']);
    }

    public function destroyFace(string $uuid)
    {
        Siswa::findOrFail($uuid)->update(['face_descriptor' => null, 'face_registered_at' => null]);
        return response()->json(['success' => true, 'message' => 'Data wajah dihapus.']);
    }

    public function resetSiswa(string $uuid)
    {
        $siswa    = Siswa::findOrFail($uuid);
        $password = Str::random(8);
        $siswa->user?->update([
            'password' => $password,
            'must_change_password' => true,
        ]);
        return back()->with('reset_account', [
            'role' => 'Siswa',
            'name' => $siswa->nama,
            'username' => $siswa->user?->username ?? '-',
            'password' => $password,
        ])->with('success', "Password siswa direset: {$password}");
    }

    public function resetOrangtua(string $uuid)
    {
        $siswa    = Siswa::findOrFail($uuid);
        $password = Str::random(8);
        $siswa->orangtua?->user?->update([
            'password' => $password,
            'must_change_password' => true,
        ]);
        return back()->with('reset_account', [
            'role' => 'Orang Tua',
            'name' => 'Orang Tua dari ' . $siswa->nama,
            'username' => $siswa->orangtua?->user?->username ?? '-',
            'password' => $password,
        ])->with('success', "Password orang tua direset: {$password}");
    }

    public function importForm()
    {
        return view('siswa.import');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:5120'], [
            'file.mimes' => 'File harus berformat Excel (.xlsx atau .xls).',
        ]);
        try {
            $import = new SiswaImport;
            Excel::import($import, $request->file('file'));

            $msg = "Import selesai: {$import->imported} siswa berhasil ditambahkan."
                 . ($import->skipped > 0 ? " ({$import->skipped} baris dilewati)" : '');

            // Kredensial (password plaintext) HANYA ada di titik ini — simpan sementara
            // di session supaya bisa diunduh sekali via tombol di halaman berikutnya.
            if (!empty($import->kredensial)) {
                session(['import_kredensial_siswa' => $import->kredensial]);
            }

            return redirect()->route('siswa.index')->with('success', $msg);
        } catch (\Exception $e) {
            return back()->with('error', 'Import gagal: ' . $e->getMessage());
        }
    }

    /** Unduh sekali kredensial login siswa+ortu hasil import terakhir, lalu hapus dari session. */
    public function importKredensial()
    {
        $data = session('import_kredensial_siswa');
        abort_if(empty($data), 404, 'Tidak ada data kredensial untuk diunduh (mungkin sudah diunduh atau sesi berakhir).');

        session()->forget('import_kredensial_siswa');

        return Excel::download(new \App\Exports\SiswaImportKredensialExport($data), 'Kredensial Siswa Baru.xlsx');
    }

    public function downloadTemplate()
    {
        return Excel::download(new \App\Exports\SiswaTemplateExport, 'template_import_siswa.xlsx');
    }
}
