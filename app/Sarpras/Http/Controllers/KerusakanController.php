<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Sarpras\Http\Requests\LaporKerusakanRequest;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\LaporanKerusakan;
use App\Sarpras\Models\LaporanKerusakanFoto;
use App\Sarpras\Models\Perbaikan;
use App\Sarpras\Notifications\KerusakanDilaporkan;
use App\Sarpras\Services\FotoCompressor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class KerusakanController extends Controller
{
    private const WAKA_SARPRAS_ACCESS = ['sarpras', 'sapras'];

    public function index(Request $request): View
    {
        $user = auth()->user();
        $canKelola = $user->can('sarpras.kerusakan.kelola');

        // Eager load pelapor + foto untuk cegah N+1.
        // Staff hanya melihat laporan miliknya; operator melihat semua.
        $laporan = LaporanKerusakan::with(['pelapor:uuid,username', 'aset:id,nama', 'ruangan:id,kode,nama'])
            ->when(! $canKelola, fn ($q) => $q->where('pelapor_id', $user->uuid))
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->get();

        return view('sarpras.kerusakan.index', [
            'laporan' => $laporan,
            'hanyaMilikSaya' => ! $canKelola,
        ]);
    }

    public function create(Request $request): View
    {
        $aset = Aset::orderBy('nama')->get(['id', 'nama', 'kode']);
        $ruangan = DenahRuangan::orderBy('kode')->get(['id', 'kode', 'nama']);
        // Pra-pilih dari halaman detail ruangan (tombol "Lapor Kerusakan").
        $ruanganTerpilih = $request->query('ruangan_id');

        return view('sarpras.kerusakan.create', compact('aset', 'ruangan', 'ruanganTerpilih'));
    }

    public function store(LaporKerusakanRequest $request, FotoCompressor $compressor): RedirectResponse
    {
        $data = $request->validated();

        try {
            $laporan = DB::transaction(function () use ($request, $data, $compressor) {
                $laporan = LaporanKerusakan::create([
                    'kode' => 'LK-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
                    'aset_id' => $data['aset_id'] ?? null,
                    'ruangan_id' => $data['ruangan_id'] ?? null,
                    'pelapor_id' => $request->user()->getKey(),
                    'deskripsi' => $data['deskripsi'],
                    'urgensi' => $data['urgensi'],
                    'status' => 'dilaporkan',
                ]);

                // Kompres SETIAP foto (<=2MB) lalu simpan path relatif.
                // Bila kompresi gagal -> exception -> transaksi rollback.
                if ($request->hasFile('foto')) {
                    foreach ($request->file('foto') as $foto) {
                        $path = $compressor->compress($foto, 'sarpras/kerusakan', 'webp');
                        $laporan->foto()->create(['foto_path' => $path]);
                    }
                }

                return $laporan;
            });
        } catch (\Throwable $e) {
            return back()->withInput()
                ->with('gagal', 'Gagal menyimpan laporan / memproses foto: ' . $e->getMessage());
        }

        // Notifikasi in-app + FCM hanya ke Waka Sarpras.
        // Detail lengkap tetap dibuka lewat route terotorisasi, bukan dibocorkan ke payload push.
        $wakas = $this->wakaSarprasRecipients();
        Notification::send($wakas, new KerusakanDilaporkan($laporan));

        return redirect()->route('sarpras.kerusakan.show', $laporan->id)
            ->with('sukses', 'Laporan kerusakan terkirim ke Waka Sarpras.');
    }

    private function wakaSarprasRecipients()
    {
        return User::query()
            ->whereIn('access', self::WAKA_SARPRAS_ACCESS)
            ->get(['uuid', 'username', 'access']);
    }

    public function show(LaporanKerusakan $kerusakan): View
    {
        $user = auth()->user();
        if (! $user->can('sarpras.kerusakan.kelola') && $kerusakan->pelapor_id !== $user->getKey()) {
            abort(403);
        }

        $kerusakan->load(['pelapor:uuid,username', 'penangan:uuid,username', 'aset', 'ruangan', 'foto', 'perbaikan']);

        return view('sarpras.kerusakan.show', compact('kerusakan'));
    }

    public function foto(LaporanKerusakanFoto $foto): BinaryFileResponse
    {
        $foto->loadMissing('laporan');
        $laporan = $foto->laporan;
        $user = auth()->user();

        if (! $laporan || (! $user->can('sarpras.kerusakan.kelola') && $laporan->pelapor_id !== $user->getKey())) {
            abort(404);
        }

        if (! $foto->foto_path || ! Storage::disk('public')->exists($foto->foto_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($foto->foto_path), [
            'Content-Type' => Storage::disk('public')->mimeType($foto->foto_path) ?: 'image/webp',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /** Waka menerima laporan -> buat order perbaikan otomatis. */
    public function terima(Request $request, LaporanKerusakan $kerusakan): RedirectResponse
    {
        if ($kerusakan->status !== 'dilaporkan') {
            return back()->with('gagal', 'Laporan sudah diproses.');
        }

        DB::transaction(function () use ($request, $kerusakan) {
            $kerusakan->update([
                'status' => 'diterima',
                'ditangani_oleh' => $request->user()->getKey(),
                'ditangani_pada' => now(),
            ]);

            Perbaikan::create([
                'kode' => 'PRB-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
                'aset_id' => $kerusakan->aset_id,
                'laporan_id' => $kerusakan->id,
                'deskripsi' => 'Perbaikan dari laporan ' . $kerusakan->kode . ': ' . $kerusakan->deskripsi,
                'status' => 'antri',
                'biaya' => 0,
            ]);

            // Tandai aset perlu perbaikan (bila terkait aset).
            if ($kerusakan->aset) {
                $kerusakan->aset->update(['status' => 'perbaikan']);
            }
        });

        return back()->with('sukses', 'Laporan diterima & order perbaikan dibuat.');
    }

    /** Waka menolak laporan (wajib alasan). */
    public function tolak(Request $request, LaporanKerusakan $kerusakan): RedirectResponse
    {
        $request->validate(['alasan_tolak' => ['required', 'string', 'max:1000']]);

        if ($kerusakan->status !== 'dilaporkan') {
            return back()->with('gagal', 'Laporan sudah diproses.');
        }

        $kerusakan->update([
            'status' => 'ditolak',
            'alasan_tolak' => $request->alasan_tolak,
            'ditangani_oleh' => $request->user()->getKey(),
            'ditangani_pada' => now(),
        ]);

        return back()->with('sukses', 'Laporan ditolak.');
    }
}
