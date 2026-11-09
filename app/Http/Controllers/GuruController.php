<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Ngajar;
use App\Models\Pelajaran;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
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

        $ngajarTanpaGuruCount = Ngajar::whereDoesntHave('guru')->count();

        return view('guru.index', compact('gurus', 'ngajarTanpaGuruCount'));
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
            'access'        => 'nullable|in:' . implode(',', array_keys(self::ROLES)) . ',sapras',
        ]);

        // Buat akun login
        $identifier = $data['nik'] ?? $data['nip'] ?? null;
        $username = $identifier ?? (Str::slug($data['nama'], '.') . '.' . Str::random(4));
        $password = \App\Support\PasswordSederhana::buat();
        $access = $data['access'] ?? 'guru';
        unset($data['access']);

        $user = User::create([
            'username'   => $username,
            'identifier' => $identifier,
            'password'   => $password,
            'access'     => $access,
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
            'access'        => 'nullable|in:' . implode(',', array_keys(self::ROLES)) . ',sapras',
            'tanda_tangan'  => 'nullable|image|mimes:png,jpg,jpeg,webp|max:2048',
            'hapus_tanda_tangan' => 'nullable|boolean',
        ]);

        $access = $data['access'] ?? null;
        unset($data['access'], $data['hapus_tanda_tangan']);

        if ($request->hasFile('tanda_tangan')) {
            $old = $guru->tanda_tangan;
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
            $ext = Uploads::safeExtension($request->file('tanda_tangan'), ['png', 'jpg', 'jpeg', 'webp'], 'png');
            $data['tanda_tangan'] = $request->file('tanda_tangan')->storeAs('ttd', 'ttd_guru_'.$guru->uuid.'_'.now()->format('YmdHis').'.'.$ext, 'public');
        } elseif ($request->boolean('hapus_tanda_tangan')) {
            $old = $guru->tanda_tangan;
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
            $data['tanda_tangan'] = null;
        }

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
        $password = \App\Support\PasswordSederhana::buat();

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
        'sarpras'   => 'Sarana & Prasarana',
        'bendahara' => 'Bendahara',
        'tendik'    => 'Tendik',
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
        // HANYA masukkan jika gurunya AKTIF (bukan guru yang sudah terhapus)
        $takenMap = [];
        $lain = Ngajar::with('guru')->whereNotNull('id_pelajaran')->whereNotNull('id_guru')
            ->where('id_guru', '!=', $uuid)->get();
        foreach ($lain as $ng) {
            if (!$ng->guru) {
                continue; // Jika guru sudah terhapus, jangan nonaktifkan kelas ini (bisa diambil alih)
            }
            $nama = $ng->guru->nama;
            if (empty($ng->id_kelas)) {
                foreach ($kelas as $k) {
                    $takenMap[$ng->id_pelajaran][$k->uuid] = $nama; // "semua kelas"
                }
            } else {
                $takenMap[$ng->id_pelajaran][$ng->id_kelas] = $nama;
            }
        }

        $guruList = Guru::where('uuid', '!=', $uuid)->orderBy('nama')->get(['uuid', 'nama']);

        return view('guru.pelajaran', compact('guru', 'pelajarans', 'kelas', 'ngajars', 'takenMap', 'guruList'));
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

        $guru = Guru::findOrFail($uuid);

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
                // JIKA GURU SEBELUMNYA SUDAH TERHAPUS DARI SISTEM:
                // Otomatis ambil alih penugasan orphan ini ke guru yang sedang diedit!
                if (!$taken->guru) {
                    $this->prosesTransferNgajar($taken, $guru);
                    $count++;
                    continue;
                }

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

    /**
     * Hapus penugasan ngajar. Ruang Kelas (Classroom) yang otomatis dibuat untuk
     * kombinasi kelas+mapel ini ikut dihapus permanen (materi/tugas/jawaban siswa
     * di ruang tsb ikut hilang lewat cascadeOnDelete di DB) — sesuai permintaan:
     * penugasan dihapus → ruang kelasnya juga tidak boleh menggantung.
     */
    public function hapusNgajar(string $ngajarUuid)
    {
        $ngajar = Ngajar::findOrFail($ngajarUuid);

        \Illuminate\Support\Facades\DB::transaction(function () use ($ngajar) {
            if ($ngajar->id_kelas && $ngajar->id_pelajaran) {
                Classroom::where('id_kelas', $ngajar->id_kelas)
                    ->where('id_pelajaran', $ngajar->id_pelajaran)
                    ->get()
                    ->each(fn (Classroom $c) => $c->forceDelete());
            }

            $ngajar->delete();
        });

        return back()->with('success', 'Pelajaran & ruang kelas terkait dihapus.');
    }

    /**
     * Memindahtangankan data ngajar (Jadwal, Classroom, Ngajar) ke guru tujuan.
     */
    private function prosesTransferNgajar(Ngajar $ngajar, Guru $guruTujuan): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($ngajar, $guruTujuan) {
            // 1. Pindahkan Jadwal
            \App\Models\Jadwal::where('id_guru', $ngajar->id_guru)
                ->where('id_pelajaran', $ngajar->id_pelajaran)
                ->where('id_kelas', $ngajar->id_kelas)
                ->update(['id_guru' => $guruTujuan->uuid]);

            // 2. Pindahkan kepemilikan Classroom (e-learning)
            if ($guruTujuan->id_login) {
                \App\Models\Classroom::where('id_pelajaran', $ngajar->id_pelajaran)
                    ->where('id_kelas', $ngajar->id_kelas)
                    ->update(['created_by' => $guruTujuan->id_login]);
            }

            // 3. Tangani record Ngajar (anti-duplikat jika guru tujuan sudah punya row mapel+kelas tsb)
            $duplikat = Ngajar::where('id_guru', $guruTujuan->uuid)
                ->where('id_pelajaran', $ngajar->id_pelajaran)
                ->where('id_kelas', $ngajar->id_kelas)
                ->where('uuid', '!=', $ngajar->uuid)
                ->first();

            if ($duplikat) {
                // Pindahkan materi ke ngajar tujuan jika ada
                \App\Models\Materi::where('id_ngajar', $ngajar->uuid)
                    ->update(['id_ngajar' => $duplikat->uuid]);
                $ngajar->delete();
            } else {
                $ngajar->update(['id_guru' => $guruTujuan->uuid]);
            }
        });
    }

    /** Memindahtangankan data ngajar (Jadwal & Classroom) ke guru lain */
    public function transferNgajar(Request $request, string $ngajarUuid)
    {
        $data = $request->validate([
            'id_guru_tujuan' => 'required|exists:gurus,uuid',
        ]);

        $ngajar = Ngajar::with('guru')->findOrFail($ngajarUuid);
        $guruTujuan = Guru::findOrFail($data['id_guru_tujuan']);

        if ($ngajar->id_guru === $guruTujuan->uuid) {
            return back()->with('error', 'Guru tujuan sama dengan guru saat ini.');
        }

        $this->prosesTransferNgajar($ngajar, $guruTujuan);

        return back()->with('success', 'Data mengajar berhasil dipindahkan ke ' . $guruTujuan->nama . '.');
    }

    /** Halaman khusus kelola penugasan mengajar yang tidak memiliki guru aktif */
    public function ngajarTanpaGuru(Request $request)
    {
        $ngajarTanpaGuru = Ngajar::whereDoesntHave('guru')
            ->with(['pelajaran', 'kelas'])
            ->get();

        $guruList = Guru::orderBy('nama')->get(['uuid', 'nama']);

        return view('guru.ngajar-tanpa-guru', compact('ngajarTanpaGuru', 'guruList'));
    }

    /** Ganti guru untuk satu penugasan ngajar tanpa guru */
    public function gantiGuruNgajar(Request $request, string $uuid)
    {
        $data = $request->validate([
            'id_guru_tujuan' => 'required|exists:gurus,uuid',
        ]);

        $ngajar = Ngajar::with(['pelajaran', 'kelas'])->findOrFail($uuid);
        $guruTujuan = Guru::findOrFail($data['id_guru_tujuan']);

        $this->prosesTransferNgajar($ngajar, $guruTujuan);

        $mapel = $ngajar->pelajaran?->nama ?? 'Pelajaran';
        $kelas = $ngajar->kelas ? ('Kelas ' . $ngajar->kelas->tingkat . $ngajar->kelas->kelas) : 'Semua Kelas';

        return back()->with('success', "Penugasan {$mapel} ({$kelas}) berhasil dialihkan ke {$guruTujuan->nama}.");
    }

    /** Ganti guru secara massal (bulk) untuk beberapa penugasan ngajar tanpa guru sekaligus */
    public function bulkGantiGuruNgajar(Request $request)
    {
        $data = $request->validate([
            'id_ngajar'      => 'required|array|min:1',
            'id_ngajar.*'    => 'exists:ngajars,uuid',
            'id_guru_tujuan' => 'required|exists:gurus,uuid',
        ], [
            'id_ngajar.required' => 'Pilih minimal satu penugasan mengajar yang ingin dialihkan.',
        ]);

        $guruTujuan = Guru::findOrFail($data['id_guru_tujuan']);

        $count = 0;
        foreach ($data['id_ngajar'] as $idNgajar) {
            $ngajar = Ngajar::find($idNgajar);
            if (!$ngajar) {
                continue;
            }

            $this->prosesTransferNgajar($ngajar, $guruTujuan);
            $count++;
        }

        return back()->with('success', "{$count} penugasan mengajar berhasil dialihkan ke {$guruTujuan->nama}.");
    }

    /** Hapus satu baris penugasan ngajar tanpa guru */
    public function hapusNgajarTanpaGuru(string $uuid)
    {
        $ngajar = Ngajar::findOrFail($uuid);

        \Illuminate\Support\Facades\DB::transaction(function () use ($ngajar) {
            if ($ngajar->id_kelas && $ngajar->id_pelajaran) {
                Classroom::where('id_kelas', $ngajar->id_kelas)
                    ->where('id_pelajaran', $ngajar->id_pelajaran)
                    ->get()
                    ->each(fn (Classroom $c) => $c->forceDelete());
            }

            $ngajar->delete();
        });

        return back()->with('success', 'Penugasan mengajar berhasil dihapus.');
    }

    /** Simpan data wajah guru (descriptor) untuk presensi */
    public function storeFace(Request $request, string $uuid)
    {
        $request->validate([
            'descriptors'   => 'required|array|min:3|max:5',
            'descriptors.*' => 'array|min:64',   // embedding
            'photo'         => 'nullable|string',
        ]);

        // Kolom & mesin descriptor tergantung Setting → Mesin Pengenalan Wajah yg AKTIF sekarang.
        $descCol = \App\Support\FaceEngine::kolomDescriptor();

        $guru = Guru::findOrFail($uuid);

        // face_registered_at & foto profil dipakai BERSAMA lintas mesin (bukan data per-mesin
        // spt descriptor) — selalu diperbarui di SETIAP registrasi sukses, apa pun mesin yg aktif.
        // Dulu hanya diisi saat $descCol==='face_descriptor' (atau pendaftaran pertama), jadi
        // registrasi ULANG lewat InsightFace pada guru yg sudah pernah terdaftar (mis. lewat
        // Human.js) tak pernah memperbarui foto profilnya lagi walau foto baru sudah dikirim.
        $update = [
            $descCol => $request->descriptors,
            'face_registered_at' => now(),
            'face_photo' => \App\Support\FaceMatch::saveFromDataUrl($request->photo, $guru->uuid, $guru->face_photo),
        ];
        $guru->update($update);
        return response()->json(['success' => true, 'message' => 'Wajah ' . $guru->nama . ' terdaftar.']);
    }

    public function destroyFace(string $uuid)
    {
        $descCol = \App\Support\FaceEngine::kolomDescriptor();
        $update = [$descCol => null];
        if ($descCol === 'face_descriptor') {
            $update['face_registered_at'] = null;
        }
        Guru::findOrFail($uuid)->update($update);
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
