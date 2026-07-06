<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\JadwalRequest;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\JadwalPemeliharaan;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class JadwalController extends Controller
{
    public function index(): View
    {
        $jadwal = JadwalPemeliharaan::with('aset:id,kode,nama')
            ->orderBy('tgl_berikutnya')->get();

        return view('sarpras.jadwal.index', compact('jadwal'));
    }

    public function create(): View
    {
        return view('sarpras.jadwal.form', [
            'jadwal' => new JadwalPemeliharaan(['interval_hari' => 30, 'aktif' => true]),
            'aset' => Aset::orderBy('nama')->get(['id', 'kode', 'nama']),
        ]);
    }

    public function store(JadwalRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['aktif'] = $request->boolean('aktif');
        JadwalPemeliharaan::create($data);

        return redirect()->route('sarpras.jadwal.index')->with('sukses', 'Jadwal pemeliharaan dibuat.');
    }

    public function edit(JadwalPemeliharaan $jadwal): View
    {
        return view('sarpras.jadwal.form', [
            'jadwal' => $jadwal,
            'aset' => Aset::orderBy('nama')->get(['id', 'kode', 'nama']),
        ]);
    }

    public function update(JadwalRequest $request, JadwalPemeliharaan $jadwal): RedirectResponse
    {
        $data = $request->validated();
        $data['aktif'] = $request->boolean('aktif');
        $jadwal->update($data);

        return redirect()->route('sarpras.jadwal.index')->with('sukses', 'Jadwal diperbarui.');
    }

    public function destroy(JadwalPemeliharaan $jadwal): RedirectResponse
    {
        $jadwal->delete();

        return redirect()->route('sarpras.jadwal.index')->with('sukses', 'Jadwal dihapus.');
    }
}
