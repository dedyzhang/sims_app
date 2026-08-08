<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\StokOpname;
use App\Sarpras\Models\StokOpnameItem;
use App\Sarpras\Services\SarprasActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class StokOpnameController extends Controller
{
    public function index(): View
    {
        $opname = StokOpname::with('pembuat:uuid,username')->latest()->get();

        return view('sarpras.stok-opname.index', compact('opname'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'periode' => ['required', 'string', 'max:32'],
            'judul' => ['required', 'string', 'max:255'],
        ]);

        $opname = DB::transaction(function () use ($request, $data) {
            $opname = StokOpname::create([
                'periode' => $data['periode'],
                'judul' => $data['judul'],
                'status' => 'draft',
                'dibuat_oleh' => $request->user()->getKey(),
            ]);

            $now = now();
            $schoolId = StokOpname::SCHOOL_ID;
            $rows = Aset::query()
                ->get(['id', 'kondisi'])
                ->map(fn (Aset $aset) => [
                    'id' => (string) Str::uuid(),
                    'school_id' => $schoolId,
                    'opname_id' => $opname->id,
                    'aset_id' => $aset->id,
                    'kondisi_sistem' => $aset->kondisi,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                StokOpnameItem::insert($chunk);
            }

            return $opname;
        });

        SarprasActivityLogger::log('stok_opname.dibuat', $opname);

        return redirect()->route('sarpras.stok-opname.show', $opname)
            ->with('sukses', 'Sesi stok opname dibuat.');
    }

    public function show(StokOpname $opname): View
    {
        $opname->load(['items.aset:id,kode,nama,kondisi,ruangan_id', 'items.aset.ruangan:id,kode']);

        return view('sarpras.stok-opname.show', compact('opname'));
    }

    public function simpanItem(Request $request, StokOpname $opname): RedirectResponse
    {
        $data = $request->validate([
            'item_id' => ['required', 'uuid', 'exists:sarpras_stok_opname_item,id'],
            'kondisi_fisik' => ['required', 'in:baik,rusak_ringan,rusak_berat,hilang'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $item = StokOpnameItem::where('opname_id', $opname->id)->findOrFail($data['item_id']);
        $item->update([
            'kondisi_fisik' => $data['kondisi_fisik'],
            'catatan' => $data['catatan'] ?? null,
        ]);

        return back()->with('sukses', 'Kondisi fisik dicatat.');
    }

    public function selesai(StokOpname $opname): RedirectResponse
    {
        DB::transaction(function () use ($opname) {
            $opname->update(['status' => 'selesai', 'selesai_pada' => now()]);
        });

        SarprasActivityLogger::log('stok_opname.selesai', $opname);

        return redirect()->route('sarpras.stok-opname.index')
            ->with('sukses', 'Stok opname ditandai selesai.');
    }
}
