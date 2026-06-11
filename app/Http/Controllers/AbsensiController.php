<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AbsensiController extends Controller
{
    public function index(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selectedKelas = $request->kelas ?: optional($kelasList->first())->uuid;
        $tanggal = $request->tanggal ?: now()->toDateString();

        $siswas = collect();
        $existing = collect();
        if ($selectedKelas) {
            $siswas = Siswa::where('id_kelas', $selectedKelas)->orderBy('nama')->get();
            $existing = Absensi::where('id_kelas', $selectedKelas)
                ->whereDate('tanggal', $tanggal)
                ->get()->keyBy('id_siswa');
        }

        return view('absensi.index', compact('kelasList', 'selectedKelas', 'tanggal', 'siswas', 'existing'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_kelas' => 'required|exists:kelas,uuid',
            'tanggal'  => 'required|date',
            'status'   => 'required|array',
        ]);

        $tanggal = $request->tanggal;
        $count = 0;
        foreach ($request->status as $siswaUuid => $status) {
            if (!in_array($status, array_keys(Absensi::STATUS))) continue;
            Absensi::updateOrCreate(
                ['id_siswa' => $siswaUuid, 'tanggal' => $tanggal],
                [
                    'id_kelas'     => $request->id_kelas,
                    'status'       => $status,
                    'keterangan'   => $request->keterangan[$siswaUuid] ?? null,
                    'dicatat_oleh' => auth()->id(),
                ]
            );
            $count++;
        }

        return back()->with('success', "Absensi {$count} siswa tersimpan untuk " . Carbon::parse($tanggal)->isoFormat('D MMM Y') . '.');
    }

    public function rekap(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selectedKelas = $request->kelas ?: optional($kelasList->first())->uuid;
        $bulan = $request->bulan ?: now()->format('Y-m');

        [$y, $m] = array_pad(explode('-', $bulan), 2, now()->format('m'));
        $start = Carbon::createFromDate((int)$y, (int)$m, 1)->startOfMonth();
        $end = (clone $start)->endOfMonth();

        $rekap = collect();
        if ($selectedKelas) {
            $siswas = Siswa::where('id_kelas', $selectedKelas)->orderBy('nama')->get();
            $absen = Absensi::where('id_kelas', $selectedKelas)
                ->whereBetween('tanggal', [$start->toDateString(), $end->toDateString()])
                ->get()->groupBy('id_siswa');

            $rekap = $siswas->map(function ($s) use ($absen) {
                $rows = $absen->get($s->uuid, collect());
                return [
                    'siswa' => $s,
                    'hadir' => $rows->where('status', 'hadir')->count(),
                    'izin'  => $rows->where('status', 'izin')->count(),
                    'sakit' => $rows->where('status', 'sakit')->count(),
                    'alpa'  => $rows->where('status', 'alpa')->count(),
                ];
            });
        }

        return view('absensi.rekap', compact('kelasList', 'selectedKelas', 'bulan', 'rekap'));
    }

    /** Halaman registrasi wajah siswa */
    public function wajah(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selectedKelas = $request->kelas ?: optional($kelasList->first())->uuid;
        $siswas = $selectedKelas
            ? Siswa::where('id_kelas', $selectedKelas)->orderBy('nama')->get()
            : collect();
        return view('absensi.wajah', compact('kelasList', 'selectedKelas', 'siswas'));
    }

    /** Halaman scan absensi via kamera */
    public function scan(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selectedKelas = $request->kelas ?: optional($kelasList->first())->uuid;
        $tanggal = $request->tanggal ?: now()->toDateString();

        $siswas = collect();
        $existing = collect();
        if ($selectedKelas) {
            $siswas = Siswa::where('id_kelas', $selectedKelas)->orderBy('nama')->get();
            $existing = Absensi::where('id_kelas', $selectedKelas)
                ->whereDate('tanggal', $tanggal)->get()->keyBy('id_siswa');
        }

        // payload untuk JS: siswa + descriptors wajah
        $payload = $siswas->map(fn($s) => [
            'uuid'    => $s->uuid,
            'nama'    => $s->nama,
            'nis'     => $s->nis,
            'jk'      => $s->jk,
            'desc'    => $s->face_descriptor,            // array of [128] atau null
            'status'  => $existing->get($s->uuid)?->status,
        ])->values();

        return view('absensi.scan', compact('kelasList', 'selectedKelas', 'tanggal', 'siswas', 'payload'));
    }

    /** Tandai 1 siswa hadir (AJAX dari scan wajah) */
    public function mark(Request $request)
    {
        $data = $request->validate([
            'id_siswa' => 'required|exists:siswa,uuid',
            'id_kelas' => 'nullable|exists:kelas,uuid',
            'tanggal'  => 'required|date',
            'status'   => 'nullable|in:hadir,izin,sakit,alpa',
        ]);

        Absensi::updateOrCreate(
            ['id_siswa' => $data['id_siswa'], 'tanggal' => $data['tanggal']],
            [
                'id_kelas'     => $data['id_kelas'] ?? null,
                'status'       => $data['status'] ?? 'hadir',
                'keterangan'   => 'Scan wajah',
                'dicatat_oleh' => auth()->id(),
            ]
        );

        return response()->json(['success' => true]);
    }
}
