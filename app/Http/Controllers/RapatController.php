<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesClassroomUploads;
use App\Models\Guru;
use App\Models\Rapat;
use App\Models\RapatDokumentasi;
use App\Models\Setting;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RapatController extends Controller
{
    use HandlesClassroomUploads;

    private const DOKUMENTASI_MAX_MB = 5;

    /**
     * Kelola penuh (buat/ubah/hapus rapat, absensi, dokumentasi, cetak):
     * admin, atau role dengan permission manage_rapat, atau guru yang
     * ditunjuk sebagai sekretaris rapat (flag global, lihat gurus.sekretaris_rapat).
     */
    private function canManage(): bool
    {
        $u = auth()->user();
        return $u->canAccess('manage_rapat') || (bool) $u->guru?->sekretaris_rapat;
    }

    /** Lihat daftar & detail rapat: semua staff (guru + kesiswaan/sarpras/kurikulum/kepala/admin). */
    private function canView(): bool
    {
        $u = auth()->user();
        if ($this->canManage()) return true;
        if ($u->guru) return true;
        return in_array($u->access, ['kesiswaan', 'sarpras', 'kurikulum', 'kepala']);
    }

    private function ensureView(): void
    {
        abort_unless($this->canView(), 403, 'Akses ditolak.');
    }

    private function ensureManage(): void
    {
        abort_unless($this->canManage(), 403, 'Hanya admin/kurikulum/kepala atau sekretaris rapat yang bisa mengelola ini.');
    }

    public function index(Request $request)
    {
        $this->ensureView();

        $rapats = Rapat::withCount(['guruHadir', 'dokumentasi'])
            ->orderByDesc('tanggal')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('rapat.index', [
            'rapats'     => $rapats,
            'canManage'  => $this->canManage(),
        ]);
    }

    public function create()
    {
        $this->ensureManage();
        return view('rapat.form', ['rapat' => new Rapat()]);
    }

    public function store(Request $request)
    {
        $this->ensureManage();

        $data = $request->validate([
            'judul'               => 'required|string|max:190',
            'tanggal'             => 'required|date',
            'pokok_permasalahan'  => 'nullable|string',
            'hasil_rapat'         => 'nullable|string',
        ]);
        $data['pokok_permasalahan'] = RichText::clean($data['pokok_permasalahan'] ?? '');
        $data['hasil_rapat']        = RichText::clean($data['hasil_rapat'] ?? '');
        $data['id_pencatat']        = auth()->user()->guru?->uuid;

        $rapat = Rapat::create($data);

        return redirect()->route('rapat.show', $rapat)->with('success', 'Rapat berhasil dicatat.');
    }

    public function show(Rapat $rapat)
    {
        $this->ensureView();
        $rapat->load(['guruHadir', 'dokumentasi', 'pencatat']);

        return view('rapat.show', [
            'rapat'     => $rapat,
            'canManage' => $this->canManage(),
        ]);
    }

    public function edit(Rapat $rapat)
    {
        $this->ensureManage();
        return view('rapat.form', ['rapat' => $rapat]);
    }

    public function update(Request $request, Rapat $rapat)
    {
        $this->ensureManage();

        $data = $request->validate([
            'judul'               => 'required|string|max:190',
            'tanggal'             => 'required|date',
            'pokok_permasalahan'  => 'nullable|string',
            'hasil_rapat'         => 'nullable|string',
        ]);
        $data['pokok_permasalahan'] = RichText::clean($data['pokok_permasalahan'] ?? '');
        $data['hasil_rapat']        = RichText::clean($data['hasil_rapat'] ?? '');

        $rapat->update($data);

        return redirect()->route('rapat.show', $rapat)->with('success', 'Rapat berhasil diperbarui.');
    }

    public function destroy(Rapat $rapat)
    {
        $this->ensureManage();

        foreach ($rapat->dokumentasi as $dok) {
            $this->hapusFileFisik($dok);
        }
        $rapat->delete(); // dokumentasi & rapat_hadir ikut terhapus (cascade FK)

        return redirect()->route('rapat.index')->with('success', 'Rapat berhasil dihapus.');
    }

    /** Form absensi kehadiran guru pada satu rapat. */
    public function hadir(Rapat $rapat)
    {
        $this->ensureManage();
        $gurus = Guru::orderBy('nama')->get();
        $hadirIds = $rapat->guruHadir()->pluck('gurus.uuid')->all();

        return view('rapat.hadir', compact('rapat', 'gurus', 'hadirIds'));
    }

    public function hadirStore(Request $request, Rapat $rapat)
    {
        $this->ensureManage();

        $ids = $request->input('guru', []);
        $valid = Guru::whereIn('uuid', $ids)->pluck('uuid')->all();
        $rapat->guruHadir()->sync($valid);

        return redirect()->route('rapat.show', $rapat)->with('success', 'Kehadiran rapat tersimpan (' . count($valid) . ' guru).');
    }

    /** Form + galeri dokumentasi (foto) rapat. */
    public function dokumentasi(Rapat $rapat)
    {
        $this->ensureManage();
        $rapat->load('dokumentasi');

        return view('rapat.dokumentasi', compact('rapat'));
    }

    public function dokumentasiStore(Request $request, Rapat $rapat)
    {
        $this->ensureManage();

        // Batas upload dokumentasi rapat sengaja lebih ketat (5MB) drpd classroom.max_file_mb —
        // ini cuma foto dokumentasi, bukan materi ajar; hasil akhir tetap dikompres ke WebP q80
        // oleh FileCompressionService (resize maks 1600px) supaya jelas tapi ringan.
        $max   = config('classroom.max_files', 10);
        $maxKb = self::DOKUMENTASI_MAX_MB * 1024;

        $request->validate([
            'files'   => "required|array|max:{$max}",
            'files.*' => "file|mimes:jpg,jpeg,png,webp,heic,pdf|max:{$maxKb}",
        ]);

        $existing = $rapat->dokumentasi()->max('sort_order');
        $sortBase = $existing === null ? 0 : $existing + 1;

        $svc = app(\App\Services\FileCompressionService::class);
        foreach ($request->file('files') as $i => $file) {
            $meta = $svc->handle($file, 'rapat/dokumentasi');
            RapatDokumentasi::create(array_merge($meta, [
                'id_rapat'       => $rapat->uuid,
                'sort_order'     => $sortBase + $i,
                'id_pengunggah'  => auth()->user()->guru?->uuid,
            ]));
        }

        return redirect()->route('rapat.dokumentasi', $rapat)->with('success', 'Dokumentasi berhasil diunggah.');
    }

    public function dokumentasiDestroy(Rapat $rapat, RapatDokumentasi $dokumentasi)
    {
        $this->ensureManage();
        abort_unless($dokumentasi->id_rapat === $rapat->uuid, 404);

        $this->hapusFileFisik($dokumentasi);
        $dokumentasi->delete();

        return back()->with('success', 'Dokumentasi dihapus.');
    }

    private function hapusFileFisik(RapatDokumentasi $dok): void
    {
        if ($dok->path && Storage::disk('public')->exists($dok->path)) {
            Storage::disk('public')->delete($dok->path);
        }
    }

    /** Halaman cetak berita acara rapat + galeri dokumentasi. */
    public function cetak(Rapat $rapat)
    {
        $this->ensureView();
        $rapat->load(['guruHadir', 'dokumentasi']);

        $kepsek = Guru::whereHas('user', fn($q) => $q->where('access', 'kepala'))->first();

        return view('rapat.cetak', [
            'rapat'         => $rapat,
            'namaSekolah'   => Setting::get('nama_sekolah', ''),
            'alamatSekolah' => Setting::get('alamat_sekolah', ''),
            'kopTeks'       => Setting::get('kop_teks'),
            'kopLogoKiri'   => $this->kopImg('kop_logo_kiri', 'img/tutwuri.png'),
            'kopLogoKanan'  => $this->kopImg('kop_logo_kanan', 'img/maitreyawira_square.png'),
            'kepsekNama'    => $kepsek?->nama ?? Setting::get('kepala_sekolah', ''),
            'kepsekNip'     => $kepsek?->nip ?? Setting::get('nip_kepala', ''),
        ]);
    }

    private function kopImg(string $key, string $default): ?string
    {
        $v = Setting::get($key);
        if ($v && Storage::disk('public')->exists($v)) {
            return asset('storage/' . $v);
        }
        if (file_exists(public_path($default))) {
            return asset($default);
        }
        return null;
    }

    /** Halaman admin: kelola guru yang ditunjuk sekretaris rapat. */
    public function sekretaris(Request $request)
    {
        abort_unless(auth()->user()->canAccess('manage_rapat'), 403);

        $q = trim((string) $request->query('q', ''));
        $gurus = Guru::when($q, fn($query) => $query->where('nama', 'like', "%{$q}%"))
            ->orderByDesc('sekretaris_rapat')
            ->orderBy('nama')
            ->paginate(20)
            ->withQueryString();

        return view('rapat.sekretaris', compact('gurus', 'q'));
    }

    public function sekretarisToggle(Guru $guru)
    {
        abort_unless(auth()->user()->canAccess('manage_rapat'), 403);

        $guru->update(['sekretaris_rapat' => !$guru->sekretaris_rapat]);

        return back()->with('success', $guru->sekretaris_rapat
            ? "{$guru->nama} ditunjuk sebagai sekretaris rapat."
            : "{$guru->nama} tidak lagi menjadi sekretaris rapat.");
    }
}
