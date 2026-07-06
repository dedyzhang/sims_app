<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\PenghapusanRequest;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\Penghapusan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PenghapusanController extends Controller
{
    public function index(Request $request): View
    {
        $penghapusan = Penghapusan::with(['aset:id,kode,nama', 'pengaju:uuid,username'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->get();

        return view('sarpras.penghapusan.index', compact('penghapusan'));
    }

    public function create(): View
    {
        $aset = Aset::whereNotIn('status', ['dihapus'])->orderBy('nama')->get(['id', 'kode', 'nama']);

        return view('sarpras.penghapusan.create', compact('aset'));
    }

    public function store(PenghapusanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['kode'] = 'HPS-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4));
        $data['diajukan_oleh'] = $request->user()->getKey();
        $data['status'] = 'diajukan';

        $penghapusan = Penghapusan::create($data);

        return redirect()->route('sarpras.penghapusan.show', $penghapusan)
            ->with('sukses', 'Pengajuan penghapusan terkirim.');
    }

    public function show(Penghapusan $penghapusan): View
    {
        $penghapusan->load(['aset:id,kode,nama', 'pengaju:uuid,username', 'penyetuju:uuid,username']);

        return view('sarpras.penghapusan.show', compact('penghapusan'));
    }

    public function setujui(Request $request, Penghapusan $penghapusan): RedirectResponse
    {
        if ($penghapusan->status !== 'diajukan') {
            return back()->with('gagal', 'Pengajuan sudah diproses.');
        }

        DB::transaction(function () use ($request, $penghapusan) {
            $penghapusan->update([
                'status' => 'disetujui',
                'disetujui_oleh' => $request->user()->getKey(),
                'disetujui_pada' => now(),
            ]);
            // Update status aset -> dihapus (tercatat di activitylog).
            $penghapusan->aset?->update(['status' => 'dihapus']);
        });

        return back()->with('sukses', 'Penghapusan disetujui. Status aset diperbarui.');
    }

    public function tolak(Request $request, Penghapusan $penghapusan): RedirectResponse
    {
        $request->validate(['alasan_tolak' => ['required', 'string', 'max:1000']]);
        if ($penghapusan->status !== 'diajukan') {
            return back()->with('gagal', 'Pengajuan sudah diproses.');
        }
        $penghapusan->update([
            'status' => 'ditolak',
            'alasan_tolak' => $request->alasan_tolak,
            'disetujui_oleh' => $request->user()->getKey(),
            'disetujui_pada' => now(),
        ]);

        return back()->with('sukses', 'Penghapusan ditolak.');
    }

    /** Berita Acara penghapusan (PDF). */
    public function beritaAcara(Penghapusan $penghapusan)
    {
        $penghapusan->load(['aset', 'pengaju:uuid,username', 'penyetuju:uuid,username']);
        $pdf = Pdf::loadView('sarpras.penghapusan.berita', compact('penghapusan'));

        return $pdf->stream('berita-acara-' . $penghapusan->kode . '.pdf');
    }
}
