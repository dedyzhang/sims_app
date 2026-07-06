<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\TeknisiRequest;
use App\Sarpras\Models\Teknisi;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TeknisiController extends Controller
{
    public function index(): View
    {
        $teknisi = Teknisi::orderBy('nama')->get();

        return view('sarpras.teknisi.index', compact('teknisi'));
    }

    public function create(): View
    {
        return view('sarpras.teknisi.form', ['teknisi' => new Teknisi(['tipe' => 'internal'])]);
    }

    public function store(TeknisiRequest $request): RedirectResponse
    {
        Teknisi::create($request->validated());

        return redirect()->route('sarpras.teknisi.index')->with('sukses', 'Teknisi ditambahkan.');
    }

    public function edit(Teknisi $teknisi): View
    {
        return view('sarpras.teknisi.form', compact('teknisi'));
    }

    public function update(TeknisiRequest $request, Teknisi $teknisi): RedirectResponse
    {
        $teknisi->update($request->validated());

        return redirect()->route('sarpras.teknisi.index')->with('sukses', 'Teknisi diperbarui.');
    }

    public function destroy(Teknisi $teknisi): RedirectResponse
    {
        $teknisi->delete();

        return redirect()->route('sarpras.teknisi.index')->with('sukses', 'Teknisi dihapus.');
    }
}
