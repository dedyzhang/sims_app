<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\PresensiGuru;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\GuruIzinPulangNotification;
use App\Notifications\GuruTerlambatNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class PresensiGuruController extends Controller
{
    /** Daftar presensi guru harian (lihat + koreksi manual) */
    public function index(Request $request)
    {
        $tanggal = $request->tanggal ?: now()->toDateString();
        $gurus = Guru::orderBy('nama')->get();
        $existing = PresensiGuru::whereDate('tanggal', $tanggal)->get()->keyBy('id_guru');
        $batas = Setting::get('waktu_terlambat_guru', Setting::get('waktu_terlambat', '07:30'));

        return view('presensi_guru.index', compact('gurus', 'existing', 'tanggal', 'batas'));
    }

    /** Rekap presensi guru rentang tanggal + deteksi terlambat */
    public function rekap(Request $request)
    {
        $dari   = $request->dari   ?: now()->startOfMonth()->toDateString();
        $sampai = $request->sampai ?: now()->toDateString();
        if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];

        $batas = Setting::get('waktu_terlambat_guru', Setting::get('waktu_terlambat', '07:30'));
        $dates = AbsensiController::dateRange($dari, $sampai);

        $gurus = Guru::orderBy('nama')->get();
        $pres = PresensiGuru::whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->get()->groupBy('id_guru');

        $rekap = $gurus->map(function ($g) use ($pres, $batas) {
            $rows = $pres->get($g->uuid, collect());
            $hadir = $rows->where('status', 'hadir');
            return [
                'guru'      => $g,
                'hadir'     => $hadir->count(),
                'terlambat' => $hadir->filter(fn($r) => $r->terlambat($batas))->count(),
                'izin'      => $rows->where('status', 'izin')->count(),
                'sakit'     => $rows->where('status', 'sakit')->count(),
                'alpa'      => $rows->where('status', 'alpa')->count(),
                'byDate'    => $rows->keyBy(fn($r) => $r->tanggal->format('Y-m-d')),
            ];
        });

        return view('presensi_guru.rekap', compact('rekap', 'dari', 'sampai', 'batas', 'dates'));
    }

    /** Simpan koreksi manual presensi guru */
    public function store(Request $request)
    {
        $request->validate([
            'tanggal' => 'required|date',
            'status'  => 'nullable|array',   // hanya guru yang ditandai yang disimpan
        ]);

        $tanggal = $request->tanggal;
        $count = 0;
        foreach (($request->status ?? []) as $guruUuid => $status) {
            if (!in_array($status, array_keys(PresensiGuru::STATUS))) continue;

            $row = PresensiGuru::firstOrNew(['id_guru' => $guruUuid, 'tanggal' => $tanggal]);
            $row->status       = $status;
            $row->dicatat_oleh = auth()->id();
            $ket = $request->keterangan[$guruUuid] ?? null;
            if ($ket !== null && $ket !== '') {
                $row->keterangan = $ket;
            }
            // jam_masuk & jam_pulang SENGAJA tidak disentuh → waktu scan tetap
            $row->save();
            $count++;
        }

        return back()->with('success', "Presensi {$count} guru tersimpan untuk " . Carbon::parse($tanggal)->isoFormat('D MMM Y') . '.');
    }

    /** Scan presensi guru kini DISATUKAN dengan scan siswa di satu tempat */
    public function scan(Request $request)
    {
        return redirect()->route('absensi.scan', $request->only('tanggal'));
    }

    /** Tandai 1 guru hadir (AJAX dari scan wajah) */
    public function mark(Request $request)
    {
        $data = $request->validate([
            'id_guru' => 'required|exists:gurus,uuid',
            'tanggal' => 'required|date',
            'status'  => 'nullable|in:hadir,izin,sakit,alpa',
            'mode'    => 'nullable|in:masuk,pulang',
            '_via'    => 'nullable|in:face,barcode',
        ]);
        $mode = $data['mode'] ?? 'masuk';

        // Gate metode per-via (pola sama dgn AbsensiController::mark siswa): via wajah butuh
        // metode wajah aktif sekolah + kamera kiosk tidak disetel QR-saja; via Kartu ID (barcode/
        // QR) sah selama metode wajah aktif ATAU kamera kiosk membaca QR — supaya Kartu ID Guru
        // tetap bisa dipakai walau sekolah menyetel "Scan Wajah" sbg metode self-service default.
        $viaBarcode = ($data['_via'] ?? 'face') === 'barcode';
        $scanKioskMode = \App\Models\Setting::get('scan_kiosk_mode', 'keduanya');
        $bolehVia = $viaBarcode
            ? (\App\Support\AbsensiGuru::bolehWajah() || in_array($scanKioskMode, ['qr', 'keduanya'], true))
            : (\App\Support\AbsensiGuru::bolehWajah() && $scanKioskMode !== 'qr');
        if (! $bolehVia) {
            return response()->json([
                'success' => false,
                'blocked' => true,
                'message' => $viaBarcode
                    ? 'Absensi via Kartu ID sedang dikunci. Ubah mode kamera di Pengaturan → Absensi.'
                    : \App\Support\AbsensiGuru::pesanKunci('Scan Wajah'),
            ]);
        }

        if ($mode === 'pulang') {
            $guru = Guru::find($data['id_guru']);

            // Jam pulang wajib per-guru (opsional, diatur admin): guru dengan jadwal
            // pulang tertentu hanya boleh scan pulang pada jam itu atau setelahnya.
            // Kosong ("bebas") = tak ada batasan, perilaku lama.
            if ($guru && $guru->jam_pulang_wajib) {
                $sekarang = now()->format('H:i:s');
                if ($sekarang < $guru->jam_pulang_wajib) {
                    return response()->json([
                        'success' => false,
                        'blocked' => true,
                        'message' => 'Belum waktunya pulang. Jadwal pulang Anda pukul ' . substr($guru->jam_pulang_wajib, 0, 5) . '.',
                    ]);
                }
            }

            // Guru wajib melengkapi agenda hari ini sebelum boleh absen pulang.
            $belum = $guru ? \App\Support\AgendaGuru::belumDiisi($guru, $data['tanggal']) : [];
            if (!empty($belum) && \App\Support\AgendaGuru::wajibSebelumPulang()) {
                return response()->json([
                    'success' => false,
                    'blocked' => true,
                    'message' => \App\Support\AgendaGuru::pesanTolak($belum),
                    'belum'   => $belum,
                ]);
            }
        }

        $row = PresensiGuru::firstOrNew([
            'id_guru' => $data['id_guru'],
            'tanggal' => $data['tanggal'],
        ]);
        $row->status       = 'hadir';
        $row->dicatat_oleh = auth()->id();
        if (empty($row->keterangan)) {
            $row->keterangan = $viaBarcode ? 'Kartu ID (barcode)' : 'Scan wajah';
        }

        if ($mode === 'pulang') {
            // jam pulang dicatat SEKALI (scan pertama terkunci, tidak ditimpa)
            if (empty($row->jam_pulang)) {
                $row->jam_pulang = now()->format('H:i:s');
            }
        } else {
            // jam masuk dicatat sekali (scan pertama)
            if (empty($row->jam_masuk)) {
                $row->jam_masuk = now()->format('H:i:s');
            }
        }
        $row->save();

        $batas = Setting::get('waktu_terlambat_guru', Setting::get('waktu_terlambat', '07:30'));
        return response()->json([
            'success'   => true,
            'mode'      => $mode,
            'jam'       => substr($mode === 'pulang' ? $row->jam_pulang : $row->jam_masuk, 0, 5),
            'terlambat' => $mode === 'masuk' && $row->terlambat($batas),
        ]);
    }

    /** Presensi Saya (guru): riwayat sendiri + akses ke form keterlambatan & izin pulang awal. */
    public function self(Request $request)
    {
        $guru = auth()->user()->guru;
        abort_unless($guru, 403, 'Halaman ini khusus untuk akun guru.');

        $dari   = $request->dari   ?: now()->startOfMonth()->toDateString();
        $sampai = $request->sampai ?: now()->toDateString();
        if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];

        $batas = Setting::get('waktu_terlambat_guru', Setting::get('waktu_terlambat', '07:30'));

        $riwayat = PresensiGuru::where('id_guru', $guru->uuid)
            ->whereDate('tanggal', '>=', $dari)
            ->whereDate('tanggal', '<=', $sampai)
            ->orderByDesc('tanggal')
            ->get();

        $today = PresensiGuru::where('id_guru', $guru->uuid)->whereDate('tanggal', now())->first();
        $belumAgenda = \App\Support\AgendaGuru::belumDiisi($guru);

        // Izin pulang awal menyesuaikan metode absensi aktif sekolah: QR scan (dilengkapi
        // lokasi) dan/atau verifikasi kamera wajah — kalau cara_absensi_guru = 'keduanya',
        // KEDUA jalur tersedia dan guru bisa pilih (lihat toggle di presensi_guru/self.blade.php).
        $bolehQr = \App\Support\AbsensiGuru::bolehQr();
        $bolehWajahMandiri = \App\Support\AbsensiGuru::bolehWajah();
        $qrLat    = Setting::get('sekolah_lat');
        $qrLng    = Setting::get('sekolah_lng');
        $qrRadius = (float) Setting::get('absen_radius', 200);

        return view('presensi_guru.self', compact(
            'guru', 'riwayat', 'batas', 'dari', 'sampai', 'today', 'belumAgenda',
            'bolehQr', 'bolehWajahMandiri', 'qrLat', 'qrLng', 'qrRadius'
        ));
    }

    /** Simpan alasan keterlambatan utk presensi masuk hari ini (hanya milik sendiri). */
    public function keterlambatanStore(Request $request)
    {
        $guru = auth()->user()->guru;
        abort_unless($guru, 403);

        $data = $request->validate(['keterangan' => 'required|string|max:500']);

        $batas = Setting::get('waktu_terlambat_guru', Setting::get('waktu_terlambat', '07:30'));
        $row = PresensiGuru::where('id_guru', $guru->uuid)->whereDate('tanggal', now())->first();
        if (!$row || $row->status !== 'hadir' || !$row->terlambat($batas)) {
            return back()->with('error', 'Anda tidak tercatat terlambat hari ini.');
        }

        $row->keterangan = $data['keterangan'];
        $row->save();

        Notification::send($this->notifRecipients(), new GuruTerlambatNotification($row));

        return back()->with('success', 'Keterangan keterlambatan tersimpan.');
    }

    /**
     * Izin pulang awal (self-service): guru sudah diverifikasi wajahnya di sisi klien
     * (kamera sendiri, bukan kiosk), lalu jam pulang dicatat langsung. Sengaja TIDAK
     * dicek AgendaGuru::belumDiisi() — izin pulang awal memang berarti sebagian jam
     * mengajar hari ini belum selesai/terisi; itu wajar & ditandai lewat alasan.
     */
    public function izinPulangStore(Request $request)
    {
        $guru = auth()->user()->guru;
        abort_unless($guru, 403);

        $data = $request->validate(['alasan' => 'required|string|max:500']);

        $row = PresensiGuru::where('id_guru', $guru->uuid)->whereDate('tanggal', now())->first();
        if (!$row || empty($row->jam_masuk)) {
            return response()->json(['success' => false, 'message' => 'Anda belum tercatat absen masuk hari ini.'], 422);
        }
        if (!empty($row->jam_pulang)) {
            return response()->json(['success' => false, 'message' => 'Anda sudah tercatat pulang hari ini.'], 422);
        }

        $row->jam_pulang = now()->format('H:i:s');
        $row->keterangan = trim(($row->keterangan ? $row->keterangan . ' | ' : '') . 'Izin pulang awal: ' . $data['alasan']);
        $row->save();

        Notification::send($this->notifRecipients(), new GuruIzinPulangNotification($row, $data['alasan']));

        return response()->json(['success' => true, 'jam' => substr($row->jam_pulang, 0, 5)]);
    }

    /** Kelola jam pulang wajib per guru (admin) — daftar guru + status jadwal pulangnya. */
    public function jamPulang(Request $request)
    {
        $search = trim((string) $request->search);
        $gurus = Guru::query()
            ->when($search !== '', fn ($q) => $q->where('nama', 'like', "%{$search}%"))
            ->orderBy('nama')
            ->get();

        return view('presensi_guru.jam-pulang', compact('gurus', 'search'));
    }

    /** Terapkan/hapus jam pulang wajib utk guru terpilih sekaligus. */
    public function jamPulangUpdate(Request $request)
    {
        $data = $request->validate([
            'guru_ids'         => 'required|array|min:1',
            'guru_ids.*'       => 'exists:gurus,uuid',
            'jam_pulang_wajib' => 'nullable|date_format:H:i',
        ]);

        $jam = $data['jam_pulang_wajib'] ?? null;
        Guru::whereIn('uuid', $data['guru_ids'])->update(['jam_pulang_wajib' => $jam]);

        $pesan = $jam
            ? count($data['guru_ids']) . ' guru dijadwalkan pulang pukul ' . $jam . '.'
            : count($data['guru_ids']) . ' guru dibebaskan dari jadwal pulang (bebas).';

        return back()->with('success', $pesan);
    }

    /** Kepala Sekolah & Admin — penerima notifikasi keterlambatan/izin pulang guru. */
    private function notifRecipients()
    {
        return User::query()->whereIn('access', ['kepala', 'admin', 'superadmin'])->get();
    }

    /** Batalkan absen masuk/pulang dari scan wajah */
    public function cancel(Request $request)
    {
        $data = $request->validate([
            'id_guru' => 'required|exists:gurus,uuid',
            'tanggal' => 'required|date',
            'mode'    => 'required|in:masuk,pulang',
        ]);

        $row = PresensiGuru::where('id_guru', $data['id_guru'])->where('tanggal', $data['tanggal'])->first();
        if ($row) {
            if ($data['mode'] === 'pulang') {
                $row->jam_pulang = null;
                $row->save();
            } else {
                $row->delete();
            }
        }

        return response()->json(['success' => true]);
    }
}
