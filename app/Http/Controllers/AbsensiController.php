<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\Guru;
use App\Models\PresensiGuru;
use App\Models\Setting;
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

        $batas = Setting::get('waktu_terlambat', '07:30');

        return view('absensi.index', compact('kelasList', 'selectedKelas', 'tanggal', 'siswas', 'existing', 'batas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'id_kelas' => 'required|exists:kelas,uuid',
            'tanggal'  => 'required|date',
            'status'   => 'nullable|array',   // hanya siswa yang ditandai yang disimpan
        ]);

        $tanggal = $request->tanggal;
        $count = 0;
        foreach (($request->status ?? []) as $siswaUuid => $status) {
            if (!in_array($status, array_keys(Absensi::STATUS))) continue;

            $row = Absensi::firstOrNew(['id_siswa' => $siswaUuid, 'tanggal' => $tanggal]);
            $row->id_kelas     = $request->id_kelas;
            $row->status       = $status;
            $row->dicatat_oleh = auth()->id();
            // keterangan: jangan timpa dengan kosong (pertahankan mis. "Scan wajah")
            $ket = $request->keterangan[$siswaUuid] ?? null;
            if ($ket !== null && $ket !== '') {
                $row->keterangan = $ket;
            }
            // jam_masuk SENGAJA tidak disentuh → waktu absen hasil scan tetap tersimpan
            $row->save();
            $count++;
        }

        return back()->with('success', "Absensi {$count} siswa tersimpan untuk " . Carbon::parse($tanggal)->isoFormat('D MMM Y') . '.');
    }

    public function rekap(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selectedKelas = $request->kelas ?: optional($kelasList->first())->uuid;

        $dari   = $request->dari   ?: now()->startOfMonth()->toDateString();
        $sampai = $request->sampai ?: now()->toDateString();
        if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];

        $batas = Setting::get('waktu_terlambat', '07:30');
        $dates = $this->dateRange($dari, $sampai);

        $rekap = collect();
        if ($selectedKelas) {
            $siswas = Siswa::where('id_kelas', $selectedKelas)->orderBy('nama')->get();
            $absen = Absensi::where('id_kelas', $selectedKelas)
                ->whereDate('tanggal', '>=', $dari)
                ->whereDate('tanggal', '<=', $sampai)
                ->get()->groupBy('id_siswa');

            $rekap = $siswas->map(function ($s) use ($absen, $batas) {
                $rows = $absen->get($s->uuid, collect());
                $hadir = $rows->where('status', 'hadir');
                return [
                    'siswa'     => $s,
                    'hadir'     => $hadir->count(),
                    'terlambat' => $hadir->filter(fn($r) => $r->terlambat($batas))->count(),
                    'izin'      => $rows->where('status', 'izin')->count(),
                    'sakit'     => $rows->where('status', 'sakit')->count(),
                    'alpa'      => $rows->where('status', 'alpa')->count(),
                    'byDate'    => $rows->keyBy(fn($r) => $r->tanggal->format('Y-m-d')),
                ];
            });
        }

        return view('absensi.rekap', compact('kelasList', 'selectedKelas', 'dari', 'sampai', 'rekap', 'batas', 'dates'));
    }

    /** Daftar tanggal dalam rentang (untuk header rincian), dibatasi 92 hari. */
    public static function dateRange(string $dari, string $sampai): array
    {
        $start = Carbon::parse($dari)->startOfDay();
        $end   = Carbon::parse($sampai)->startOfDay();
        $dates = [];
        $i = 0;
        while ($start <= $end && $i < 92) {
            $dates[] = [
                'ymd'   => $start->format('Y-m-d'),
                'hari'  => $start->isoFormat('dd'),     // Sn, Sl, ...
                'tgl'   => $start->format('j/n'),       // 13/6
                'libur' => $start->isoWeekday() >= 6,   // Sabtu/Minggu
            ];
            $start->addDay();
            $i++;
        }
        return $dates;
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

    /** Halaman scan absensi via kamera — mode kiosk, lintas semua kelas untuk siswa dan guru */
    public function scan(Request $request)
    {
        $tanggal = $request->tanggal ?: now()->toDateString();

        // Semua siswa yang SUDAH daftar wajah, dari kelas mana pun
        $siswas = Siswa::with('kelas')
            ->whereNotNull('face_descriptor')
            ->orderBy('nama')
            ->get()
            ->sortBy(fn($s) => sprintf('%s%s %s', $s->kelas?->tingkat, $s->kelas?->kelas, $s->nama))
            ->values();

        $existingSiswa = Absensi::whereIn('id_siswa', $siswas->pluck('uuid'))
            ->whereDate('tanggal', $tanggal)->get()->keyBy('id_siswa');

        // Semua guru yang SUDAH daftar wajah
        $gurus = Guru::whereNotNull('face_descriptor')
            ->orderBy('nama')
            ->get();

        $existingGuru = PresensiGuru::whereDate('tanggal', $tanggal)
            ->get()
            ->keyBy('id_guru');

        // payload untuk JS: siswa + guru + descriptors wajah
        $payloadSiswa = $siswas->map(fn($s) => [
            'uuid'     => $s->uuid,
            'type'     => 'siswa',
            'nama'     => $s->nama,
            'nis'      => $s->nis,
            'jk'       => $s->jk,
            'kelas'    => $s->kelas ? $s->kelas->tingkat . $s->kelas->kelas : '-',
            'id_kelas' => $s->id_kelas,
            'desc'     => $s->face_descriptor,           // array of embeddings
            'status'   => $existingSiswa->get($s->uuid)?->status,
        ]);

        $payloadGuru = $gurus->map(fn($g) => [
            'uuid'       => $g->uuid,
            'type'       => 'guru',
            'nama'       => $g->nama,
            'jk'         => $g->jk,
            'desc'       => $g->face_descriptor,
            'nip'        => $g->nip ?: $g->nik,
            'status'     => $existingGuru->get($g->uuid)?->status,
            'pulangDone' => (bool) ($existingGuru->get($g->uuid)?->jam_pulang),  // sudah scan pulang?
        ]);

        $payload = $payloadSiswa->concat($payloadGuru)->values();

        return view('absensi.scan', compact('tanggal', 'siswas', 'gurus', 'payload', 'existingSiswa', 'existingGuru'));
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

        $row = Absensi::firstOrNew([
            'id_siswa' => $data['id_siswa'],
            'tanggal'  => $data['tanggal'],
        ]);
        $row->id_kelas     = $data['id_kelas'] ?? $row->id_kelas;
        $row->status       = $data['status'] ?? 'hadir';
        $row->keterangan   = 'Scan wajah';
        $row->dicatat_oleh = auth()->id();
        // catat jam masuk hanya sekali (scan pertama) agar deteksi terlambat akurat
        if (empty($row->jam_masuk)) {
            $row->jam_masuk = now()->format('H:i:s');
        }
        $row->save();

        $batas = Setting::get('waktu_terlambat', '07:30');
        return response()->json([
            'success'   => true,
            'jam'       => substr($row->jam_masuk, 0, 5),
            'terlambat' => $row->terlambat($batas),
        ]);
    }
}
