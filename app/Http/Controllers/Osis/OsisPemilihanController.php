<?php

namespace App\Http\Controllers\Osis;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\OsisPemilihan;
use Illuminate\Http\Request;

class OsisPemilihanController extends Controller
{
    public function index()
    {
        // Daftar periode tak pernah besar (paling banyak belasan per sekolah selama app dipakai) — get() aman.
        $daftar = OsisPemilihan::withCount(['paslon', 'pemilih'])->latest()->get();

        return view('osis.admin.periode-index', compact('daftar'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => 'required|string|max:150',
            'tahun_ajaran' => 'nullable|string|max:20',
        ]);
        $pemilihan = OsisPemilihan::create($data + ['dibuat_oleh' => $request->user()->uuid]);

        return redirect()->route('osis.show', $pemilihan)->with('success', 'Periode pemilihan dibuat.');
    }

    public function show(OsisPemilihan $pemilihan)
    {
        $pemilihan->load('paslon');

        // Daftar kelas jarang besar (belasan) — get() aman. withCount hindari N+1 hitung siswa per kelas.
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->withCount('siswa')->get();

        // 1 query agregat: berapa siswa per kelas yg SUDAH punya token di periode ini.
        $tokenPerKelas = \App\Models\OsisPemilih::where('id_pemilihan', $pemilihan->uuid)
            ->where('tipe_pemilih', 'siswa')
            ->join('siswa', 'siswa.uuid', '=', 'osis_pemilih.id_siswa')
            ->selectRaw('siswa.id_kelas, COUNT(*) as jumlah')
            ->groupBy('siswa.id_kelas')
            ->pluck('jumlah', 'id_kelas');

        $totalGuru = \App\Models\Guru::count();
        $tokenGuru = \App\Models\OsisPemilih::where('id_pemilihan', $pemilihan->uuid)->where('tipe_pemilih', 'guru')->count();

        return view('osis.admin.periode-show', compact('pemilihan', 'kelasList', 'tokenPerKelas', 'totalGuru', 'tokenGuru'));
    }

    /** Jadikan periode ini "aktif" (default utk generate token/dashboard) — matikan aktif periode lain (pola sama SettingController::updateSemester). */
    public function aktifkan(OsisPemilihan $pemilihan)
    {
        OsisPemilihan::query()->update(['aktif' => false]); // mass-update: tak memicu event model
        OsisPemilihan::clearCache();
        $pemilihan->update(['aktif' => true]);

        return back()->with('success', "'{$pemilihan->nama}' dijadikan periode aktif.");
    }

    public function updateStatus(Request $request, OsisPemilihan $pemilihan)
    {
        $data = $request->validate(['status' => 'required|in:draft,dibuka,ditutup']);

        $update = ['status' => $data['status']];
        if ($data['status'] === 'dibuka' && $pemilihan->status !== 'dibuka') {
            $update['dibuka_pada'] = now();
        }
        if ($data['status'] === 'ditutup' && $pemilihan->status !== 'ditutup') {
            $update['ditutup_pada'] = now();
        }
        $pemilihan->update($update);

        $label = ['draft' => 'diset ke draft', 'dibuka' => 'DIBUKA — publik bisa mulai memilih', 'ditutup' => 'DITUTUP — voting dihentikan'][$data['status']];

        return back()->with('success', "Status pemilihan {$label}.");
    }

    /**
     * Jadwal opsional — kalau diisi, jadi gerbang tambahan di atas `status` (lihat
     * OsisPemilihan::bolehMemilihSekarang()): publik tetap ditolak sebelum jadwal_mulai
     * tiba walau admin sudah klik "Buka" lebih awal utk siap-siap.
     */
    public function updateJadwal(Request $request, OsisPemilihan $pemilihan)
    {
        // "after:jadwal_mulai" dihindari sengaja — kalau jadwal_mulai kosong, Carbon
        // menafsirkan null sbg "sekarang" & bikin aturan itu diam-diam berubah makna.
        $data = $request->validate([
            'jadwal_mulai' => 'nullable|date',
            'jadwal_selesai' => ['nullable', 'date', function ($attr, $value, $fail) use ($request) {
                if ($value && $request->filled('jadwal_mulai') && \Illuminate\Support\Carbon::parse($value)->lte($request->input('jadwal_mulai'))) {
                    $fail('Jadwal selesai harus setelah jadwal mulai.');
                }
            }],
        ]);

        $pemilihan->update($data);

        return back()->with('success', $data['jadwal_mulai']
            ? 'Jadwal pemilihan disimpan.'
            : 'Jadwal pemilihan dihapus — voting hanya mengikuti status.');
    }
}
