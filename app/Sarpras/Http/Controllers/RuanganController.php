<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\RuanganPosisiRequest;
use App\Sarpras\Http\Requests\RuanganRequest;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Services\FotoCompressor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RuanganController extends Controller
{
    /** Halaman detail ruangan: denah ruangan + foto + daftar aset + tombol lapor. */
    public function show(DenahRuangan $ruangan): View
    {
        // Eager load aset + kategori -> cegah N+1.
        $ruangan->load(['denah:id,nama', 'aset.kategori:id,nama']);

        return view('sarpras.denah.ruangan', compact('ruangan'));
    }

    public function store(RuanganRequest $request, Denah $denah, FotoCompressor $compressor): RedirectResponse
    {
        $data = $request->safe()->only(['kode', 'nama', 'pos_x', 'pos_y', 'lebar', 'tinggi', 'kapasitas', 'deskripsi']);
        $data['denah_id'] = $denah->id;

        try {
            if ($request->hasFile('gambar_denah')) {
                $data['gambar_denah_path'] = $compressor->compress($request->file('gambar_denah'), 'sarpras/ruangan', 'webp');
            }
            if ($request->hasFile('foto')) {
                $data['foto_path'] = $compressor->compress($request->file('foto'), 'sarpras/ruangan', 'webp');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('gagal', 'Gagal memproses foto ruangan: ' . $e->getMessage());
        }

        $denah->ruangan()->create($data);

        return back()->with('sukses', 'Ruangan "' . $data['kode'] . '" ditambahkan.');
    }

    public function update(RuanganRequest $request, DenahRuangan $ruangan, FotoCompressor $compressor): RedirectResponse
    {
        $data = $request->safe()->only(['kode', 'nama', 'pos_x', 'pos_y', 'lebar', 'tinggi', 'kapasitas', 'deskripsi']);

        try {
            if ($request->hasFile('gambar_denah')) {
                $compressor->hapus($ruangan->gambar_denah_path);
                $data['gambar_denah_path'] = $compressor->compress($request->file('gambar_denah'), 'sarpras/ruangan', 'webp');
            }
            if ($request->hasFile('foto')) {
                $compressor->hapus($ruangan->foto_path);
                $data['foto_path'] = $compressor->compress($request->file('foto'), 'sarpras/ruangan', 'webp');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('gagal', 'Gagal memproses foto ruangan: ' . $e->getMessage());
        }

        $ruangan->update($data);

        return back()->with('sukses', 'Ruangan diperbarui.');
    }

    /**
     * Simpan koordinat hotspot dari editor (AJAX).
     * Koordinat sudah PERSEN (0-100) — dihitung di sisi klien relatif ukuran gambar.
     */
    public function simpanPosisi(RuanganPosisiRequest $request, DenahRuangan $ruangan): JsonResponse
    {
        // Hanya perbarui field yang dikirim (posisi selalu; ukuran saat resize).
        $data = array_filter(
            $request->validated(),
            fn ($v) => $v !== null
        );
        $ruangan->update($data);

        return response()->json([
            'ok' => true,
            'pos_x' => (float) $ruangan->pos_x,
            'pos_y' => (float) $ruangan->pos_y,
            'lebar' => (float) $ruangan->lebar,
            'tinggi' => (float) $ruangan->tinggi,
        ]);
    }

    public function destroy(DenahRuangan $ruangan, FotoCompressor $compressor): RedirectResponse
    {
        $compressor->hapus($ruangan->gambar_denah_path);
        $compressor->hapus($ruangan->foto_path);
        $ruangan->delete();

        return back()->with('sukses', 'Ruangan dihapus.');
    }
}
