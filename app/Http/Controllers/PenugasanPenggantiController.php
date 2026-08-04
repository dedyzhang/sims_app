<?php

namespace App\Http\Controllers;

use App\Models\GuruTidakHadir;
use App\Models\Guru;
use App\Models\PenugasanPengganti;
use App\Services\Piket\JamKosongService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PenugasanPenggantiController extends Controller
{
    public function __construct(private JamKosongService $jamKosong)
    {
    }

    /**
     * Daftar jam kosong + status penugasan untuk satu tanggal (default hari ini).
     * Otomatis membuat baris penugasan_pengganti (status 'menunggu') untuk tiap jam
     * kosong yang belum punya baris, berdasarkan guru_tidak_hadir hari ini (Fase 2).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', PenugasanPengganti::class);

        $tanggal = $request->tanggal && preg_match('/^\d{4}-\d{2}-\d{2}$/', $request->tanggal)
            ? $request->tanggal : now()->toDateString();

        $this->sinkronDariGuruTidakHadir($tanggal);

        $daftar = PenugasanPengganti::with(['guruTidakHadir.guru:uuid,nama', 'jadwal.kelas:uuid,tingkat,kelas', 'jadwal.pelajaran:uuid,nama', 'guruPengganti:uuid,nama', 'guruPiket:uuid,nama'])
            ->whereHas('guruTidakHadir', fn ($q) => $q->where('tanggal', $tanggal))
            ->get()
            ->map(function (PenugasanPengganti $p) {
                return [
                    'model' => $p,
                    'guru_tersedia' => $p->status === 'menunggu' && $p->jadwal
                        ? $this->jamKosong->guruTersediaUntuk($p->jadwal, $p->guruTidakHadir->tanggal->toDateString())
                        : collect(),
                ];
            });

        $ringkasan = [
            'menunggu' => $daftar->filter(fn ($d) => $d['model']->status === 'menunggu')->count(),
            'ditugaskan' => $daftar->filter(fn ($d) => $d['model']->status === 'ditugaskan')->count(),
            'selesai' => $daftar->filter(fn ($d) => $d['model']->status === 'selesai')->count(),
        ];

        $bolehKelola = auth()->user()->can('manage', [PenugasanPengganti::class, $tanggal]);
        $guruPiketId = auth()->user()->guru?->uuid;

        return view('piket.penugasan', compact('daftar', 'tanggal', 'ringkasan', 'bolehKelola', 'guruPiketId'));
    }

    public function assign(Request $request, PenugasanPengganti $penugasanPengganti)
    {
        $tanggal = $penugasanPengganti->guruTidakHadir->tanggal->toDateString();
        $this->authorize('manage', [PenugasanPengganti::class, $tanggal]);

        $data = $request->validate([
            'id_guru_pengganti' => ['required', 'string', 'exists:gurus,uuid'],
        ]);

        $penugasanPengganti = DB::transaction(function () use ($penugasanPengganti, $data, $tanggal) {
            $slot = PenugasanPengganti::with(['jadwal', 'guruTidakHadir'])
                ->whereKey($penugasanPengganti->uuid)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($slot->status !== 'menunggu', 422, 'Jam ini sudah memiliki penugasan.');
            abort_unless($slot->jadwal, 422, 'Jadwal sekolah untuk slot ini tidak ditemukan.');

            // Serialisasi assignment untuk guru kandidat yang sama agar dua ketua
            // piket tidak dapat memilih orang yang sama pada slot waktu bersamaan.
            Guru::whereKey($data['id_guru_pengganti'])->lockForUpdate()->firstOrFail();
            $tersedia = $this->jamKosong->guruTersediaUntuk($slot->jadwal, $tanggal);
            abort_unless(
                $tersedia->contains('uuid', $data['id_guru_pengganti']),
                422,
                'Guru tersebut sedang mengajar, tidak hadir, atau sudah ditugaskan pada jam yang sama.'
            );

            $slot->update([
                'id_guru_pengganti' => $data['id_guru_pengganti'],
                'id_guru_piket' => null,
                'status' => 'ditugaskan',
            ]);
            $slot->load('guruPengganti:uuid,nama');

            return $slot;
        });

        activity('piket')
            ->causedBy(auth()->user())
            ->performedOn($penugasanPengganti)
            ->withProperties(['id_guru_pengganti' => $data['id_guru_pengganti'], 'tanggal' => $tanggal])
            ->log('Tugaskan guru pengganti');

        return response()->json($this->formatSlot($penugasanPengganti));
    }

    public function ambilAlih(PenugasanPengganti $penugasanPengganti)
    {
        $tanggal = $penugasanPengganti->guruTidakHadir->tanggal->toDateString();
        $this->authorize('manage', [PenugasanPengganti::class, $tanggal]);

        $idGuruPiket = auth()->user()->guru?->uuid;
        abort_unless($idGuruPiket, 422, 'Akun ini tidak memiliki profil guru.');

        $penugasanPengganti = DB::transaction(function () use ($penugasanPengganti, $idGuruPiket) {
            $slot = PenugasanPengganti::whereKey($penugasanPengganti->uuid)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($slot->status !== 'menunggu', 422, 'Jam ini sudah memiliki penugasan.');

            $slot->update([
                'id_guru_pengganti' => null,
                'id_guru_piket' => $idGuruPiket,
                'status' => 'ditugaskan',
            ]);

            $slot->load('guruPiket:uuid,nama');

            return $slot;
        });

        activity('piket')
            ->causedBy(auth()->user())
            ->performedOn($penugasanPengganti)
            ->withProperties(['id_guru_piket' => $idGuruPiket, 'tanggal' => $tanggal])
            ->log('Piket ambil alih jam kosong');

        return response()->json($this->formatSlot($penugasanPengganti));
    }

    public function selesai(PenugasanPengganti $penugasanPengganti)
    {
        $tanggal = $penugasanPengganti->guruTidakHadir->tanggal->toDateString();
        $this->authorize('manage', [PenugasanPengganti::class, $tanggal]);

        $penugasanPengganti = DB::transaction(function () use ($penugasanPengganti) {
            $slot = PenugasanPengganti::whereKey($penugasanPengganti->uuid)
                ->lockForUpdate()
                ->firstOrFail();

            abort_if($slot->status !== 'ditugaskan', 422, 'Hanya penugasan aktif yang dapat diselesaikan.');
            $slot->update(['status' => 'selesai']);

            return $slot;
        });

        return response()->json($this->formatSlot($penugasanPengganti));
    }

    /** Buat baris penugasan_pengganti (status 'menunggu') untuk tiap jam kosong yang belum punya baris. */
    private function sinkronDariGuruTidakHadir(string $tanggal): void
    {
        $guruTidakHadir = GuruTidakHadir::where('tanggal', $tanggal)->get();

        foreach ($guruTidakHadir as $g) {
            $slots = $this->jamKosong->untukGuru($g->id_guru, $tanggal);

            DB::transaction(function () use ($g, $slots) {
                foreach ($slots as $slot) {
                    PenugasanPengganti::firstOrCreate(
                        ['id_guru_tidak_hadir' => $g->uuid, 'id_jadwal' => $slot->uuid],
                        ['status' => 'menunggu']
                    );
                }
            });
        }
    }

    private function formatSlot(PenugasanPengganti $p): array
    {
        return [
            'id' => $p->uuid,
            'status' => $p->status,
            'guru_pengisi' => $p->guru_pengisi,
        ];
    }
}
