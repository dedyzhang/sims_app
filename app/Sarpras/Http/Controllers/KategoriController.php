<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\KategoriRequest;
use App\Sarpras\Models\KategoriAset;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KategoriController extends Controller
{
    public function index(): View
    {
        $kategori = KategoriAset::with('parent:id,nama')->withCount('aset')
            ->orderBy('nama')->paginate(20);

        return view('sarpras.kategori.index', compact('kategori'));
    }

    public function create(): View
    {
        return view('sarpras.kategori.form', [
            'kategori' => new KategoriAset(),
            'semua' => KategoriAset::orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function store(KategoriRequest $request): RedirectResponse
    {
        KategoriAset::create($request->validated());

        return redirect()->route('sarpras.kategori.index')->with('sukses', 'Kategori ditambahkan.');
    }

    public function edit(KategoriAset $kategori): View
    {
        return view('sarpras.kategori.form', [
            'kategori' => $kategori,
            'semua' => KategoriAset::where('id', '!=', $kategori->id)->orderBy('nama')->get(['id', 'nama']),
        ]);
    }

    public function update(KategoriRequest $request, KategoriAset $kategori): RedirectResponse
    {
        $kategori->update($request->validated());

        return redirect()->route('sarpras.kategori.index')->with('sukses', 'Kategori diperbarui.');
    }

    public function destroy(KategoriAset $kategori): RedirectResponse
    {
        $kategori->delete();

        return redirect()->route('sarpras.kategori.index')->with('sukses', 'Kategori dihapus.');
    }
}
