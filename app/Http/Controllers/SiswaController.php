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
            ->where('status', 'aktif')
            ->when($request->search, fn($q) => $q->where('nama', 'like', "%{$request->search}%")->orWhere('nis', 'like', "%{$request->search}%"))
            ->when($request->id_kelas, fn($q) => $q->where('id_kelas', $request->id_kelas))
            ->orderBy('nama')
            ->paginate(25)
            ->withQueryString();

        $totalAktif    = Siswa::where('status', 'aktif')->count();
        $tingkatCounts = Siswa::where('status', 'aktif')
            ->join('kelas', 'siswa.id_kelas', '=', 'kelas.uuid')
            ->selectRaw('kelas.tingkat, count(*) as total')
            ->groupBy('kelas.tingkat')
            ->orderBy('kelas.tingkat')
            ->pluck('total', 'tingkat');

        return view('siswa.index', compact('siswas', 'kelas', 'totalAktif', 'tingkatCounts'));
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

    /** Wali kelas hanya boleh sentuh data wajah siswa di kelasnya sendiri; admin bebas semua kelas. */
    private function bisaKelolaWajah(?string $idKelasSiswa): bool
    {
        return auth()->user()->canAccess('manage_absensi')
            || auth()->user()->guru?->walikelas?->id_kelas === $idKelasSiswa;
    }

    /** Simpan data wajah (face descriptors) untuk pengenalan absensi */
    public function storeFace(Request $request, string $uuid)
    {
        $target = Siswa::findOrFail($uuid);
        abort_unless($this->bisaKelolaWajah($target->id_kelas), 403, 'Anda hanya dapat mendaftarkan wajah siswa kelas Anda sendiri.');

        $request->validate([
            'descriptors'   => 'required|array|min:3|max:5',
            'descriptors.*' => 'array|min:64',   // embedding (face-api 128 / Human ~1024)
            'photo'         => 'nullable|string',
        ]);

        // Deteksi wajah ganda: cocok dengan orang lain yang sudah terdaftar?
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

        $target->update([
            'face_descriptor'    => $request->descriptors,
            'face_registered_at' => now(),
            'face_photo'         => \App\Support\FaceMatch::saveFromDataUrl($request->photo, $target->uuid, $target->face_photo),
        ]);
        return response()->json(['success' => true, 'message' => 'Wajah ' . $target->nama . ' terdaftar.']);
    }

    public function destroyFace(string $uuid)
    {
        $target = Siswa::findOrFail($uuid);
        abort_unless($this->bisaKelolaWajah($target->id_kelas), 403, 'Anda hanya dapat menghapus wajah siswa kelas Anda sendiri.');

        $target->update(['face_descriptor' => null, 'face_registered_at' => null]);
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

    /** Reset password akun siswa+ortu massal (semua/tingkat/kelas) — kredensial baru diunduh sekali. */
    public function resetBulk(Request $request)
    {
        // Bisa ratusan hash bcrypt (siswa+ortu) — jangan biarkan PHP timeout memotong
        // di tengah proses (hosting bervariasi, ada yg batas eksekusinya pendek).
        set_time_limit(0);

        $data = $request->validate([
            'scope'    => 'required|in:semua,tingkat,kelas',
            'tingkat'  => 'required_if:scope,tingkat|nullable|integer',
            'id_kelas' => 'required_if:scope,kelas|nullable|exists:kelas,uuid',
        ]);

        $query = Siswa::with(['user', 'orangtua.user'])->where('status', 'aktif');

        if ($data['scope'] === 'tingkat') {
            $query->whereHas('kelas', fn ($q) => $q->where('tingkat', $data['tingkat']));
        } elseif ($data['scope'] === 'kelas') {
            $query->where('id_kelas', $data['id_kelas']);
        }

        $siswas = $query->get();
        if ($siswas->isEmpty()) {
            return back()->with('error', 'Tidak ada siswa aktif pada cakupan yang dipilih.');
        }

        $kredensial = [];
        foreach ($siswas as $siswa) {
            $passwordSiswa = null;
            if ($siswa->user) {
                $passwordSiswa = Str::random(8);
                $siswa->user->update(['password' => $passwordSiswa, 'must_change_password' => true]);
            }

            $passwordOrtu = null;
            if ($siswa->orangtua?->user) {
                $passwordOrtu = Str::random(8);
                $siswa->orangtua->user->update(['password' => $passwordOrtu, 'must_change_password' => true]);
            }

            $kredensial[] = [
                'nama' => $siswa->nama,
                'nis' => $siswa->nis,
                'username_siswa' => $siswa->user?->username ?? '-',
                'password_siswa' => $passwordSiswa ?? '-',
                'username_ortu' => $siswa->orangtua?->user?->username ?? '-',
                'password_ortu' => $passwordOrtu ?? '-',
            ];
        }

        // Kredensial (password plaintext) HANYA ada di titik ini — simpan sementara
        // di session supaya bisa diunduh sekali via tombol di halaman berikutnya.
        session(['reset_kredensial_siswa' => $kredensial]);

        return redirect()->route('siswa.index')->with('success', count($kredensial) . ' akun siswa+ortu berhasil direset.');
    }

    /** Unduh sekali kredensial hasil reset massal terakhir, lalu hapus dari session. */
    public function resetBulkKredensial()
    {
        $data = session('reset_kredensial_siswa');
        abort_if(empty($data), 404, 'Tidak ada data kredensial untuk diunduh (mungkin sudah diunduh atau sesi berakhir).');

        session()->forget('reset_kredensial_siswa');

        return Excel::download(
            new \App\Exports\SiswaImportKredensialExport($data, 'KREDENSIAL LOGIN SISWA HASIL RESET PASSWORD'),
            'Kredensial Reset Siswa.xlsx'
        );
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
                 . ($import->skipped > 0 ? " ({$import->skipped} baris dilewati)" : '')
                 . ($import->agamaTidakValid > 0 ? " {$import->agamaTidakValid} data agama diabaikan karena tidak sesuai pilihan dropdown." : '');

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
