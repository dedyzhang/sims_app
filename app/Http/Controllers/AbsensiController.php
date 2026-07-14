<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\Guru;
use App\Models\PresensiGuru;
use App\Models\Setting;
use App\Support\AbsensiGuru;
use App\Support\AttendanceParentNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AbsensiController extends Controller
{
    /** Kelas homeroom guru saat ini bila BUKAN admin (null berarti admin = boleh semua kelas). */
    private function walikelasKelasId(): ?string
    {
        return auth()->user()->canAccess('manage_absensi') ? null : auth()->user()->guru?->walikelas?->id_kelas;
    }

    /**
     * Masuk mode kiosk publik via link rahasia — TANPA login sama sekali (tidak ada Auth::login,
     * tidak ada session). Token diteruskan lewat query string `?_kiosk=` ke halaman scan/QR, lalu
     * divalidasi ulang per-request oleh EnsureKioskOrPermission. Dengan begitu membuka link ini di
     * browser yang sama dengan tab lain yang sudah login (mis. admin) tidak pernah mengubah sesi
     * login orang itu — beda dari pendekatan lama yang login-kan browser sbg akun kiosk.
     */
    public function kioskEnter(string $token)
    {
        $real = Setting::get('kiosk_token');
        abort_unless($real && hash_equals((string) $real, $token), 404);

        $target = AbsensiGuru::bolehQr() ? route('qr.absensi') : route('absensi.scan');

        return redirect($target . '?_kiosk=' . urlencode($token));
    }

    public function index(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $walikelasKelas = $this->walikelasKelasId();
        abort_if(!auth()->user()->canAccess('manage_absensi') && !$walikelasKelas, 403, 'Hanya admin/wali kelas yang dapat mengakses absensi.');
        if ($walikelasKelas) {
            $kelasList = $kelasList->where('uuid', $walikelasKelas)->values();
        }
        $selectedKelas = $walikelasKelas ?: ($request->kelas ?: optional($kelasList->first())->uuid);
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

        $walikelasKelas = $this->walikelasKelasId();
        abort_if(!auth()->user()->canAccess('manage_absensi') && $request->id_kelas !== $walikelasKelas, 403, 'Anda hanya dapat mengisi absensi kelas Anda sendiri.');

        $tanggal = $request->tanggal;
        $count = 0;
        foreach (($request->status ?? []) as $siswaUuid => $status) {
            if (!in_array($status, array_keys(Absensi::STATUS))) continue;

            $row = Absensi::firstOrNew(['id_siswa' => $siswaUuid, 'tanggal' => $tanggal]);
            $previousStatus = $row->exists ? $row->status : null;
            $row->id_kelas     = $request->id_kelas;
            $row->status       = $status;
            $row->dicatat_oleh = auth()->id();
            if (!$row->exists) $row->id_semester = \App\Models\Semester::aktif()?->id;
            // keterangan: jangan timpa dengan kosong (pertahankan mis. "Scan wajah")
            $ket = $request->keterangan[$siswaUuid] ?? null;
            if ($ket !== null && $ket !== '') {
                $row->keterangan = $ket;
            }
            // jam_masuk SENGAJA tidak disentuh → waktu absen hasil scan tetap tersimpan
            $row->save();
            if ($status === 'hadir' && $previousStatus !== 'hadir') {
                AttendanceParentNotifier::notify($row);
            }
            $count++;
        }

        return back()->with('success', "Absensi {$count} siswa tersimpan untuk " . Carbon::parse($tanggal)->isoFormat('D MMM Y') . '.');
    }

    public function rekap(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $walikelasKelas = $this->walikelasKelasId();
        abort_if(!auth()->user()->canAccess('manage_absensi') && !$walikelasKelas, 403, 'Hanya pengelola yang dapat mengakses rekap absensi.');
        if ($walikelasKelas) {
            $kelasList = $kelasList->where('uuid', $walikelasKelas)->values();
        }
        $selectedKelas = $walikelasKelas ?: ($request->kelas ?: optional($kelasList->first())->uuid);

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

    public function cetakRekap(Request $request)
    {
        $walikelasKelas = $this->walikelasKelasId();
        abort_if(!auth()->user()->canAccess('manage_absensi') && !$walikelasKelas, 403);
        
        // admin harus milih kelas, wk otomatis pakai kelasnya
        $selectedKelas = $walikelasKelas ?: $request->kelas;
        abort_if(!$selectedKelas, 404, 'Kelas tidak valid.');
        
        $dari   = $request->dari   ?: now()->startOfMonth()->toDateString();
        $sampai = $request->sampai ?: now()->toDateString();
        if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];

        $k = Kelas::findOrFail($selectedKelas);
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Cetak\AbsensiSiswaExport($selectedKelas, $dari, $sampai), 
            "Rekap Absensi Siswa Kelas {$k->tingkat}{$k->kelas}.xlsx"
        );
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

    /** Halaman registrasi wajah guru */
    public function wajahGuru()
    {
        $gurus = Guru::orderBy('nama')->get();
        return view('absensi.wajah-guru', compact('gurus'));
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
            'jam_masuk'=> substr($existingSiswa->get($s->uuid)?->jam_masuk, 0, 5),
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
            'jam_masuk'  => substr($existingGuru->get($g->uuid)?->jam_masuk, 0, 5),
            'jam_pulang' => substr($existingGuru->get($g->uuid)?->jam_pulang, 0, 5),
        ]);

        $payload = $payloadSiswa->concat($payloadGuru)->values();

        // Mode kiosk ditentukan per-request dari token di URL (lihat EnsureKioskOrPermission),
        // BUKAN dari session — supaya tab lain di browser yang sama yg sudah login tak terganggu.
        $isKiosk = \App\Http\Middleware\EnsureKioskOrPermission::hasValidToken($request);
        $kioskToken = $isKiosk ? $request->query('_kiosk') : null;

        return view('absensi.scan', compact('tanggal', 'siswas', 'gurus', 'payload', 'existingSiswa', 'existingGuru', 'isKiosk', 'kioskToken'));
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

        // Metode absensi aktif harus "Scan Wajah".
        if (!\App\Support\AbsensiGuru::bolehWajah()) {
            return response()->json([
                'success' => false,
                'message' => \App\Support\AbsensiGuru::pesanKunci('Scan Wajah'),
            ]);
        }

        // Hormati kalender: absensi siswa harus dibuka untuk tanggal ini.
        if (!\App\Support\KalenderAbsensi::absenSiswaDibuka($data['tanggal'])) {
            return response()->json([
                'success' => false,
                'message' => 'Absensi siswa tidak dibuka untuk tanggal ini.',
            ]);
        }

        // Wajib isi kuesioner 7 KAIH hari ini sebelum boleh absen (berlaku juga di kios scan wajah).
        if (!\App\Support\KaihSiswa::bolehAbsen($data['id_siswa'], $data['tanggal'])) {
            return response()->json([
                'success' => false,
                'message' => \App\Support\KaihSiswa::pesanTolak(),
            ]);
        }

        $row = Absensi::firstOrNew([
            'id_siswa' => $data['id_siswa'],
            'tanggal'  => $data['tanggal'],
        ]);
        $previousStatus = $row->exists ? $row->status : null;
        $row->id_kelas     = $data['id_kelas'] ?? $row->id_kelas;
        $row->status       = $data['status'] ?? 'hadir';
        $row->keterangan   = 'Scan wajah';
        $row->dicatat_oleh = auth()->id();
        // catat jam masuk hanya sekali (scan pertama) agar deteksi terlambat akurat
        $scanPertama = empty($row->jam_masuk);
        if (!$row->jam_masuk) {
            $row->jam_masuk = now()->format('H:i:s');
        }
        if (!$row->exists) $row->id_semester = \App\Models\Semester::aktif()?->id;
        $row->save();

        $batas = Setting::get('waktu_terlambat', '07:30');
        $terlambat = $row->terlambat($batas);
        if ($scanPertama && $terlambat) {
            \App\Http\Controllers\PoinController::autoTerlambat($data['id_siswa'], $data['tanggal']);
        }
        if ($row->status === 'hadir' && $previousStatus !== 'hadir') {
            AttendanceParentNotifier::notify($row);
        }

        return response()->json([
            'success'   => true,
            'jam'       => substr($row->jam_masuk, 0, 5),
            'terlambat' => $terlambat,
        ]);
    }

    /** Batalkan absen dari scan wajah */
    public function cancel(Request $request)
    {
        $data = $request->validate([
            'id_siswa' => 'required|exists:siswa,uuid',
            'tanggal'  => 'required|date',
        ]);
        
        $row = Absensi::where('id_siswa', $data['id_siswa'])->where('tanggal', $data['tanggal'])->first();
        if ($row) {
            $row->delete();
        }
        
        return response()->json(['success' => true]);
    }
}
