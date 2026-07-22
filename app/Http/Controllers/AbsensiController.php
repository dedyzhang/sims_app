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

        return view('absensi.index', compact('kelasList', 'selectedKelas', 'tanggal', 'siswas', 'existing', 'batas', 'walikelasKelas'));
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
            // Isi jam_masuk hanya saat pertama kali ditandai hadir (jangan timpa hasil scan).
            if ($status === 'hadir' && empty($row->jam_masuk)) {
                $row->jam_masuk = now()->format('H:i:s');
            }
            $row->save();
            AttendanceParentNotifier::notifyIfStatusChanged($previousStatus, $row);
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

    /** Halaman registrasi wajah siswa — admin (semua kelas) + wali kelas (kelasnya saja) */
    public function wajah(Request $request)
    {
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $walikelasKelas = $this->walikelasKelasId();
        abort_if(!auth()->user()->canAccess('manage_absensi') && !$walikelasKelas, 403, 'Hanya admin/wali kelas yang dapat mengakses registrasi wajah.');
        if ($walikelasKelas) {
            $kelasList = $kelasList->where('uuid', $walikelasKelas)->values();
        }
        $selectedKelas = $walikelasKelas ?: ($request->kelas ?: optional($kelasList->first())->uuid);
        $siswas = $selectedKelas
            ? Siswa::where('id_kelas', $selectedKelas)->orderBy('nama')->get()
            : collect();
        return view('absensi.wajah', compact('kelasList', 'selectedKelas', 'siswas', 'walikelasKelas'));
    }

    /** Halaman registrasi wajah guru */
    public function wajahGuru()
    {
        $gurus = Guru::orderBy('nama')->get();
        return view('absensi.wajah-guru', compact('gurus'));
    }

    /** Halaman scan absensi via kamera — mode kiosk; filter kelas opsional untuk gallery lebih kecil */
    public function scan(Request $request)
    {
        $tanggal = $request->tanggal ?: now()->toDateString();
        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selectedKelas = $request->kelas ?: '';

        // Apa yang dibaca kamera kiosk: wajah saja, QR kartu saja, atau keduanya (default).
        $scanKioskMode = \App\Models\Setting::get('scan_kiosk_mode', 'keduanya');
        if (! in_array($scanKioskMode, ['wajah', 'qr', 'keduanya'], true)) {
            $scanKioskMode = 'keduanya';
        }

        // Mode wajah saja: hanya siswa yang sudah daftar wajah (filter kelas di UI JS).
        // Mode dengan QR: SEMUA siswa — kartu pelajar berlaku juga untuk yang belum daftar wajah.
        $siswas = Siswa::with('kelas')
            ->when($scanKioskMode === 'wajah', fn ($q) => $q->whereNotNull('face_descriptor'))
            ->orderBy('nama')
            ->get()
            ->sortBy(fn ($s) => sprintf('%s%s %s', $s->kelas?->tingkat, $s->kelas?->kelas, $s->nama))
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
        $payloadSiswa = $siswas->map(fn ($s) => [
            'uuid'     => $s->uuid,
            'type'     => 'siswa',
            'nama'     => $s->nama,
            'nis'      => $s->nis,
            'jk'       => $s->jk,
            'kelas'    => $s->kelas ? $s->kelas->tingkat.$s->kelas->kelas : '-',
            'id_kelas' => $s->id_kelas,
            // Mode QR saja: descriptor tidak dipakai — jangan kirim (payload jauh lebih ringan).
            'desc'     => $scanKioskMode === 'qr' ? [] : $s->face_descriptor,
            'status'   => $existingSiswa->get($s->uuid)?->status,
            'jam_masuk'=> substr($existingSiswa->get($s->uuid)?->jam_masuk, 0, 5),
        ]);

        $payloadGuru = $gurus->map(fn ($g) => [
            'uuid'       => $g->uuid,
            'type'       => 'guru',
            'nama'       => $g->nama,
            'jk'         => $g->jk,
            // QR kartu hanya untuk siswa — di mode QR saja, guru tidak bisa scan di halaman ini.
            'desc'       => $scanKioskMode === 'qr' ? [] : $g->face_descriptor,
            'nip'        => $g->nip ?: $g->nik,
            'status'     => $existingGuru->get($g->uuid)?->status,
            'pulangDone' => (bool) ($existingGuru->get($g->uuid)?->jam_pulang),
            'jam_masuk'  => substr($existingGuru->get($g->uuid)?->jam_masuk, 0, 5),
            'jam_pulang' => substr($existingGuru->get($g->uuid)?->jam_pulang, 0, 5),
        ]);

        $payload = $payloadSiswa->concat($payloadGuru)->values();

        $isKiosk = \App\Http\Middleware\EnsureKioskOrPermission::hasValidToken($request);
        $kioskToken = $isKiosk ? $request->query('_kiosk') : null;

        $kelasOptions = $kelasList->map(fn ($k) => [
            'uuid'  => $k->uuid,
            'label' => $k->tingkat.$k->kelas,
        ])->values();

        return view('absensi.scan', compact(
            'tanggal', 'siswas', 'gurus', 'payload', 'existingSiswa', 'existingGuru',
            'isKiosk', 'kioskToken', 'kelasList', 'selectedKelas', 'kelasOptions', 'scanKioskMode'
        ));
    }

    /** Telemetry ringan: alasan gagal match wajah (untuk kalibrasi lapangan). */
    public function faceTelemetry(Request $request)
    {
        $data = $request->validate([
            'reason'  => 'required|in:low_score,small_margin,low_support,small_face,low_face_score',
            'top1'    => 'nullable|numeric|min:0|max:1',
            'gap'     => 'nullable|numeric|min:0|max:1',
            'support' => 'nullable|integer|min:0|max:10',
            'kelas'   => 'nullable|string|max:40',
        ]);

        \Illuminate\Support\Facades\Log::info('face_scan_diag', [
            'reason'  => $data['reason'],
            'top1'    => isset($data['top1']) ? round((float) $data['top1'], 3) : null,
            'gap'     => isset($data['gap']) ? round((float) $data['gap'], 3) : null,
            'support' => $data['support'] ?? null,
            'kelas'   => $data['kelas'] ?? null,
            'user'    => auth()->id(),
        ]);

        return response()->json(['ok' => true]);
    }

    /** Fallback absen siswa via barcode/QR kartu pelajar digital (payload = NIS, NISN, atau UUID). */
    public function markByBarcode(Request $request)
    {
        $data = $request->validate([
            'barcode'  => 'required|string|max:80',
            'tanggal'  => 'required|date',
            'id_kelas' => 'nullable|exists:kelas,uuid',
        ]);

        $code = trim($data['barcode']);
        $siswa = $this->resolveSiswaFromBarcode($code, $data['id_kelas'] ?? null);

        if ($siswa === false) {
            return response()->json(['success' => false, 'message' => 'Kode kartu ambigu — hubungi admin untuk verifikasi data siswa.']);
        }
        if (! $siswa) {
            return response()->json(['success' => false, 'message' => 'Kartu tidak dikenali'.(! empty($data['id_kelas']) ? ' di kelas yang dipilih.' : '.')]);
        }

        $existing = Absensi::where('id_siswa', $siswa->uuid)->where('tanggal', $data['tanggal'])->first();
        if ($existing && in_array($existing->status, ['izin', 'sakit', 'alpa'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Siswa sudah tercatat '.ucfirst($existing->status).' — ubah dulu di rekap absensi.',
            ]);
        }

        // Kartu hanya berlaku SEKALI per hari — tanpa guard ini, scan ulang jatuh ke mark()
        // yang cuma meng-update baris lama dan tetap membalas sukses (flash "Selamat datang"
        // berulang, seolah bisa absen berkali-kali).
        if ($existing && $existing->status === 'hadir' && $existing->jam_masuk) {
            return response()->json([
                'success'   => false,
                'duplicate' => true,
                'uuid'      => $siswa->uuid,
                'jam'       => substr($existing->jam_masuk, 0, 5),
                'message'   => $siswa->nama.' sudah absen '.substr($existing->jam_masuk, 0, 5).' — kartu hanya berlaku sekali.',
            ]);
        }

        $request->merge([
            'id_siswa' => $siswa->uuid,
            'id_kelas' => $siswa->id_kelas,
            'status'   => 'hadir',
            '_via'     => 'barcode',
        ]);

        return $this->mark($request);
    }

    /** Cari siswa dari payload kartu: UUID → NIS → NISN (prioritas ketat, hindari OR ambigu). */
    private function resolveSiswaFromBarcode(string $code, ?string $idKelas = null): Siswa|false|null
    {
        $scoped = fn () => Siswa::query()->when($idKelas, fn ($q) => $q->where('id_kelas', $idKelas));

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $code)) {
            return $scoped()->where('uuid', $code)->first();
        }

        $byNis = $scoped()->where('nis', $code)->get();
        if ($byNis->count() > 1) {
            return false;
        }
        if ($byNis->count() === 1) {
            return $byNis->first();
        }

        $byNisn = $scoped()->where('nisn', $code)->get();
        if ($byNisn->count() > 1) {
            return false;
        }

        return $byNisn->first();
    }

    /** Tandai 1 siswa hadir (AJAX dari scan wajah atau fallback kartu pelajar). */
    public function mark(Request $request)
    {
        $data = $request->validate([
            'id_siswa' => 'required|exists:siswa,uuid',
            'id_kelas' => 'nullable|exists:kelas,uuid',
            'tanggal'  => 'required|date',
            'status'   => 'nullable|in:hadir,izin,sakit,alpa',
            '_via'     => 'nullable|in:face,barcode',
        ]);

        // Gate metode per-via — dulu semua yang lewat halaman scan dianggap "metode wajah",
        // sehingga kartu QR ikut tertolak saat cara_absensi_guru = barcode. Sekarang:
        // via wajah butuh metode wajah aktif + kamera kiosk tidak disetel QR-saja;
        // via kartu (barcode/QR) sah selama metode wajah aktif ATAU kamera kiosk membaca QR.
        $viaBarcode = ($data['_via'] ?? 'face') === 'barcode';
        $scanKioskMode = \App\Models\Setting::get('scan_kiosk_mode', 'keduanya');
        $qrKameraAktif = in_array($scanKioskMode, ['qr', 'keduanya'], true);
        $bolehVia = $viaBarcode
            ? (\App\Support\AbsensiGuru::bolehWajah() || $qrKameraAktif)
            : (\App\Support\AbsensiGuru::bolehWajah() && $scanKioskMode !== 'qr');
        if (! $bolehVia) {
            return response()->json([
                'success' => false,
                'message' => $viaBarcode
                    ? 'Absensi via kartu QR sedang dikunci. Ubah mode kamera di Pengaturan → Absensi.'
                    : \App\Support\AbsensiGuru::pesanKunci('Scan Wajah'),
            ]);
        }

        if (! \App\Support\KalenderAbsensi::absenSiswaDibuka($data['tanggal'])) {
            return response()->json([
                'success' => false,
                'message' => 'Absensi siswa tidak dibuka untuk tanggal ini.',
            ]);
        }

        if (! \App\Support\KaihSiswa::bolehAbsen($data['id_siswa'], $data['tanggal'])) {
            return response()->json([
                'success' => false,
                'message' => \App\Support\KaihSiswa::pesanTolak(),
            ]);
        }

        $via = ($data['_via'] ?? 'face') === 'barcode' ? 'Kartu pelajar (barcode)' : 'Scan wajah';

        $row = Absensi::firstOrNew([
            'id_siswa' => $data['id_siswa'],
            'tanggal'  => $data['tanggal'],
        ]);
        $previousStatus = $row->exists ? $row->status : null;
        $row->id_kelas     = $data['id_kelas'] ?? $row->id_kelas;
        $row->status       = $data['status'] ?? 'hadir';
        $row->keterangan   = $via;
        $row->dicatat_oleh = auth()->id();
        $scanPertama = empty($row->jam_masuk);
        if (! $row->jam_masuk) {
            $row->jam_masuk = now()->format('H:i:s');
        }
        if (! $row->exists) {
            $row->id_semester = \App\Models\Semester::aktif()?->id;
        }
        $row->save();

        $batas = Setting::get('waktu_terlambat', '07:30');
        $terlambat = $row->terlambat($batas);
        if ($scanPertama && $terlambat) {
            \App\Http\Controllers\PoinController::autoTerlambat($data['id_siswa'], $data['tanggal']);
        }
        AttendanceParentNotifier::notifyIfStatusChanged($previousStatus, $row);

        $siswa = Siswa::with('kelas')->find($data['id_siswa']);

        return response()->json([
            'success'   => true,
            'jam'       => substr($row->jam_masuk, 0, 5),
            'terlambat' => $terlambat,
            'uuid'      => $data['id_siswa'],
            'nama'      => $siswa?->nama,
            'nis'       => $siswa?->nis,
            'id_kelas'  => $siswa?->id_kelas,
            'kelas'     => $siswa?->kelas ? $siswa->kelas->tingkat.$siswa->kelas->kelas : null,
            'via'       => $data['_via'] ?? 'face',
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
