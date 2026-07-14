<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\PresensiGuru;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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
        ]);
        $mode = $data['mode'] ?? 'masuk';

        // Metode absensi guru aktif harus "Scan Wajah".
        if (!\App\Support\AbsensiGuru::bolehWajah()) {
            return response()->json([
                'success' => false,
                'blocked' => true,
                'message' => \App\Support\AbsensiGuru::pesanKunci('Scan Wajah'),
            ]);
        }

        // Guru wajib melengkapi agenda hari ini sebelum boleh absen pulang.
        if ($mode === 'pulang') {
            $guru = Guru::find($data['id_guru']);
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
            $row->keterangan = 'Scan wajah';
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
