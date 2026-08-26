<?php

namespace App\Http\Controllers;

use App\Models\GrupChat;
use App\Models\Guru;
use App\Models\Kelas;
use App\Models\Rombel;
use App\Models\Ruang;
use App\Models\Semester;
use App\Models\Siswa;
use App\Models\Walikelas;
use App\Services\ClassroomService;
use App\Services\GrupChatService;
use App\Support\JenjangSekolah;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    public function __construct(
        private ClassroomService $classroomService,
        private GrupChatService $grupChatService,
    ) {
    }

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
        // Rentang tingkat dibatasi sesuai jenjang sekolah aktif (Pengaturan → Identitas) —
        // dropdown di kelas.create sudah menyaring ini, validasi di sini cuma jaring
        // pengaman kalau ada yg mengirim request di luar form (curl/devtools).
        [$min, $max] = JenjangSekolah::rentangTingkat();
        $request->validate([
            'tingkat' => "required|integer|between:{$min},{$max}",
            'kelas'   => 'required|string|max:5',
        ]);

        $kelas = Kelas::create($request->only('tingkat', 'kelas'));
        // Siapkan Grup Kelas & Grup Paguyuban sejak awal supaya walikelas bisa
        // langsung menulis walau siswanya belum diisi.
        $this->grupChatService->provisionKelas($kelas);
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
        $kelas = Kelas::findOrFail($uuid);

        // Grup chat yang sudah punya riwayat pesan tidak boleh ikut lenyap diam-diam
        // lewat cascade delete — minta admin menanganinya dulu (mis. arsipkan kelas
        // di tahun ajaran baru) daripada kehilangan percakapan bertahun-tahun.
        if (GrupChat::where('id_kelas', $uuid)->where('last_seq', '>', 0)->exists()) {
            return back()->with('error', 'Kelas ini masih punya riwayat percakapan Grup Chat (Grup Kelas/Paguyuban). Kelas tidak bisa dihapus selama riwayat itu ada.');
        }

        // Grup yang belum pernah dipakai (last_seq = 0) aman ikut terhapus bersama
        // kelasnya — tidak ada isi yang hilang.
        GrupChat::where('id_kelas', $uuid)->delete();

        $kelas->delete();

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
        $kelas = Kelas::findOrFail($uuid);

        Walikelas::updateOrCreate(
            ['id_kelas' => $uuid],
            ['id_guru'  => $request->id_guru]
        );

        // Update access ke walikelas
        $guru = Guru::findOrFail($request->id_guru);
        $guru->user?->update(['access' => 'walikelas']);

        // Walikelas lama dikeluarkan dari kedua grup, yang baru masuk — rekonsiliasi
        // penuh dipakai (bukan jalur murah) karena pergantian ini menyentuh dua peran.
        $this->grupChatService->syncKelas($kelas);

        return back()->with('success', 'Walikelas berhasil diset.');
    }

    public function setKelasSiswa()
    {
        $semester  = Semester::aktif();
        $kelas     = Kelas::with(['walikelas.guru', 'siswa'])
            ->orderBy('tingkat')->orderBy('kelas')->get();
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

        // Kelas asal (sebelum dimutasi) dikumpulkan dulu: siswa yang PINDAH dari
        // kelas lain (bukan sekadar diisi dari kosong) tetap harus direkonsiliasi
        // keluar dari grup kelas lamanya — syncKelas() cuma membereskan satu grup
        // yang diberi tahu, beda dari syncSiswa() yang mencari ke semua grup user.
        $kelasAsalIds = Siswa::whereIn('uuid', $request->siswa_ids)
            ->whereNotNull('id_kelas')
            ->pluck('id_kelas')
            ->unique();

        foreach ($request->siswa_ids as $siswaUuid) {
            $siswa = Siswa::findOrFail($siswaUuid);
            $siswa->update(['id_kelas' => $uuid]);

            Rombel::firstOrCreate([
                'id_siswa'  => $siswaUuid,
                'id_kelas'  => $uuid,
                'semester'  => $semesterStr,
            ]);

            // Kalau kelas tujuan sudah punya ruang kelas (dibuat sebelum siswa ini
            // dimasukkan), langsung daftarkan sbg anggota — kalau tidak, siswa ini
            // kena 403 saat buka Ruang Kelas / Arena Belajar meski id_kelas-nya benar.
            $this->classroomService->enrollStudentInKelasClassrooms($siswa);
        }

        // Rekonsiliasi grup chat sekali per kelas terdampak (tujuan + semua asal),
        // bukan sekali per siswa — memindahkan puluhan siswa sekaligus tidak lagi
        // memicu puluhan query sync per siswa.
        $kelasTerdampak = Kelas::whereIn('uuid', $kelasAsalIds->push($uuid)->unique())->get();
        foreach ($kelasTerdampak as $kelasSatu) {
            $this->grupChatService->syncKelas($kelasSatu);
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
