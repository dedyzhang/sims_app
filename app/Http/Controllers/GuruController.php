<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GuruController extends Controller
{
    public function index(Request $request)
    {
        $gurus = Guru::with('user', 'walikelas.kelas')
            ->when($request->search, fn($q) => $q->where('nama', 'like', "%{$request->search}%")
                ->orWhere('nik', 'like', "%{$request->search}%"))
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('guru.index', compact('gurus'));
    }

    public function create()
    {
        return view('guru.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'          => 'required|string|max:100',
            'nik'           => 'nullable|string|max:20|unique:gurus,nik',
            'nip'           => 'nullable|string|max:20',
            'jk'            => 'required|in:L,P',
            'tempat_lahir'  => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'agama'         => 'nullable|string',
            'alamat'        => 'nullable|string',
            'tingkat_studi' => 'nullable|string',
            'program_studi' => 'nullable|string',
            'universitas'   => 'nullable|string',
            'tahun_tamat'   => 'nullable|string',
            'tmt_ngajar'    => 'nullable|date',
            'tmt_smp'       => 'nullable|date',
            'no_telp'       => 'nullable|string|max:20',
        ]);

        // Buat akun login
        $identifier = $data['nik'] ?? $data['nip'] ?? null;
        $username = $identifier ?? (Str::slug($data['nama'], '.') . '.' . Str::random(4));
        $password = Str::random(8);

        $user = User::create([
            'username'   => $username,
            'identifier' => $identifier,
            'password'   => $password,
            'access'     => 'guru',
            'must_change_password' => true,
        ]);

        $data['id_login'] = $user->uuid;
        Guru::create($data);

        return redirect()->route('guru.index')
            ->with('success', "Guru berhasil ditambah. Username: {$username} | Password: {$password}");
    }

    public function show(string $uuid)
    {
        $guru = Guru::with(['user', 'walikelas.kelas', 'ngajars.pelajaran', 'ngajars.kelas'])
            ->findOrFail($uuid);
        return view('guru.show', compact('guru'));
    }

    public function edit(string $uuid)
    {
        $guru = Guru::with('user')->findOrFail($uuid);
        return view('guru.edit', compact('guru'));
    }

    public function update(Request $request, string $uuid)
    {
        $guru = Guru::findOrFail($uuid);
        $data = $request->validate([
            'nama'          => 'required|string|max:100',
            'nik'           => "nullable|string|max:20|unique:gurus,nik,{$uuid},uuid",
            'nip'           => 'nullable|string|max:20',
            'jk'            => 'required|in:L,P',
            'tempat_lahir'  => 'nullable|string',
            'tanggal_lahir' => 'nullable|date',
            'agama'         => 'nullable|string',
            'alamat'        => 'nullable|string',
            'tingkat_studi' => 'nullable|string',
            'program_studi' => 'nullable|string',
            'universitas'   => 'nullable|string',
            'tahun_tamat'   => 'nullable|string',
            'tmt_ngajar'    => 'nullable|date',
            'tmt_smp'       => 'nullable|date',
            'no_telp'       => 'nullable|string|max:20',
            'access'        => 'nullable|in:' . implode(',', array_keys(self::ROLES)),
        ]);

        $access = $data['access'] ?? null;
        unset($data['access']); // bukan kolom guru
        $guru->update($data);

        // Sync identifier + role/akses login (hanya admin yang bisa, route sudah IsAdmin)
        if ($guru->user) {
            $guru->user->update(['identifier' => $data['nik'] ?? $data['nip'] ?? null]);
            if ($access && $guru->user->access !== 'superadmin') {
                $guru->user->update(['access' => $access]);
            }
        }

        return redirect()->route('guru.show', $uuid)->with('success', 'Data guru diperbarui.');
    }

    public function destroy(string $uuid)
    {
        $guru = Guru::findOrFail($uuid);
        if ($guru->user) {
            $guru->user->delete();
        }
        $guru->delete();

        return redirect()->route('guru.index')->with('success', 'Guru dihapus.');
    }

    public function reset(string $uuid)
    {
        $guru = Guru::findOrFail($uuid);
        $password = Str::random(8);

        if ($guru->user) {
            $guru->user->update([
                'password' => $password,
                'must_change_password' => true,
            ]);
        }

        return back()->with('reset_account', [
            'role' => 'Guru',
            'name' => $guru->nama,
            'username' => $guru->user?->username ?? '-',
            'password' => $password,
        ])->with('success', "Password direset. Password baru: {$password}");
    }

    /** Role/akses yang boleh diberikan ke seorang guru */
    public const ROLES = [
        'guru'      => 'Guru',
        'walikelas' => 'Wali Kelas',
        'kurikulum' => 'Kurikulum',
        'kesiswaan' => 'Kesiswaan',
        'sapras'    => 'Sarana & Prasarana',
        'bendahara' => 'Bendahara',
        'kepala'    => 'Kepala Sekolah',
        'admin'     => 'Admin',
    ];

    // Halaman assign pelajaran
    public function pelajaran(string $uuid)
    {
        $guru      = Guru::findOrFail($uuid);
        $pelajarans = Pelajaran::orderBy('urutan')->orderBy('nama')->get();
        $kelas     = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $ngajars   = Ngajar::with(['pelajaran', 'kelas'])->where('id_guru', $uuid)->get();

        // Peta kelas yang SUDAH diajar guru LAIN per pelajaran → utk nonaktifkan di form
        $takenMap = [];
        $lain = Ngajar::with('guru')->whereNotNull('id_pelajaran')->whereNotNull('id_guru')
            ->where('id_guru', '!=', $uuid)->get();
        foreach ($lain as $ng) {
            $nama = $ng->guru?->nama ?? 'guru lain';
            if (empty($ng->id_kelas)) {
                foreach ($kelas as $k) {
                    $takenMap[$ng->id_pelajaran][$k->uuid] = $nama; // "semua kelas"
                }
            } else {
                $takenMap[$ng->id_pelajaran][$ng->id_kelas] = $nama;
            }
        }

        return view('guru.pelajaran', compact('guru', 'pelajarans', 'kelas', 'ngajars', 'takenMap'));
    }

    public function ngajar(Request $request, string $uuid)
    {
        $data = $request->validate([
            'id_pelajaran' => 'required|exists:pelajarans,uuid',
            'id_kelas'     => 'required|array|min:1',
            'id_kelas.*'   => 'exists:kelas,uuid',
        ], [
            'id_kelas.required' => 'Pilih minimal satu kelas.',
            'id_kelas.min'      => 'Pilih minimal satu kelas.',
        ]);

        Guru::findOrFail($uuid);

        // Satu pelajaran bisa ditugaskan ke BANYAK kelas sekaligus,
        // tapi 1 (pelajaran + kelas) hanya boleh diajar SATU guru (anti-bentrok).
        $count = 0;
        $conflicts = [];
        foreach (array_unique($data['id_kelas']) as $kelasUuid) {
            // Sudah diajar guru LAIN? (kelas spesifik ini, atau penugasan "semua kelas")
            $taken = Ngajar::with('guru')
                ->where('id_pelajaran', $data['id_pelajaran'])
                ->where('id_guru', '!=', $uuid)
                ->where(fn($q) => $q->where('id_kelas', $kelasUuid)->orWhereNull('id_kelas'))
                ->first();

            if ($taken) {
                $k = Kelas::find($kelasUuid);
                $conflicts[] = ($k ? $k->tingkat . $k->kelas : '-') . ' → ' . ($taken->guru?->nama ?? 'guru lain');
                continue;
            }

            $ng = Ngajar::firstOrCreate([
                'id_guru'      => $uuid,
                'id_pelajaran' => $data['id_pelajaran'],
                'id_kelas'     => $kelasUuid,
            ]);
            if ($ng->wasRecentlyCreated) $count++;
        }

        if (!empty($conflicts)) {
            $msg = ($count > 0 ? "{$count} kelas ditambahkan. " : '')
                . 'Dilewati karena pelajaran ini sudah diajar guru lain di: ' . implode(', ', $conflicts) . '.';
            return back()->with('error', $msg);
        }

        return back()->with('success', $count > 0
            ? "Pelajaran ditambahkan ke {$count} kelas."
            : 'Penugasan sudah ada (tidak ada yang baru ditambahkan).');
    }

    public function hapusNgajar(string $ngajarUuid)
    {
        Ngajar::findOrFail($ngajarUuid)->delete();
        return back()->with('success', 'Pelajaran dihapus.');
    }

    /** Simpan data wajah guru (descriptor) untuk presensi */
    public function storeFace(Request $request, string $uuid)
    {
        $request->validate([
            'descriptors'   => 'required|array|min:3|max:5',
            'descriptors.*' => 'array|min:64',   // embedding
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

        $guru = Guru::findOrFail($uuid);

        $dup = \App\Support\FaceMatch::bestMatch($request->descriptors, $guru->uuid);
        if ($dup && $dup['similarity'] >= \App\Support\FaceMatch::THRESHOLD) {
            return response()->json([
                'duplicate'  => true,
                'nama'       => $dup['nama'],
                'tipe'       => $dup['tipe'],
                'similarity' => round($dup['similarity'] * 100),
                'message'    => 'Wajah ini mirip ' . $dup['nama'] . ' (' . $dup['tipe'] . ').',
            ], 422);
        }

        $guru->update([
            'face_descriptor'    => $request->descriptors,
            'face_registered_at' => now(),
            'face_photo'         => \App\Support\FaceMatch::saveFromDataUrl($request->photo, $guru->uuid, $guru->face_photo),
        ]);
        return response()->json(['success' => true, 'message' => 'Wajah ' . $guru->nama . ' terdaftar.']);
    }

    public function destroyFace(string $uuid)
    {
        Guru::findOrFail($uuid)->update(['face_descriptor' => null, 'face_registered_at' => null]);
        return response()->json(['success' => true, 'message' => 'Data wajah dihapus.']);
    }

    // ─── Import Data ───────────────────────────────────────────────────────

    public function importForm()
    {
        return view('guru.import');
    }

    public function import(Request $request)
    {
        $request->validate(['file' => 'required|mimes:xlsx,xls,csv|max:5120']);

        try {
            $import = new \App\Imports\GuruImport;
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('file'));

            $msg = "Import selesai: {$import->imported} guru berhasil ditambahkan."
                 . ($import->skipped > 0 ? " ({$import->skipped} baris dilewati)" : '')
                 . ($import->agamaTidakValid > 0 ? " {$import->agamaTidakValid} data agama diabaikan karena tidak sesuai pilihan dropdown." : '');

            // Simpan kredensial plaintext sementara ke session untuk diunduh.
            if (!empty($import->kredensial)) {
                session(['import_kredensial_guru' => $import->kredensial]);
                $msg .= ' Silakan unduh Kredensial Login di halaman utama data guru.';
            }

            return redirect()->route('guru.index')->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->with('error', 'Gagal import: ' . $e->getMessage());
        }
    }

    /** Unduh sekali kredensial login guru hasil import terakhir, lalu hapus dari session. */
    public function importKredensial()
    {
        $data = session('import_kredensial_guru');
        abort_if(empty($data), 404, 'Tidak ada data kredensial untuk diunduh (mungkin sudah diunduh atau sesi berakhir).');

        session()->forget('import_kredensial_guru');

        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\GuruImportKredensialExport($data), 'Kredensial Guru Baru.xlsx');
    }

    public function downloadTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\GuruTemplateExport, 'template_import_guru.xlsx');
    }
}
