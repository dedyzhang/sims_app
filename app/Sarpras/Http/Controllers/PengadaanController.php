<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\PengadaanRequest;
use App\Sarpras\Models\KategoriAset;
use App\Sarpras\Models\Pengadaan;
use App\Sarpras\Models\Supplier;
use App\Sarpras\Services\FotoCompressor;
use App\Sarpras\Support\Rupiah;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PengadaanController extends Controller
{
    public function index(Request $request): View
    {
        $statusCounts = Pengadaan::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $pengadaan = Pengadaan::with('pengaju:uuid,username')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->get();

        return view('sarpras.pengadaan.index', compact('pengadaan', 'statusCounts'));
    }

    public function create(): View
    {
        return view('sarpras.pengadaan.create', [
            'kategori' => KategoriAset::orderBy('nama')->get(['id', 'nama']),
            'supplier' => Supplier::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(PengadaanRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $pengadaan = DB::transaction(function () use ($request, $data) {
            // Total estimasi = sum(harga*qty) via BCMath.
            $total = Rupiah::sumItems(array_map(fn ($i) => [
                'harga' => $i['estimasi_harga'], 'qty' => $i['qty'],
            ], $data['item']));

            $pengadaan = Pengadaan::create([
                'kode' => 'PGD-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4)),
                'judul' => $data['judul'],
                'deskripsi' => $data['deskripsi'] ?? null,
                'diajukan_oleh' => $request->user()->getKey(),
                'status' => 'diajukan',
                'total_estimasi' => $total,
            ]);

            foreach ($data['item'] as $item) {
                $pengadaan->items()->create([
                    'kategori_id' => $item['kategori_id'] ?? null,
                    'supplier_id' => $item['supplier_id'] ?? null,
                    'nama_barang' => $item['nama_barang'],
                    'qty' => $item['qty'],
                    'satuan' => $item['satuan'] ?? 'unit',
                    'estimasi_harga' => $item['estimasi_harga'],
                ]);
            }

            return $pengadaan;
        });

        return redirect()->route('sarpras.pengadaan.show', $pengadaan)
            ->with('sukses', 'Pengajuan pengadaan terkirim.');
    }

    public function show(Pengadaan $pengadaan): View
    {
        $pengadaan->load(['pengaju:uuid,username', 'penyetuju:uuid,username',
            'items.kategori:id,nama', 'items.supplier:id,nama', 'dokumen']);

        return view('sarpras.pengadaan.show', compact('pengadaan'));
    }

    public function setujui(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        if ($pengadaan->status !== 'diajukan') {
            return back()->with('gagal', 'Pengajuan sudah diproses.');
        }
        $pengadaan->update([
            'status' => 'disetujui',
            'disetujui_oleh' => $request->user()->getKey(),
            'disetujui_pada' => now(),
        ]);

        return back()->with('sukses', 'Pengadaan disetujui.');
    }

    public function tolak(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $request->validate(['alasan_tolak' => ['required', 'string', 'max:1000']]);
        if ($pengadaan->status !== 'diajukan') {
            return back()->with('gagal', 'Pengajuan sudah diproses.');
        }
        $pengadaan->update([
            'status' => 'ditolak',
            'alasan_tolak' => $request->alasan_tolak,
            'disetujui_oleh' => $request->user()->getKey(),
            'disetujui_pada' => now(),
        ]);

        return back()->with('sukses', 'Pengadaan ditolak.');
    }

    /** Pencatatan penerimaan barang per item. */
    public function terima(Request $request, Pengadaan $pengadaan): RedirectResponse
    {
        $request->validate([
            'qty_diterima' => ['required', 'array'],
            'qty_diterima.*' => ['nullable', 'integer', 'min:0'],
            'kondisi_terima' => ['nullable', 'array'],
            'tgl_terima' => ['nullable', 'date'],
        ]);

        DB::transaction(function () use ($request, $pengadaan) {
            foreach ($pengadaan->items as $item) {
                $qty = (int) ($request->input("qty_diterima.{$item->id}") ?? 0);
                $item->update([
                    'qty_diterima' => $qty,
                    'kondisi_terima' => $request->input("kondisi_terima.{$item->id}"),
                    'tgl_terima' => $request->tgl_terima,
                ]);
            }
            $pengadaan->update(['status' => 'selesai']);
        });

        return back()->with('sukses', 'Penerimaan barang dicatat.');
    }

    /** Upload nota / bukti (dikompres <=2MB). */
    public function uploadDokumen(Request $request, Pengadaan $pengadaan, FotoCompressor $compressor): RedirectResponse
    {
        $request->validate([
            'nama' => ['required', 'string', 'max:150'],
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        try {
            $path = $compressor->compress($request->file('file'), 'sarpras/pengadaan', 'webp');
        } catch (\Throwable $e) {
            return back()->with('gagal', 'Gagal memproses dokumen: ' . $e->getMessage());
        }

        $pengadaan->dokumen()->create(['nama' => $request->nama, 'file_path' => $path]);

        return back()->with('sukses', 'Dokumen ditambahkan.');
    }
}
