<?php

namespace App\Sarpras\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Sarpras\Http\Requests\DenahRequest;
use App\Sarpras\Models\Aset;
use App\Sarpras\Models\Denah;
use App\Sarpras\Models\DenahRuangan;
use App\Sarpras\Models\LaporanKerusakan;
use App\Sarpras\Models\Peminjaman;
use App\Sarpras\Services\FotoCompressor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DenahController extends Controller
{
    public function index(): View
    {
        // Dikelompokkan per gedung; tiap gedung memuat beberapa lantai/denah.
        $denah = Denah::withCount('ruangan')
            ->orderBy('gedung')
            ->orderBy('lantai')
            ->orderBy('nama')
            ->get();

        $gedungGroups = $denah->groupBy(fn ($d) => $d->gedung ?: 'Tanpa Gedung');
        $ruanganPerStatus = DenahRuangan::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $denahStats = [
            'gedung' => $gedungGroups->count(),
            'lantai' => $denah->count(),
            'ruangan' => DenahRuangan::count(),
            'tanpa_gambar' => $denah->whereNull('gambar_path')->count(),
            'tanpa_ruangan' => $denah->where('ruangan_count', 0)->count(),
            'tersedia' => (int) ($ruanganPerStatus['tersedia'] ?? 0),
            'digunakan' => (int) ($ruanganPerStatus['digunakan'] ?? 0),
            'maintenance' => (int) ($ruanganPerStatus['maintenance'] ?? 0),
        ];

        return view('sarpras.denah.index', compact('gedungGroups', 'denahStats'));
    }

    /** Halaman denah interaktif: gambar + hotspot ruangan (koordinat persen). */
    public function show(Denah $denah): View
    {
        // Eager load ruangan -> cegah N+1 saat render hotspot.
        $denah->load('ruangan');

        // Lantai lain pada gedung yang sama (untuk pemilih lantai).
        $lantaiSegedung = $denah->gedung
            ? Denah::where('gedung', $denah->gedung)->orderBy('lantai')->orderBy('nama')->get(['id', 'nama', 'lantai'])
            : collect([$denah]);

        // Agregat status per ruangan untuk pewarnaan interaktif (mode "Status").
        $ruanganIds = $denah->ruangan->pluck('id');

        $jmlAset = Aset::whereIn('ruangan_id', $ruanganIds)
            ->selectRaw('ruangan_id, count(*) as c')->groupBy('ruangan_id')->pluck('c', 'ruangan_id');

        $jmlKerusakan = LaporanKerusakan::whereIn('ruangan_id', $ruanganIds)
            ->whereIn('status', ['dilaporkan', 'diterima'])
            ->selectRaw('ruangan_id, count(*) as c')->groupBy('ruangan_id')->pluck('c', 'ruangan_id');

        // Ruangan yang sedang dipinjam/dibooking pada saat ini.
        $sedangDipinjam = Peminjaman::whereIn('ruangan_id', $ruanganIds)
            ->whereIn('status', ['disetujui', 'dipinjam', 'terlambat'])
            ->where('mulai', '<=', now())->where('selesai', '>=', now())
            ->pluck('ruangan_id')->unique()->flip();

        return view('sarpras.denah.show', compact(
            'denah', 'lantaiSegedung', 'jmlAset', 'jmlKerusakan', 'sedangDipinjam'
        ));
    }

    public function create(Request $request): View
    {
        // Prefill gedung saat menambah lantai pada gedung yang sudah ada.
        $denah = new Denah(['gedung' => $request->query('gedung')]);

        return view('sarpras.denah.form', compact('denah'));
    }

    public function store(DenahRequest $request, FotoCompressor $compressor): RedirectResponse
    {
        $data = $request->safe()->only(['nama', 'gedung', 'lantai', 'deskripsi']);
        $data['nama'] = $this->namaDenah($data);

        try {
            // Kompres gambar denah (<=2MB) bila ada.
            if ($request->hasFile('gambar')) {
                $data['gambar_path'] = $compressor->compress($request->file('gambar'), 'sarpras/denah', 'webp');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('gagal', 'Gagal memproses gambar denah: ' . $e->getMessage());
        }

        $denah = Denah::create($data);

        // Bila belum mengunggah gambar, arahkan ke editor sketsa (gambar dulu).
        $tujuan = empty($data['gambar_path']) ? 'sarpras.denah.gambar' : 'sarpras.denah.hotspot';

        return redirect()->route($tujuan, $denah)
            ->with('sukses', 'Denah dibuat. ' . ($tujuan === 'sarpras.denah.gambar'
                ? 'Gambar denahnya, lalu atur blok ruangan.'
                : 'Sekarang atur blok ruangan.'));
    }

    public function edit(Denah $denah): View
    {
        return view('sarpras.denah.form', compact('denah'));
    }

    public function update(DenahRequest $request, Denah $denah, FotoCompressor $compressor): RedirectResponse
    {
        $data = $request->safe()->only(['nama', 'gedung', 'lantai', 'deskripsi']);
        $data['nama'] = $this->namaDenah($data);

        try {
            if ($request->hasFile('gambar')) {
                // Hapus gambar lama lalu simpan hasil kompresi baru.
                $compressor->hapus($denah->gambar_path);
                $data['gambar_path'] = $compressor->compress($request->file('gambar'), 'sarpras/denah', 'webp');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->with('gagal', 'Gagal memproses gambar denah: ' . $e->getMessage());
        }

        $denah->update($data);

        return redirect()->route('sarpras.denah.show', $denah)->with('sukses', 'Denah diperbarui.');
    }

    public function destroy(Denah $denah, FotoCompressor $compressor): RedirectResponse
    {
        $compressor->hapus($denah->gambar_path);
        $denah->delete();

        return redirect()->route('sarpras.denah.index')->with('sukses', 'Denah dihapus.');
    }

    /** Editor penempatan hotspot: klik pada denah -> hitung koordinat persen. */
    public function editorHotspot(Denah $denah): View
    {
        $denah->load('ruangan');

        return view('sarpras.denah.hotspot', compact('denah'));
    }

    /** Editor sketsa: menggambar denah langsung di aplikasi (kanvas). */
    public function editorGambar(Denah $denah): View
    {
        return view('sarpras.denah.gambar', compact('denah'));
    }

    /** Import gambar denah dari file (jpg/jpeg/png/webp/gif/bmp). */
    public function imporGambar(Request $request, Denah $denah, FotoCompressor $compressor): RedirectResponse
    {
        $request->validate([
            'gambar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif,bmp', 'max:10240'],
        ], [
            'gambar.required' => 'Pilih berkas gambar denah.',
            'gambar.image' => 'Berkas harus berupa gambar.',
            'gambar.mimes' => 'Format didukung: jpg, jpeg, png, webp, gif, bmp.',
            'gambar.max' => 'Ukuran gambar maksimal 10MB.',
        ]);

        try {
            $compressor->hapus($denah->gambar_path);
            $denah->update([
                'gambar_path' => $compressor->compress($request->file('gambar'), 'sarpras/denah', 'webp'),
            ]);
        } catch (\Throwable $e) {
            return back()->with('gagal', 'Gagal mengimpor gambar denah: ' . $e->getMessage());
        }

        return redirect()->route('sarpras.denah.hotspot', $denah)
            ->with('sukses', 'Gambar denah berhasil diimpor. Sekarang atur blok ruangan.');
    }

    /** Simpan hasil gambar kanvas (data URL base64) sebagai gambar denah. */
    public function simpanGambar(Request $request, Denah $denah, FotoCompressor $compressor): RedirectResponse
    {
        $request->validate(['gambar_data' => ['required', 'string']]);

        $dataUrl = (string) $request->input('gambar_data');
        if (! preg_match('#^data:image/(png|jpeg|webp);base64,#', $dataUrl)) {
            return back()->with('gagal', 'Data gambar tidak valid.');
        }

        $binary = base64_decode(preg_replace('#^data:image/\w+;base64,#', '', $dataUrl), true);
        if ($binary === false || $binary === '') {
            return back()->with('gagal', 'Gagal membaca data gambar.');
        }

        try {
            $compressor->hapus($denah->gambar_path);
            $denah->update([
                'gambar_path' => $compressor->compressString($binary, 'sarpras/denah', 'webp'),
            ]);
        } catch (\Throwable $e) {
            return back()->with('gagal', 'Gagal menyimpan gambar denah: ' . $e->getMessage());
        }

        return redirect()->route('sarpras.denah.hotspot', $denah)
            ->with('sukses', 'Gambar denah tersimpan. Sekarang atur blok ruangan.');
    }

    /** Hapus gambar denah (mis. hasil import yang tidak sesuai). Blok ruangan tetap. */
    public function hapusGambar(Denah $denah, FotoCompressor $compressor): RedirectResponse
    {
        if (empty($denah->gambar_path)) {
            return back()->with('gagal', 'Belum ada gambar denah untuk dihapus.');
        }

        try {
            $compressor->hapus($denah->gambar_path);
            $denah->update(['gambar_path' => null]);
        } catch (\Throwable $e) {
            return back()->with('gagal', 'Gagal menghapus gambar denah: ' . $e->getMessage());
        }

        return redirect()->route('sarpras.denah.hotspot', $denah)
            ->with('sukses', 'Gambar denah dihapus. Anda bisa import ulang atau menggambar baru.');
    }

    /**
     * Bentuk nama denah. Bila nama dikosongkan, dibuat otomatis dari
     * gedung + lantai, mis. "Gedung A - Lantai 2".
     *
     * @param  array<string, mixed>  $data
     */
    private function namaDenah(array $data): string
    {
        if (filled($data['nama'] ?? null)) {
            return $data['nama'];
        }

        $gedung = trim((string) ($data['gedung'] ?? ''));
        $lantai = trim((string) ($data['lantai'] ?? ''));

        $parts = array_filter([
            $gedung !== '' ? $gedung : null,
            $lantai !== '' ? 'Lantai ' . $lantai : null,
        ]);

        return $parts ? implode(' - ', $parts) : 'Denah';
    }
}
