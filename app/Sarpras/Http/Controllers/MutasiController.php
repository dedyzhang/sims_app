<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\MutasiRequest;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\MutasiAset;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class MutasiController extends Controller
{
    public function index(): View
    {
        $mutasi = MutasiAset::with(['aset:id,kode,nama', 'ruanganAsal:id,kode', 'ruanganTujuan:id,kode'])
            ->latest()->get();

        return view('sarpras.mutasi.index', compact('mutasi'));
    }

    public function create(): View
    {
        return view('sarpras.mutasi.create', [
            'aset' => Aset::orderBy('nama')->get(['id', 'kode', 'nama', 'ruangan_id']),
            'ruangan' => DenahRuangan::orderBy('kode')->get(['id', 'kode', 'nama']),
        ]);
    }

    public function store(MutasiRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data) {
            $aset = Aset::findOrFail($data['aset_id']);
            // Asal default dari posisi aset saat ini bila tak diisi.
            $asal = $data['ruangan_asal_id'] ?? $aset->ruangan_id;

            MutasiAset::create([
                'aset_id' => $aset->id,
                'ruangan_asal_id' => $asal,
                'ruangan_tujuan_id' => $data['ruangan_tujuan_id'],
                'alasan' => $data['alasan'] ?? null,
                'tgl_mutasi' => $data['tgl_mutasi'],
                'dilakukan_oleh' => $request->user()->getKey(),
            ]);

            // Pindahkan aset ke ruangan tujuan (tercatat di activitylog).
            $aset->update(['ruangan_id' => $data['ruangan_tujuan_id'], 'status' => 'aktif']);
        });

        return redirect()->route('sarpras.mutasi.index')->with('sukses', 'Mutasi aset tercatat.');
    }

    /** Berita Acara mutasi (PDF). */
    public function beritaAcara(MutasiAset $mutasi)
    {
        $mutasi->load(['aset', 'ruanganAsal:id,kode,nama', 'ruanganTujuan:id,kode,nama', 'pelaksana:uuid,username']);
        $pdf = Pdf::loadView('sarpras.mutasi.berita', compact('mutasi'));

        return $pdf->stream('berita-acara-mutasi.pdf');
    }
}
