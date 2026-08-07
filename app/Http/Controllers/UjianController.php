<?php

namespace App\Http\Controllers;

use App\Models\Materi;
use App\Models\Ngajar;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianKelas;
use App\Services\UjianNilaiTransfer;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class UjianController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                if ($request->user() && $request->user()->access === 'orangtua') {
                    abort(403, 'Akses ditolak.');
                }
                return $next($request);
            }),
        ];
    }

    /** Daftar ujian yang bisa dikelola user (guru pengampu, atau admin/kurikulum). */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Ujian::withCount('soal')->with('pelajaran', 'kelas.kelas')->latest();

        if (!$user->isAdmin() && !$user->canAccess('manage_ujian')) {
            $guru = $user->guru;
            $pairs = Ngajar::where('id_guru', $guru?->uuid ?? '-')->get(['id_pelajaran', 'id_kelas']);

            $query->where(function ($q) use ($user, $pairs) {
                $q->where('created_by', $user->uuid);
                foreach ($pairs as $p) {
                    $q->orWhere(function ($qq) use ($p) {
                        $qq->where('id_pelajaran', $p->id_pelajaran)
                           ->whereHas('kelas', fn ($k) => $k->where('id_kelas', $p->id_kelas));
                    });
                }
            });
        }

        $ujians = $query->get();

        return view('ujian.index', compact('ujians'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Ujian::class);

        [$ngajars] = $this->ngajarPilihan($request->user());
        $materiByNgajar = Materi::whereIn('id_ngajar', $ngajars->pluck('uuid'))->orderBy('nama')->get()->groupBy('id_ngajar');

        return view('ujian.create', ['ngajars' => $ngajars, 'materiByNgajar' => $materiByNgajar]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Ujian::class);
        $user = $request->user();

        $data = $request->validate([
            'judul'                 => 'required|string|max:150',
            'instruksi'             => 'nullable|string|max:5000',
            'jenis'                 => 'required|in:harian,pts,pas,uas',
            'target_nilai'          => 'required|in:pts,pas,sumatif',
            'id_pelajaran'          => 'required_if:target_nilai,pts,pas|nullable|uuid|exists:pelajarans,uuid',
            'id_materi'             => 'required_if:target_nilai,sumatif|nullable|uuid|exists:materi,uuid',
            'durasi_menit'          => 'required|integer|min:5|max:600',
            'acak_soal'             => 'nullable|boolean',
            'acak_opsi'             => 'nullable|boolean',
            'tampilkan_pembahasan'  => 'nullable|boolean',
        ]);

        // target_nilai=sumatif: id_pelajaran diturunkan dari Materi->Ngajar (bukan dari
        // input pengguna) — Materi selalu benar mencerminkan mapel Ngajar pemiliknya.
        if ($data['target_nilai'] === 'sumatif') {
            $materi = Materi::with('ngajar')->findOrFail($data['id_materi']);
            abort_unless($materi->ngajar, 422, 'Materi ini tidak terhubung ke penugasan mengajar yang valid.');
            if (!$user->isAdmin() && !$user->canAccess('manage_ujian')) {
                abort_unless($materi->ngajar->id_guru === $user->guru?->uuid, 403, 'Materi ini bukan milik penugasan mengajar Anda.');
            }
            $data['id_pelajaran'] = $materi->ngajar->id_pelajaran;
        } else {
            $data['id_materi'] = null;
            if (!$user->isAdmin() && !$user->canAccess('manage_ujian')) {
                $mengajar = Ngajar::where('id_guru', $user->guru?->uuid ?? '-')
                    ->where('id_pelajaran', $data['id_pelajaran'])->exists();
                abort_unless($mengajar, 403, 'Anda tidak mengajar mata pelajaran ini.');
            }
        }

        $ujian = Ujian::create([
            'id_pelajaran'          => $data['id_pelajaran'],
            'id_materi'             => $data['id_materi'] ?? null,
            'created_by'            => $user->uuid,
            'judul'                 => $data['judul'],
            'instruksi'             => $data['instruksi'] ?? null,
            'jenis'                 => $data['jenis'],
            'target_nilai'          => $data['target_nilai'],
            'durasi_menit'          => $data['durasi_menit'],
            'acak_soal'             => $request->boolean('acak_soal', true),
            'acak_opsi'             => $request->boolean('acak_opsi', true),
            'tampilkan_pembahasan'  => $request->boolean('tampilkan_pembahasan', false),
        ]);

        return redirect()->route('ujian.show', $ujian)->with('success', 'Ujian dibuat. Lanjutkan menyusun soal dan menetapkan kelas.');
    }

    public function show(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->load(['soal.opsi', 'kelas.kelas', 'pelajaran', 'materi']);

        return view('ujian.show', compact('ujian'));
    }

    public function edit(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->load(['soal.opsi', 'pelajaran']);

        return view('ujian.edit', compact('ujian'));
    }

    public function update(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        abort_if($ujian->isClosed(), 422, 'Ujian yang sudah ditutup tidak bisa diubah.');

        $data = $request->validate([
            'judul'                 => 'required|string|max:150',
            'instruksi'             => 'nullable|string|max:5000',
            'jenis'                 => 'required|in:harian,pts,pas,uas',
            'durasi_menit'          => 'required|integer|min:5|max:600',
            'acak_soal'             => 'nullable|boolean',
            'acak_opsi'             => 'nullable|boolean',
            'tampilkan_pembahasan'  => 'nullable|boolean',
        ]);

        $ujian->update([
            'judul'                 => $data['judul'],
            'instruksi'             => $data['instruksi'] ?? null,
            'jenis'                 => $data['jenis'],
            'durasi_menit'          => $data['durasi_menit'],
            'acak_soal'             => $request->boolean('acak_soal'),
            'acak_opsi'             => $request->boolean('acak_opsi'),
            'tampilkan_pembahasan'  => $request->boolean('tampilkan_pembahasan'),
        ]);

        return back()->with('success', 'Ujian diperbarui.');
    }

    /**
     * Tetapkan kelas yang mengambil ujian ini — dibatasi 1 kelas kalau target_nilai=sumatif.
     * Kelas-kelas dalam TINGKAT yang sama berbagi SATU token_masuk (PTS/PAS/UAS lintas kelas
     * satu angkatan) — tak ada kolom baru, cukup diturunkan dari kelas.tingkat tiap kali kelas
     * baru ditambahkan: kalau ujian ini sudah punya kelas lain di tingkat yang sama, token
     * kelas baru itu ikut token yang sudah ada; kalau belum, generate token baru.
     */
    public function syncKelas(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        abort_if($ujian->isClosed(), 422, 'Ujian yang sudah ditutup tidak bisa diubah kelasnya.');

        $data = $request->validate([
            'id_kelas'   => 'required|array|min:1',
            'id_kelas.*' => 'uuid|exists:kelas,uuid',
        ]);

        if ($ujian->butuhSatuKelas() && count($data['id_kelas']) > 1) {
            return back()->withErrors(['id_kelas' => 'Ujian dengan target nilai Sumatif (jenis Harian) hanya boleh untuk satu kelas — Materi bersifat khusus satu penugasan mengajar.'])->withInput();
        }

        DB::transaction(function () use ($ujian, $data) {
            $existing = $ujian->kelas()->pluck('id_kelas', 'id_kelas');
            $kelasByUuid = \App\Models\Kelas::whereIn('uuid', $data['id_kelas'])->get()->keyBy('uuid');

            // Token yg sudah dipakai per tingkat pada ujian ini (dari kelas yg sudah ter-assign
            // sebelumnya) — kelas baru di tingkat yg sama ikut token ini, bukan bikin token baru.
            $tokenByTingkat = $ujian->kelas()->with('kelas')->get()
                ->filter(fn ($uk) => $uk->kelas)
                ->keyBy(fn ($uk) => $uk->kelas->tingkat)
                ->map(fn ($uk) => $uk->token_masuk);

            foreach ($data['id_kelas'] as $idKelas) {
                if ($existing->has($idKelas)) {
                    continue;
                }
                $tingkat = $kelasByUuid->get($idKelas)?->tingkat;
                $token = $tokenByTingkat->get($tingkat) ?? UjianKelas::generateToken();
                $tokenByTingkat[$tingkat] = $token; // kelas lain di tingkat sama dlm batch ini ikut token ini juga

                UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $idKelas, 'token_masuk' => $token]);
            }
            // Kelas yg tidak lagi dipilih dilepas — aman selama belum ada attempt (FK cascade
            // akan ikut menghapus attempt jika ada; UI harus memperingatkan sebelum submit).
            $ujian->kelas()->whereNotIn('id_kelas', $data['id_kelas'])->delete();
        });

        return back()->with('success', 'Kelas ujian diperbarui.');
    }

    /**
     * Regenerate token — berlaku utk SELURUH kelas di tingkat yang sama pada ujian ini (bukan
     * cuma satu kelas), supaya token antar kelas satu tingkat tidak pernah out-of-sync.
     */
    public function regenerateToken(Request $request, Ujian $ujian, UjianKelas $ujianKelas)
    {
        $this->authorize('manage', $ujian);
        abort_unless($ujianKelas->id_ujian === $ujian->uuid, 404);

        $tingkat = $ujianKelas->kelas?->tingkat;
        $token = UjianKelas::generateToken();

        if ($tingkat !== null) {
            UjianKelas::where('id_ujian', $ujian->uuid)
                ->whereHas('kelas', fn ($q) => $q->where('tingkat', $tingkat))
                ->update(['token_masuk' => $token]);
        } else {
            $ujianKelas->update(['token_masuk' => $token]);
        }

        return back()->with('success', 'Token masuk diperbarui untuk semua kelas tingkat ' . $tingkat . ' pada ujian ini.');
    }

    public function publish(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);

        if ($ujian->soal()->count() === 0) {
            return back()->with('error', 'Ujian belum punya soal.');
        }
        if ($ujian->kelas()->count() === 0) {
            return back()->with('error', 'Ujian belum ditetapkan ke kelas mana pun.');
        }

        $ujian->update(['status' => 'published']);

        return back()->with('success', 'Ujian diterbitkan. Token masuk sudah bisa dibagikan ke siswa.');
    }

    public function close(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->update(['status' => 'closed']);

        return back()->with('success', 'Ujian ditutup — siswa tidak bisa lagi memulai/melanjutkan.');
    }

    /**
     * Rilis/sembunyikan pembahasan ke siswa — sengaja TERPISAH dari update() biasa
     * (yg terkunci setelah ujian ditutup) krn justru baru masuk akal dipakai
     * SETELAH ujian ditutup, ketika semua siswa selesai mengerjakan.
     */
    public function togglePembahasan(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->update(['tampilkan_pembahasan' => !$ujian->tampilkan_pembahasan]);

        return back()->with('success', $ujian->tampilkan_pembahasan
            ? 'Pembahasan dirilis — siswa sekarang bisa melihatnya di halaman hasil mereka.'
            : 'Pembahasan disembunyikan kembali dari siswa.');
    }

    public function destroy(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        abort_if($ujian->isPublished(), 422, 'Tutup ujian dulu sebelum menghapusnya.');

        $ujian->delete();

        return redirect()->route('ujian.index')->with('success', 'Ujian dihapus.');
    }

    /** Rekap skor per siswa lintas kelas + status transfer ke buku nilai. */
    public function hasil(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);

        $ujianKelasList = $ujian->kelas()->with('kelas')->get();
        $attempts = UjianAttempt::whereIn('id_ujian_kelas', $ujianKelasList->pluck('uuid'))
            ->whereIn('status', [UjianAttempt::STATUS_SUBMITTED, UjianAttempt::STATUS_DINILAI])
            ->get();
        $siswaByLogin = Siswa::whereIn('id_login', $attempts->pluck('id_siswa'))->get()->keyBy('id_login');

        return view('ujian.hasil.index', compact('ujian', 'ujianKelasList', 'attempts', 'siswaByLogin'));
    }

    /**
     * Satu-satunya aksi manual yg disengaja di alur transfer nilai — dipakai kalau
     * transfer otomatis sebelumnya gagal krn rapor terkunci, dan admin memang sudah
     * sadar membuka kunci itu utk memasukkan nilai ini.
     */
    public function transferUlang(Request $request, Ujian $ujian, UjianAttempt $attempt, UjianNilaiTransfer $transfer)
    {
        $this->authorize('manage', $ujian);
        abort_unless($attempt->ujianKelas->id_ujian === $ujian->uuid, 404);
        abort_unless($attempt->status === UjianAttempt::STATUS_DINILAI, 422, 'Attempt ini belum selesai dinilai.');

        $transfer->transfer($attempt);

        return back()->with('success', 'Transfer nilai dicoba ulang — status: ' . $attempt->fresh()->status_transfer_nilai);
    }

    /** @return array{0: \Illuminate\Support\Collection} */
    private function ngajarPilihan($user): array
    {
        $query = Ngajar::with(['pelajaran', 'kelas'])
            ->whereNotNull('id_guru')->whereNotNull('id_pelajaran')->whereNotNull('id_kelas');

        if (!$user->isAdmin() && !$user->canAccess('manage_ujian')) {
            $query->where('id_guru', $user->guru?->uuid ?? '-');
        }

        $ngajars = $query->get()->sortBy(fn ($n) => [$n->pelajaran?->urutan, $n->pelajaran?->nama, $n->kelas?->tingkat, $n->kelas?->kelas])->values();

        return [$ngajars];
    }
}
