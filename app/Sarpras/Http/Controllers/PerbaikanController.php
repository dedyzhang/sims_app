<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\PerbaikanRequest;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\LaporanKerusakan;
use App\Sarpras\Models\Perbaikan;
use App\Sarpras\Models\Teknisi;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PerbaikanController extends Controller
{
    public function index(Request $request): View
    {
        $perbaikan = Perbaikan::with(['aset:id,kode,nama', 'teknisi:id,nama'])
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(15)->withQueryString();

        return view('sarpras.perbaikan.index', compact('perbaikan'));
    }

    public function create(Request $request): View
    {
        return view('sarpras.perbaikan.form', [
            'perbaikan' => new Perbaikan(['status' => 'antri', 'biaya' => 0]),
            'aset' => Aset::orderBy('nama')->get(['id', 'kode', 'nama']),
            'teknisi' => Teknisi::orderBy('nama')->get(['id', 'nama']),
            'laporan' => LaporanKerusakan::whereIn('status', ['diterima'])->get(['id', 'kode']),
        ]);
    }

    public function store(PerbaikanRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['kode'] = 'PRB-' . now()->format('Ymd') . '-' . Str::upper(Str::random(4));
        Perbaikan::create($data);

        return redirect()->route('sarpras.perbaikan.index')->with('sukses', 'Order perbaikan dibuat.');
    }

    public function show(Perbaikan $perbaikan): View
    {
        $perbaikan->load(['aset:id,kode,nama', 'teknisi:id,nama', 'laporan:id,kode']);

        return view('sarpras.perbaikan.show', compact('perbaikan'));
    }

    public function update(PerbaikanRequest $request, Perbaikan $perbaikan): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $perbaikan) {
            $perbaikan->update($data);

            // Bila selesai & terkait aset -> kembalikan status aset & kondisi baik.
            if ($data['status'] === 'selesai' && $perbaikan->aset) {
                $perbaikan->aset->update(['status' => 'aktif', 'kondisi' => 'baik']);
            }
        });

        return redirect()->route('sarpras.perbaikan.show', $perbaikan)->with('sukses', 'Perbaikan diperbarui.');
    }
}
