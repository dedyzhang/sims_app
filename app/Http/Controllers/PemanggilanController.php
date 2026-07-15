<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\HandlesClassroomUploads;
use App\Models\Kelas;
use App\Models\Orangtua;
use App\Models\Pemanggilan;
use App\Models\PemanggilanDokumentasi;
use App\Models\Siswa;
use App\Services\FileCompressionService;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Rekapan pemanggilan orang tua/siswa: catatan permasalahan + hasil pertemuan,
 * dengan dokumentasi opsional. TIDAK memakai alur approval (beda dari poin_temp/
 * p3_temp) — ini murni catatan kejadian yang langsung tercatat oleh pembuatnya.
 */
class PemanggilanController extends Controller
{
    use HandlesClassroomUploads;

    private const DOKUMENTASI_MAX_MB = 5;

    // ─────────────── Akses ───────────────

    /** Kelola penuh (lihat semua, ubah/hapus punya siapa pun): admin/kesiswaan. */
    private function bisaKelolaSemua(): bool
    {
        return auth()->user()?->canAccess('manage_disiplin') ?? false;
    }

    private function guardKelolaSemua(): void
    {
        abort_unless($this->bisaKelolaSemua(), 403, 'Hanya admin/kesiswaan yang dapat mengakses ini.');
    }

    /** Bisa membuat catatan baru: admin/kesiswaan ATAU guru mana pun. */
    private function bisaBuat(): bool
    {
        return $this->bisaKelolaSemua() || (bool) auth()->user()?->guru;
    }

    private function guardBuat(): void
    {
        abort_unless($this->bisaBuat(), 403, 'Hanya guru atau kesiswaan yang dapat membuat catatan pemanggilan.');
    }

    /** Lihat 1 catatan: pengelola, ATAU guru yang mencatatnya sendiri. */
    private function bisaLihat(Pemanggilan $p): bool
    {
        return $this->bisaKelolaSemua() || $p->id_pencatat === auth()->id();
    }

    /** Ubah/hapus 1 catatan: pengelola, ATAU guru yang mencatatnya sendiri (bukan guru lain). */
    private function bisaKelola(Pemanggilan $p): bool
    {
        return $this->bisaKelolaSemua() || $p->id_pencatat === auth()->id();
    }

    private function guardKelola(Pemanggilan $p): void
    {
        abort_unless($this->bisaKelola($p), 403, 'Anda hanya dapat mengubah/menghapus catatan yang Anda buat sendiri.');
    }

    // ─────────────── Kelola (admin/kesiswaan): lihat semua ───────────────

    public function index(Request $request)
    {
        $this->guardKelolaSemua();

        $kelasList = Kelas::orderBy('tingkat')->orderBy('kelas')->get();
        $selectedKelas = $request->query('kelas', '');

        [$sort, $dir] = TableSort::resolve($request, ['tanggal', 'nama', 'dipanggil'], 'tanggal', 'desc');

        $items = Pemanggilan::with(['siswa.kelas', 'pencatat'])
            ->when($selectedKelas, fn ($q) => $q->whereHas('siswa', fn ($q2) => $q2->where('id_kelas', $selectedKelas)))
            ->when($request->filled('dari'), fn ($q) => $q->whereDate('tanggal', '>=', $request->query('dari')))
            ->when($request->filled('sampai'), fn ($q) => $q->whereDate('tanggal', '<=', $request->query('sampai')))
            ->when($request->filled('search'), fn ($q) => $q->whereHas('siswa', fn ($q2) => $q2
                ->where('nama', 'like', '%' . $request->query('search') . '%')))
            ->get();

        $sorted = $items->sortBy(function ($p) use ($sort) {
            return match ($sort) {
                'nama'      => $p->siswa?->nama,
                'dipanggil' => $p->dipanggil,
                default     => $p->tanggal,
            };
        }, SORT_REGULAR, $dir === 'desc')->values();

        $items = TableSort::paginateCollection($sorted, 20);

        return view('pemanggilan.index', compact('items', 'kelasList', 'selectedKelas'));
    }

    // ─────────────── Buat catatan (guru & kesiswaan) ───────────────

    public function create()
    {
        $this->guardBuat();
        return view('pemanggilan.create');
    }

    /** AJAX: cari siswa (nama/NIS) untuk pemilih di form buat — dipakai TomSelect remote search. */
    public function cariSiswa(Request $request)
    {
        $this->guardBuat();
        $q = trim((string) $request->query('q', ''));
        $siswas = Siswa::with('kelas')
            ->when($q !== '', fn ($qq) => $qq->where(fn ($qq2) => $qq2
                ->where('nama', 'like', "%{$q}%")
                ->orWhere('nis', 'like', "%{$q}%")))
            ->orderBy('nama')->limit(20)->get();

        return response()->json(['siswas' => $siswas->map(fn ($s) => [
            'uuid'  => $s->uuid,
            'nama'  => $s->nama,
            'nis'   => $s->nis,
            'kelas' => $s->kelas ? $s->kelas->tingkat . $s->kelas->kelas : '-',
        ])]);
    }

    public function store(Request $request)
    {
        $this->guardBuat();

        $data = $request->validate([
            'id_siswa'      => 'required|exists:siswa,uuid',
            'tanggal'       => 'required|date',
            'dipanggil'     => 'required|in:siswa,orangtua,keduanya',
            'perihal'       => 'required|string|max:150',
            'permasalahan'  => 'required|string',
            'hasil'         => 'nullable|string',
        ]);

        $max   = config('classroom.max_files', 10);
        $maxKb = self::DOKUMENTASI_MAX_MB * 1024;
        $request->validate([
            'files'   => "nullable|array|max:{$max}",
            'files.*' => "file|mimes:jpg,jpeg,png,webp,heic,pdf|max:{$maxKb}",
        ]);

        $panggilan = Pemanggilan::create([
            'id_siswa'     => $data['id_siswa'],
            'tanggal'      => $data['tanggal'],
            'dipanggil'    => $data['dipanggil'],
            'perihal'      => $data['perihal'],
            'permasalahan' => $data['permasalahan'],
            'hasil'        => $data['hasil'] ?? null,
            'id_pencatat'  => auth()->id(),
        ]);

        $this->simpanDokumentasi($panggilan, $request->file('files', []));

        return redirect()->route('pemanggilan.show', $panggilan)->with('success', 'Catatan pemanggilan berhasil disimpan.');
    }

    private function simpanDokumentasi(Pemanggilan $panggilan, array $files): void
    {
        $files = array_values(array_filter($files));
        if (empty($files)) {
            return;
        }
        $existing = $panggilan->dokumentasi()->max('sort_order');
        $sortBase = $existing === null ? 0 : $existing + 1;

        $svc = app(FileCompressionService::class);
        foreach ($files as $i => $file) {
            $meta = $svc->handle($file, 'pemanggilan/dokumentasi');
            PemanggilanDokumentasi::create(array_merge($meta, [
                'id_pemanggilan' => $panggilan->uuid,
                'sort_order'     => $sortBase + $i,
                'id_pengunggah'  => auth()->id(),
            ]));
        }
    }

    // ─────────────── Detail / Ubah / Hapus ───────────────

    public function show(Pemanggilan $panggilan)
    {
        abort_unless($this->bisaLihat($panggilan), 403, 'Anda tidak memiliki akses ke catatan ini.');
        $panggilan->load(['siswa.kelas', 'pencatat', 'dokumentasi']);
        return view('pemanggilan.show', [
            'panggilan' => $panggilan,
            'bisaKelola' => $this->bisaKelola($panggilan),
        ]);
    }

    public function edit(Pemanggilan $panggilan)
    {
        $this->guardKelola($panggilan);
        $panggilan->load(['siswa.kelas', 'dokumentasi']);
        return view('pemanggilan.edit', compact('panggilan'));
    }

    public function update(Request $request, Pemanggilan $panggilan)
    {
        $this->guardKelola($panggilan);

        $data = $request->validate([
            'tanggal'       => 'required|date',
            'dipanggil'     => 'required|in:siswa,orangtua,keduanya',
            'perihal'       => 'required|string|max:150',
            'permasalahan'  => 'required|string',
            'hasil'         => 'nullable|string',
        ]);

        $max   = config('classroom.max_files', 10);
        $maxKb = self::DOKUMENTASI_MAX_MB * 1024;
        $request->validate([
            'files'   => "nullable|array|max:{$max}",
            'files.*' => "file|mimes:jpg,jpeg,png,webp,heic,pdf|max:{$maxKb}",
        ]);

        $panggilan->update($data);
        $this->simpanDokumentasi($panggilan, $request->file('files', []));

        return redirect()->route('pemanggilan.show', $panggilan)->with('success', 'Catatan pemanggilan berhasil diperbarui.');
    }

    public function destroy(Pemanggilan $panggilan)
    {
        $this->guardKelola($panggilan);

        foreach ($panggilan->dokumentasi as $dok) {
            $this->hapusFileFisik($dok);
        }
        $panggilan->delete(); // dokumentasi ikut terhapus (cascade FK)

        return redirect()->route('pemanggilan.index')->with('success', 'Catatan pemanggilan dihapus.');
    }

    public function dokumentasiDestroy(Pemanggilan $panggilan, PemanggilanDokumentasi $dokumentasi)
    {
        $this->guardKelola($panggilan);
        abort_unless($dokumentasi->id_pemanggilan === $panggilan->uuid, 404);

        $this->hapusFileFisik($dokumentasi);
        $dokumentasi->delete();

        return back()->with('success', 'Dokumentasi dihapus.');
    }

    private function hapusFileFisik(PemanggilanDokumentasi $dok): void
    {
        if ($dok->path && Storage::disk('public')->exists($dok->path)) {
            Storage::disk('public')->delete($dok->path);
        }
    }

    // ─────────────── Riwayat guru (catatan yang ia buat sendiri) ───────────────

    public function guruRiwayat(Request $request)
    {
        abort_unless((bool) auth()->user()?->guru, 403, 'Hanya guru yang dapat mengakses ini.');

        [$sort, $dir] = TableSort::resolve($request, ['tanggal', 'nama'], 'tanggal', 'desc');
        $items = Pemanggilan::with(['siswa.kelas'])->where('id_pencatat', auth()->id())->get();

        $sorted = $items->sortBy(function ($p) use ($sort) {
            return $sort === 'nama' ? $p->siswa?->nama : $p->tanggal;
        }, SORT_REGULAR, $dir === 'desc')->values();

        $items = TableSort::paginateCollection($sorted, 20);

        return view('pemanggilan.riwayat', compact('items'));
    }

    // ─────────────── Self-view: siswa & orangtua (khusus milik sendiri) ───────────────

    public function self(Request $request)
    {
        $u = auth()->user();
        $siswa = $u->siswa ?: Orangtua::where('id_login', $u->uuid)->first()?->siswa;
        abort_unless($siswa, 404);

        $items = Pemanggilan::with(['dokumentasi'])
            ->where('id_siswa', $siswa->uuid)
            ->orderByDesc('tanggal')
            ->paginate(20);

        return view('pemanggilan.self', compact('siswa', 'items'));
    }
}
