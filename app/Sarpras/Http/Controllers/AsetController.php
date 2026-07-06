<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Exports\AsetTemplateExport;
use App\Sarpras\Http\Requests\AsetRequest;
use App\Sarpras\Imports\AsetImport;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\KategoriAset;
use App\Sarpras\Services\FotoCompressor;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class AsetController extends Controller
{
    public function index(Request $request): View
    {
        // Filter + search; eager load kategori & ruangan -> cegah N+1.
        $aset = Aset::with(['kategori:id,nama', 'ruangan:id,kode,nama'])
            ->when($request->q, fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('nama', 'like', "%{$s}%")->orWhere('kode', 'like', "%{$s}%")))
            ->when($request->kategori_id, fn ($q, $v) => $q->where('kategori_id', $v))
            ->when($request->kondisi, fn ($q, $v) => $q->where('kondisi', $v))
            ->latest()->get();

        // ── Ringkasan neraca aset (seluruh aset, bukan hanya yang terfilter) ──
        $kondisiUrut = ['baik', 'rusak_ringan', 'rusak_berat', 'hilang'];
        $kondisiCount = Aset::selectRaw('kondisi, COUNT(*) as jml')
            ->groupBy('kondisi')->pluck('jml', 'kondisi');

        $perKategori = Aset::with('kategori:id,nama')
            ->selectRaw('kategori_id, COUNT(*) as jml, SUM(nilai_perolehan) as nilai')
            ->groupBy('kategori_id')->orderByDesc('nilai')->get();

        // Nilai buku dihitung per aset di PHP (akurat) — ambil kolom seperlunya.
        $totalNilaiBuku = Aset::get(['nilai_perolehan', 'masa_manfaat_tahun', 'tgl_perolehan'])
            ->sum(fn ($a) => $a->nilaiBuku());

        return view('sarpras.aset.index', [
            'aset' => $aset,
            'kategori' => KategoriAset::orderBy('nama')->get(['id', 'nama']),
            'totalAset' => Aset::count(),
            'totalNilai' => (int) Aset::sum('nilai_perolehan'),
            'totalNilaiBuku' => (int) $totalNilaiBuku,
            'kondisiCount' => $kondisiCount,
            'kondisiUrut' => $kondisiUrut,
            'perKategori' => $perKategori,
        ]);
    }

    public function create(): View
    {
        return view('sarpras.aset.form', [
            'aset' => new Aset(['kondisi' => 'baik', 'status' => 'aktif', 'nilai_perolehan' => 0]),
            'kategori' => KategoriAset::orderBy('nama')->get(['id', 'nama']),
            'ruangan' => DenahRuangan::orderBy('kode')->get(['id', 'kode', 'nama']),
        ]);
    }

    public function store(AsetRequest $request, FotoCompressor $compressor): RedirectResponse
    {
        $data = $request->safe()->except(['spek_key', 'spek_val', 'foto']);
        $data['spesifikasi'] = $request->spesifikasi();

        try {
            if ($request->hasFile('foto')) {
                $data['foto_path'] = $compressor->compress($request->file('foto'), 'sarpras/aset', 'webp');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('gagal', 'Gagal memproses foto aset: ' . $e->getMessage());
        }

        $aset = Aset::create($data);

        return redirect()->route('sarpras.aset.show', $aset)->with('sukses', 'Aset ditambahkan.');
    }

    /** Unduh template Excel kosong (header + contoh) untuk import aset. */
    public function templateImport()
    {
        return Excel::download(new AsetTemplateExport(), 'template-import-aset.xlsx');
    }

    /** Import katalog aset dari berkas Excel/CSV. UPSERT berdasarkan kode. */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ], [
            'file.required' => 'Pilih berkas Excel/CSV terlebih dahulu.',
            'file.mimes' => 'Format didukung: xlsx, xls, csv.',
            'file.max' => 'Ukuran berkas maksimal 5MB.',
        ]);

        $import = new AsetImport();

        try {
            // Bungkus transaksi: bila ada baris yang gagal di tengah jalan,
            // seluruh import dibatalkan (tidak ada data setengah masuk).
            DB::transaction(fn () => Excel::import($import, $request->file('file')));
        } catch (\Throwable $e) {
            return back()->with('gagal', 'Gagal memproses berkas (tidak ada data tersimpan): ' . $e->getMessage());
        }

        $msg = "Import selesai — {$import->dibuat} aset baru, {$import->diperbarui} diperbarui";
        $msg .= $import->jumlahDilewati() ? ", {$import->jumlahDilewati()} catatan." : '.';

        return redirect()->route('sarpras.aset.index')
            ->with('sukses', $msg)
            ->with('import_catatan', $import->dilewati);
    }

    public function show(Aset $aset): View
    {
        $aset->load(['kategori:id,nama', 'ruangan:id,kode,nama']);

        // Riwayat perubahan (audit log) tidak diaktifkan di SIMS.
        $riwayat = collect();

        return view('sarpras.aset.show', compact('aset', 'riwayat'));
    }

    public function edit(Aset $aset): View
    {
        return view('sarpras.aset.form', [
            'aset' => $aset,
            'kategori' => KategoriAset::orderBy('nama')->get(['id', 'nama']),
            'ruangan' => DenahRuangan::orderBy('kode')->get(['id', 'kode', 'nama']),
        ]);
    }

    public function update(AsetRequest $request, Aset $aset, FotoCompressor $compressor): RedirectResponse
    {
        $data = $request->safe()->except(['spek_key', 'spek_val', 'foto']);
        $data['spesifikasi'] = $request->spesifikasi();

        try {
            if ($request->hasFile('foto')) {
                $compressor->hapus($aset->foto_path);
                $data['foto_path'] = $compressor->compress($request->file('foto'), 'sarpras/aset', 'webp');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('gagal', 'Gagal memproses foto aset: ' . $e->getMessage());
        }

        $aset->update($data);

        return redirect()->route('sarpras.aset.show', $aset)->with('sukses', 'Aset diperbarui.');
    }

    public function destroy(Aset $aset, FotoCompressor $compressor): RedirectResponse
    {
        $compressor->hapus($aset->foto_path);
        $aset->delete();

        return redirect()->route('sarpras.aset.index')->with('sukses', 'Aset dihapus.');
    }

    /** QR berisi URL detail aset. SVG (tanpa Imagick). */
    public function qr(Aset $aset): Response
    {
        $url = route('sarpras.aset.show', $aset);
        $svg = QrCode::format('svg')->size(220)->margin(1)->generate($url);

        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    /** Cetak label aset (PDF): QR + kode + nama. */
    public function label(Aset $aset)
    {
        $url = route('sarpras.aset.show', $aset);
        // QR SVG di-embed ke PDF (dompdf mendukung SVG dasar).
        $qrSvg = QrCode::format('svg')->size(150)->margin(0)->generate($url);

        $pdf = Pdf::loadView('sarpras.aset.label', compact('aset', 'qrSvg'))
            ->setPaper([0, 0, 226.77, 141.73]); // ~80x50mm label

        return $pdf->stream('label-' . $aset->kode . '.pdf');
    }
}
