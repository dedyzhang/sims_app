<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\GuruTidakHadir;
use App\Models\PresensiGuru;
use App\Services\Piket\JamKosongService;
use App\Models\PenugasanPengganti;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GuruTidakHadirController extends Controller
{
    public function __construct(private JamKosongService $jamKosong)
    {
    }

    /**
     * Daftar guru tidak hadir untuk satu tanggal (default hari ini): auto-sync dari
     * presensi_gurus + entri manual, plus jam kosong per guru dari jadwals.
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', GuruTidakHadir::class);

        $tanggal = $request->tanggal && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->tanggal)
            ? $request->tanggal : now()->toDateString();

        $this->syncDariPresensi($tanggal);

        $daftar = GuruTidakHadir::with(['guru:uuid,nama', 'pencatat:uuid,username'])
            ->where('tanggal', $tanggal)
            ->orderBy('created_at')
            ->get()
            ->map(function (GuruTidakHadir $g) {
                return [
                    'model' => $g,
                    'jam_kosong' => $this->jamKosong->untukGuru($g->id_guru, $g->tanggal->toDateString())
                        ->map(fn ($j) => $this->jamKosong->format($j))->values()->all(),
                ];
            });

        $guruList = Guru::orderBy('nama')->get(['uuid', 'nama']);
        $bolehInput = auth()->user()->can('create', GuruTidakHadir::class);

        return view('piket.tidak-hadir', compact('daftar', 'tanggal', 'guruList', 'bolehInput'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', GuruTidakHadir::class);

        $data = $request->validate([
            'id_guru' => ['required', 'string', 'exists:gurus,uuid'],
            'tanggal' => ['required', 'date'],
            'alasan' => ['required', 'string', 'in:sakit,izin,dinas_luar,alpa'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $entri = GuruTidakHadir::updateOrCreate(
            ['id_guru' => $data['id_guru'], 'tanggal' => $data['tanggal']],
            [
                'sumber' => 'manual_piket',
                'alasan' => $data['alasan'],
                'keterangan' => $data['keterangan'] ?? null,
                'dicatat_oleh' => auth()->id(),
            ]
        );

        activity('piket')
            ->causedBy(auth()->user())
            ->performedOn($entri)
            ->withProperties(['alasan' => $data['alasan'], 'tanggal' => $data['tanggal']])
            ->log('Catat guru tidak hadir (manual)');

        $entri->load('guru:uuid,nama');

        return response()->json([
            'id' => $entri->uuid,
            'id_guru' => $entri->id_guru,
            'nama' => $entri->guru?->nama ?? '-',
            'tanggal' => $entri->tanggal->toDateString(),
            'sumber' => $entri->sumber,
            'alasan' => $entri->alasan,
            'keterangan' => $entri->keterangan,
            'jam_kosong' => $this->jamKosong->untukGuru($entri->id_guru, $entri->tanggal->toDateString())
                ->map(fn ($j) => $this->jamKosong->format($j))->values()->all(),
        ]);
    }

    private function syncDariPresensi(string $tanggal): void
    {
        $presensiTidakHadir = PresensiGuru::whereIn('status', array_keys(GuruTidakHadir::ALASAN_DARI_PRESENSI))
            ->whereDate('tanggal', $tanggal)
            ->get();

        $existingIds = GuruTidakHadir::where('tanggal', $tanggal)
            ->pluck('id_guru')
            ->toArray();

        foreach ($presensiTidakHadir as $presensi) {
            if (in_array($presensi->id_guru, $existingIds)) {
                continue;
            }

            GuruTidakHadir::create([
                'id_guru' => $presensi->id_guru,
                'tanggal' => $tanggal,
                'sumber' => 'otomatis_presensi',
                'alasan' => GuruTidakHadir::ALASAN_DARI_PRESENSI[$presensi->status],
                'keterangan' => $presensi->keterangan,
                'id_presensi_guru' => $presensi->uuid,
            ]);
            
            $existingIds[] = $presensi->id_guru;
        }
    }

    public function laporMandiri(Request $request)
    {
        $this->authorize('laporMandiri', GuruTidakHadir::class);

        $data = $request->validate([
            'alasan' => ['required', 'string', 'in:sakit,izin,dinas_luar,alpa'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);

        $idGuru = auth()->user()->guru->uuid;
        $tanggal = now()->toDateString();

        DB::transaction(function () use ($data, $idGuru, $tanggal) {
            $entri = GuruTidakHadir::updateOrCreate(
                ['id_guru' => $idGuru, 'tanggal' => $tanggal],
                [
                    'sumber' => 'lapor_mandiri',
                    'alasan' => $data['alasan'],
                    'keterangan' => $data['keterangan'] ?? null,
                    'dicatat_oleh' => auth()->id(),
                ]
            );

            // Auto-create penugasan pengganti (jam kosong)
            $slots = $this->jamKosong->untukGuru($idGuru, $tanggal);
            foreach ($slots as $slot) {
                PenugasanPengganti::firstOrCreate(
                    ['id_guru_tidak_hadir' => $entri->uuid, 'id_jadwal' => $slot->uuid],
                    ['status' => 'menunggu']
                );
            }

            activity('piket')
                ->causedBy(auth()->user())
                ->performedOn($entri)
                ->withProperties(['alasan' => $data['alasan'], 'tanggal' => $tanggal])
                ->log('Lapor ketidakhadiran mandiri');
        });

        return response()->json(['message' => 'Ketidakhadiran berhasil dilaporkan. Silakan unggah tugas Anda.']);
    }
}
