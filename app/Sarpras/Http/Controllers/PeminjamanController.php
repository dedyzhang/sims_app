<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\PeminjamanRequest;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\BookingRuangan;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\Peminjaman;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PeminjamanController extends Controller
{
    public function index(Request $request): View
    {
        $user = auth()->user();
        $canKelola = $user->can('sarpras.peminjaman.setujui') || $user->can('sarpras.peminjaman.kelola');

        $peminjaman = Peminjaman::with(['peminjam:uuid,username', 'ruangan:id,kode,nama'])
            ->withCount('items')
            ->when(! $canKelola, fn ($q) => $q->where('peminjam_id', $user->uuid))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->get();

        // Panel samping: operator lihat semua; staff hanya booking miliknya.
        $bookings = BookingRuangan::with(['ruangan:id,kode,nama,gedung,lantai', 'pemohon'])
            ->when(! $canKelola, fn ($q) => $q->where('pemohon_id', $user->uuid))
            ->latest('mulai')->limit(15)->get();

        return view('sarpras.peminjaman.index', [
            'peminjaman' => $peminjaman,
            'bookings' => $bookings,
            'hanyaMilikSaya' => ! $canKelola,
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

        // Normalisasi periode ke Carbon agar binding query konsisten ('Y-m-d H:i:s').
        $mulai = Carbon::parse($data['mulai']);
        $selesai = Carbon::parse($data['selesai']);

        // Cek bentrok ruangan bila ruangan dipilih.
        if (! empty($data['ruangan_id'])) {
            $bentrok = Peminjaman::query()
                ->bentrok($data['ruangan_id'], $mulai, $selesai)
                ->exists();

            if ($bentrok) {
                return back()->withInput()
                    ->with('gagal', 'Jadwal bentrok: ruangan sudah dipesan pada rentang waktu tersebut.');
            }
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
                // qty di-key berdasarkan id aset agar tidak salah pasang.
                $peminjaman->items()->create([
                    'aset_id' => $asetId,
                    'qty' => (int) ($data['qty'][$asetId] ?? 1),
                ]);
            }

            return $peminjaman;
        });

        return redirect()->route('sarpras.peminjaman.show', $peminjaman)
            ->with('sukses', 'Pengajuan peminjaman terkirim.');
    }

    public function show(Peminjaman $peminjaman): View
    {
        $peminjaman->load(['peminjam:uuid,username', 'penyetuju:uuid,username', 'ruangan:id,kode,nama', 'items.aset:id,kode,nama']);

        return view('sarpras.peminjaman.show', compact('peminjaman'));
    }

    public function setujui(Request $request, Peminjaman $peminjaman): RedirectResponse
    {
        if ($peminjaman->status !== 'diajukan') {
            return back()->with('gagal', 'Peminjaman sudah diproses.');
        }

        DB::transaction(function () use ($request, $peminjaman) {
            $peminjaman->update([
                'status' => 'dipinjam',
                'disetujui_oleh' => $request->user()->getKey(),
                'disetujui_pada' => now(),
            ]);
            // Tandai aset sedang dipinjam.
            $peminjaman->items()->with('aset')->get()
                ->each(fn ($it) => $it->aset?->update(['status' => 'dipinjam']));
        });

        return back()->with('sukses', 'Peminjaman disetujui & aset ditandai dipinjam.');
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

        return back()->with('sukses', 'Aset dikembalikan.');
    }
}
