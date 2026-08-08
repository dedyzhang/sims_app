<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\PeminjamanRequest;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\Peminjaman;
use App\Sarpras\Models\PeminjamanItem;
use App\Sarpras\Services\RuanganJadwalService;
use App\Sarpras\Services\SarprasActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PeminjamanController extends Controller
{
    public function __construct(
        private readonly RuanganJadwalService $jadwalRuangan,
    ) {}

    public function index(Request $request): View
    {
        $user = auth()->user();
        $canKelola = $user->can('sarpras.peminjaman.setujui') || $user->can('sarpras.peminjaman.kelola');
        $tab = $request->query('tab', 'barang');

        $peminjaman = Peminjaman::with(['peminjam:uuid,username', 'ruangan:id,kode,nama,gedung,lantai'])
            ->withCount('items')
            ->when(! $canKelola, fn ($q) => $q->where('peminjam_id', $user->uuid))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()
            ->get();

        $pending = $canKelola
            ? Peminjaman::with(['peminjam:uuid,username', 'ruangan:id,kode,nama'])
                ->where('status', 'diajukan')
                ->whereNotNull('ruangan_id')
                ->orderBy('mulai')
                ->get()
            : collect();

        $summary = collect(DenahRuangan::STATUS)->mapWithKeys(fn ($l, $k) => [
            $k => DenahRuangan::where('status', $k)->count(),
        ]);

        $rooms = DenahRuangan::with('denah:id,nama')
            ->when($request->status_ruangan, fn ($q, $v) => $q->where('status', $v))
            ->orderBy('kode')
            ->get();

        return view('sarpras.peminjaman.index', [
            'peminjaman' => $peminjaman,
            'hanyaMilikSaya' => ! $canKelola,
            'tab' => $tab,
            'pending' => $pending,
            'canApprove' => $canKelola,
            'summary' => $summary,
            'rooms' => $rooms,
            'allRooms' => DenahRuangan::orderBy('kode')->get(['id', 'kode', 'nama']),
            'statusFilter' => (string) $request->status_ruangan,
        ]);
    }

    public function create(): View
    {
        $aset = Aset::where('status', 'aktif')->orderBy('nama')->get(['id', 'kode', 'nama']);
        $ruangan = DenahRuangan::orderBy('kode')->get(['id', 'kode', 'nama']);

        return view('sarpras.peminjaman.create', compact('aset', 'ruangan'));
    }

    public function store(PeminjamanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $mulai = Carbon::parse($data['mulai']);
        $selesai = Carbon::parse($data['selesai']);

        if (! empty($data['ruangan_id']) && $this->jadwalRuangan->bentrok($data['ruangan_id'], $mulai, $selesai)) {
            return back()->withInput()
                ->with('gagal', 'Jadwal bentrok: ruangan sudah dipesan pada rentang waktu tersebut.');
        }

        $asetIds = array_values(array_filter($data['aset_id'] ?? []));
        if ($pesanAset = $this->pesanAsetBentrok($asetIds, $mulai, $selesai)) {
            return back()->withInput()->with('gagal', $pesanAset);
        }

        $peminjaman = DB::transaction(function () use ($request, $data, $mulai, $selesai) {
            $peminjaman = Peminjaman::create([
                'kode' => 'PJM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
                'peminjam_id' => $request->user()->getKey(),
                'ruangan_id' => $data['ruangan_id'] ?? null,
                'keperluan' => $data['keperluan'],
                'mulai' => $mulai,
                'selesai' => $selesai,
                'tgl_pinjam' => $mulai->toDateString(),
                'tgl_kembali_rencana' => $selesai->toDateString(),
                'status' => 'diajukan',
            ]);

            foreach (($data['aset_id'] ?? []) as $asetId) {
                $peminjaman->items()->create([
                    'aset_id' => $asetId,
                    'qty' => (int) ($data['qty'][$asetId] ?? 1),
                ]);
            }

            return $peminjaman;
        });

        SarprasActivityLogger::log('peminjaman.diajukan', $peminjaman, [
            'ruangan_id' => $peminjaman->ruangan_id,
            'jumlah_aset' => $peminjaman->items()->count(),
        ]);

        return redirect()->route('sarpras.peminjaman.show', $peminjaman)
            ->with('sukses', 'Pengajuan peminjaman terkirim.');
    }

    /** Ajukan pemakaian ruangan saja (form cepat dari tab ruangan). */
    public function storeRuangan(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ruangan_id' => ['required', 'uuid', 'exists:sarpras_denah_ruangan,id'],
            'keperluan' => ['required', 'string', 'max:255'],
            'tanggal' => ['required', 'date'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i', 'after:jam_mulai'],
        ], [
            'jam_selesai.after' => 'Jam selesai harus setelah jam mulai.',
        ]);

        $mulai = Carbon::parse($data['tanggal'] . ' ' . $data['jam_mulai']);
        $selesai = Carbon::parse($data['tanggal'] . ' ' . $data['jam_selesai']);

        if ($this->jadwalRuangan->bentrok($data['ruangan_id'], $mulai, $selesai)) {
            return back()->withInput()->with('gagal', 'Jadwal bentrok dengan peminjaman ruangan lain.');
        }

        $peminjaman = Peminjaman::create([
            'kode' => 'PJM-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
            'peminjam_id' => $request->user()->getKey(),
            'ruangan_id' => $data['ruangan_id'],
            'keperluan' => $data['keperluan'],
            'mulai' => $mulai,
            'selesai' => $selesai,
            'tgl_pinjam' => $mulai->toDateString(),
            'tgl_kembali_rencana' => $selesai->toDateString(),
            'status' => 'diajukan',
        ]);

        SarprasActivityLogger::log('peminjaman.ruangan.diajukan', $peminjaman);

        return redirect()->route('sarpras.peminjaman.index', ['tab' => 'ruangan'])
            ->with('sukses', 'Pengajuan pemakaian ruangan terkirim.');
    }

    public function show(Peminjaman $peminjaman): View
    {
        $user = auth()->user();
        $canKelola = $user->can('sarpras.peminjaman.setujui') || $user->can('sarpras.peminjaman.kelola');
        if (! $canKelola && $peminjaman->peminjam_id !== $user->getKey()) {
            abort(403);
        }

        $peminjaman->load(['peminjam:uuid,username', 'penyetuju:uuid,username', 'ruangan:id,kode,nama', 'items.aset:id,kode,nama']);

        return view('sarpras.peminjaman.show', compact('peminjaman'));
    }

    public function setujui(Request $request, Peminjaman $peminjaman): RedirectResponse
    {
        if ($peminjaman->status !== 'diajukan') {
            return back()->with('gagal', 'Peminjaman sudah diproses.');
        }

        if ($peminjaman->ruangan_id && $this->jadwalRuangan->bentrok(
            $peminjaman->ruangan_id,
            $peminjaman->mulai,
            $peminjaman->selesai,
            $peminjaman->id,
        )) {
            return back()->with('gagal', 'Tidak bisa disetujui: jadwal ruangan bentrok dengan peminjaman lain.');
        }

        $asetIds = $peminjaman->items()->pluck('aset_id')->filter()->all();
        if ($pesanAset = $this->pesanAsetBentrok($asetIds, $peminjaman->mulai, $peminjaman->selesai, $peminjaman->id)) {
            return back()->with('gagal', 'Tidak bisa disetujui: ' . $pesanAset);
        }

        $asetTidakAktif = Aset::whereIn('id', $asetIds)->where('status', '!=', 'aktif')->exists();
        if ($asetTidakAktif) {
            return back()->with('gagal', 'Tidak bisa disetujui: ada aset yang tidak tersedia (sudah dipinjam atau sedang perbaikan).');
        }

        DB::transaction(function () use ($request, $peminjaman) {
            $peminjaman->update([
                'status' => 'dipinjam',
                'disetujui_oleh' => $request->user()->getKey(),
                'disetujui_pada' => now(),
            ]);
            $peminjaman->items()->with('aset')->get()
                ->each(fn ($it) => $it->aset?->update(['status' => 'dipinjam']));
        });

        SarprasActivityLogger::log('peminjaman.disetujui', $peminjaman);

        return back()->with('sukses', 'Peminjaman disetujui.');
    }

    public function tolak(Request $request, Peminjaman $peminjaman): RedirectResponse
    {
        $request->validate(['alasan_tolak' => ['required', 'string', 'max:1000']]);
        if ($peminjaman->status !== 'diajukan') {
            return back()->with('gagal', 'Peminjaman sudah diproses.');
        }
        $peminjaman->update([
            'status' => 'ditolak',
            'alasan_tolak' => $request->alasan_tolak,
            'disetujui_oleh' => $request->user()->getKey(),
            'disetujui_pada' => now(),
        ]);

        SarprasActivityLogger::log('peminjaman.ditolak', $peminjaman);

        return back()->with('sukses', 'Peminjaman ditolak.');
    }

    public function kembalikan(Request $request, Peminjaman $peminjaman): RedirectResponse
    {
        if (! in_array($peminjaman->status, ['dipinjam', 'terlambat'])) {
            return back()->with('gagal', 'Status peminjaman tidak bisa dikembalikan.');
        }

        DB::transaction(function () use ($peminjaman) {
            $peminjaman->update([
                'status' => 'dikembalikan',
                'tgl_kembali_aktual' => now()->toDateString(),
            ]);
            $peminjaman->items()->with('aset')->get()
                ->each(fn ($it) => $it->aset?->update(['status' => 'aktif']));
        });

        SarprasActivityLogger::log('peminjaman.dikembalikan', $peminjaman);

        return back()->with('sukses', 'Peminjaman selesai / aset dikembalikan.');
    }

    /**
     * @param  list<string>  $asetIds
     */
    private function pesanAsetBentrok(array $asetIds, Carbon $mulai, Carbon $selesai, ?string $kecualiPeminjamanId = null): ?string
    {
        if ($asetIds === []) {
            return null;
        }

        $item = PeminjamanItem::query()
            ->whereIn('aset_id', $asetIds)
            ->whereHas('peminjaman', function ($q) use ($mulai, $selesai, $kecualiPeminjamanId) {
                $q->whereIn('status', ['diajukan', 'dipinjam', 'terlambat'])
                    ->where('mulai', '<', $selesai)
                    ->where('selesai', '>', $mulai)
                    ->when($kecualiPeminjamanId, fn ($q) => $q->where('id', '!=', $kecualiPeminjamanId));
            })
            ->with('aset:id,kode,nama')
            ->first();

        if (! $item) {
            return null;
        }

        $label = $item->aset?->kode ?? $item->aset_id;

        return "Aset {$label} sudah diajukan atau dipinjam pada rentang waktu tersebut.";
    }
}
