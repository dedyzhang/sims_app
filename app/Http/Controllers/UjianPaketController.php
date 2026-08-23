<?php

namespace App\Http\Controllers;

use App\Models\Ujian;
use App\Models\UjianPaket;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * "Folder" pengelompokan periode ujian formal (mis. "PAS Semester 1 2026/2027")
 * — menaungi ujian-ujian anggota (satu per mapel, tetap berdiri sendiri), lalu
 * dari sini guru/admin masuk ke pengaturan Ruangan & Jadwal (lihat
 * UjianRuanganController/UjianJadwalController). Otorisasi setara "pengelola
 * ujian" (admin/manage_ujian) atau pembuat paket — BUKAN per-mapel spt Ujian
 * biasa, krn paket menaungi banyak mapel sekaligus. Menu sidebar-nya sengaja
 * disembunyikan dari guru biasa (lihat layouts/app.blade.php) krn alur normal
 * guru sekarang cuma Ulangan Harian lepas — tapi kalau ada guru yg memang perlu
 * bikin paket sendiri (kasus khusus), akses backend ini TETAP dibuka lewat URL
 * langsung, bukan dicabut sepenuhnya.
 */
class UjianPaketController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                if ($request->user() && !($request->user()->isAdmin() || $request->user()->canAccess('manage_ujian') || $request->user()->guru)) {
                    abort(403, 'Akses ditolak.');
                }
                return $next($request);
            }),
        ];
    }

    private function bolehKelola($user, ?UjianPaket $paket = null): bool
    {
        if ($user->isAdmin() || $user->canAccess('manage_ujian')) {
            return true;
        }
        return $paket && $paket->created_by === $user->uuid;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $query = UjianPaket::withCount(['ujian', 'ruangan'])->with('semester')->latest();
        if (!$user->isAdmin() && !$user->canAccess('manage_ujian')) {
            $query->where('created_by', $user->uuid);
        }
        $paketList = $query->get();

        return view('ujian.paket.index', compact('paketList'));
    }

    public function create()
    {
        $semesterList = \App\Models\Semester::orderByDesc('tahun')->orderByDesc('semester')->get();

        return view('ujian.paket.create', compact('semesterList'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama'             => 'required|string|max:150',
            'jenis'            => 'required|in:harian,pts,pas,uas',
            'id_semester'      => 'nullable|exists:semesters,id',
            'tanggal_mulai'    => 'nullable|date',
            'tanggal_selesai'  => 'nullable|date|after_or_equal:tanggal_mulai',
        ]);
        $data['created_by'] = $request->user()->uuid;

        $paket = UjianPaket::create($data);

        return redirect()->route('ujian.paket.show', $paket)->with('success', 'Paket ujian dibuat — sekarang tambahkan ujian, ruangan, dan jadwalnya.');
    }

    public function show(Request $request, UjianPaket $paket)
    {
        $paket->load(['semester', 'ujian.pelajaran', 'ruangan.peserta', 'jadwal.ujian']);

        // Ujian standalone milik user ini (belum masuk paket manapun) — kandidat utk ditambahkan.
        $user = $request->user();
        $calonUjian = Ujian::whereNull('id_ujian_paket')
            ->when(!$this->bolehKelola($user, $paket), fn ($q) => $q->where('created_by', $user->uuid))
            ->orderByDesc('created_at')->get();

        return view('ujian.paket.show', compact('paket', 'calonUjian'));
    }

    /** Tak ada halaman edit terpisah — form edit tampil sbg modal inline di show(), POST langsung ke update(). */
    public function update(Request $request, UjianPaket $paket)
    {
        abort_unless($this->bolehKelola($request->user(), $paket), 403);

        $data = $request->validate([
            'nama'             => 'required|string|max:150',
            'jenis'            => 'required|in:harian,pts,pas,uas',
            'id_semester'      => 'nullable|exists:semesters,id',
            'tanggal_mulai'    => 'nullable|date',
            'tanggal_selesai'  => 'nullable|date|after_or_equal:tanggal_mulai',
            'status'           => 'required|in:draft,berjalan,selesai',
        ]);
        $paket->update($data);

        return back()->with('success', 'Paket ujian diperbarui.');
    }

    public function destroy(Request $request, UjianPaket $paket)
    {
        abort_unless($this->bolehKelola($request->user(), $paket), 403);
        // Ujian anggota TIDAK ikut terhapus (id_ujian_paket nullOnDelete) — cuma folder-nya yg lenyap.
        $paket->delete();

        return redirect()->route('ujian.paket.index')->with('success', 'Paket ujian dihapus — ujian anggotanya tetap ada sbg ujian standalone.');
    }

    public function tambahUjian(Request $request, UjianPaket $paket)
    {
        abort_unless($this->bolehKelola($request->user(), $paket), 403);
        $data = $request->validate(['id_ujian' => 'required|exists:ujians,uuid']);

        $ujian = Ujian::whereNull('id_ujian_paket')->findOrFail($data['id_ujian']);
        abort_unless($this->bolehKelola($request->user(), $paket) || $ujian->created_by === $request->user()->uuid, 403);
        $ujian->update(['id_ujian_paket' => $paket->uuid]);

        return back()->with('success', "Ujian \"{$ujian->judul}\" ditambahkan ke paket.");
    }

    public function lepasUjian(Request $request, UjianPaket $paket, Ujian $ujian)
    {
        abort_unless($this->bolehKelola($request->user(), $paket), 403);
        abort_unless($ujian->id_ujian_paket === $paket->uuid, 404);
        $ujian->update(['id_ujian_paket' => null]);

        return back()->with('success', "Ujian \"{$ujian->judul}\" dilepas dari paket (tetap ada sbg ujian standalone).");
    }
}
