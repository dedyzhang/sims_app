<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Exports\KategoriTemplateExport;
use App\Sarpras\Http\Requests\KategoriRequest;
use App\Sarpras\Imports\KategoriImport;
use App\Sarpras\Models\KategoriAset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class KategoriController extends Controller
{
    public function index(): View
    {
        $kategori = KategoriAset::with('parent:id,nama')->withCount('aset')
            ->orderBy('nama')->get();

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

    /** Unduh template Excel kosong (header + contoh) untuk import kategori. */
    public function templateImport()
    {
        return Excel::download(new KategoriTemplateExport(), 'template-import-kategori.xlsx');
    }

    /** Import kategori aset dari berkas Excel/CSV. UPSERT berdasarkan kode/nama. */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Pilih berkas Excel/CSV terlebih dahulu.',
            'file.mimes' => 'Format didukung: xlsx, xls, csv.',
            'file.max' => 'Ukuran berkas maksimal 5MB.',
        ]);

        $import = new KategoriImport();

        try {
            // Bungkus transaksi: bila ada baris yang gagal di tengah jalan,
            // seluruh import dibatalkan (tidak ada data setengah masuk).
            DB::transaction(fn () => Excel::import($import, $request->file('file')));
        } catch (\Throwable $e) {
            return back()->with('gagal', 'Gagal memproses berkas (tidak ada data tersimpan): ' . $e->getMessage());
        }

        $msg = "Import selesai — {$import->dibuat} kategori baru, {$import->diperbarui} diperbarui";
        $msg .= $import->jumlahDilewati() ? ", {$import->jumlahDilewati()} catatan." : '.';

        return redirect()->route('sarpras.kategori.index')
            ->with('sukses', $msg)
            ->with('import_catatan', $import->dilewati);
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
