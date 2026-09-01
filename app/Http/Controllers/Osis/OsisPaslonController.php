<?php

namespace App\Http\Controllers\Osis;

use App\Http\Controllers\Controller;
use App\Models\OsisPaslon;
use App\Models\OsisPemilihan;
use App\Sarpras\Services\FotoCompressor;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OsisPaslonController extends Controller
{
    public function __construct(private FotoCompressor $fotoCompressor)
    {
    }

    public function store(Request $request, OsisPemilihan $pemilihan)
    {
        $data = $this->validated($request, $pemilihan);

        if ($request->hasFile('foto')) {
            $data['foto'] = $this->fotoCompressor->compress($request->file('foto'), 'osis-paslon');
        }

        OsisPaslon::create($data + ['id_pemilihan' => $pemilihan->uuid]);

        return back()->with('success', 'Paslon ditambahkan.');
    }

    public function update(Request $request, OsisPemilihan $pemilihan, OsisPaslon $paslon)
    {
        abort_unless($paslon->id_pemilihan === $pemilihan->uuid, 404);
        $data = $this->validated($request, $pemilihan, $paslon);

        if ($request->hasFile('foto')) {
            $this->fotoCompressor->hapus($paslon->foto);
            $data['foto'] = $this->fotoCompressor->compress($request->file('foto'), 'osis-paslon');
        }

        $paslon->update($data);

        return back()->with('success', 'Data paslon diperbarui.');
    }

    public function destroy(OsisPemilihan $pemilihan, OsisPaslon $paslon)
    {
        abort_unless($paslon->id_pemilihan === $pemilihan->uuid, 404);
        $this->fotoCompressor->hapus($paslon->foto);
        $paslon->delete();

        return back()->with('success', 'Paslon dihapus.');
    }

    private function validated(Request $request, OsisPemilihan $pemilihan, ?OsisPaslon $current = null): array
    {
        $data = $request->validate([
            'nomor_urut' => [
                'required', 'integer', 'min:1', 'max:99',
                Rule::unique('osis_paslon', 'nomor_urut')
                    ->where('id_pemilihan', $pemilihan->uuid)
                    ->ignore($current?->uuid, 'uuid'),
            ],
            'nama_ketua' => 'required|string|max:100',
            'nama_wakil' => 'nullable|string|max:100',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:8192',
            // Textarea polos (bukan HTML) — Blade {{ }} auto-escape sudah cukup aman,
            // tak perlu RichText::clean() (lihat keputusan desain di plan).
            'visi' => 'nullable|string|max:2000',
            'misi' => 'nullable|string|max:4000',
            'urutan_tampil' => 'nullable|integer|min:0|max:999',
        ]);

        // Field ini 'nullable' — kalau dikosongkan di form, validate() tetap sertakan key-nya
        // dgn nilai null (bukan menghilangkannya), dan Eloquent create()/update() akan INSERT
        // NULL eksplisit itu (menimpa default DB, kolomnya NOT NULL) → crash. Default akal sehat:
        // kosong = tampil sesuai nomor urut.
        $data['urutan_tampil'] ??= $data['nomor_urut'];

        return $data;
    }
}
