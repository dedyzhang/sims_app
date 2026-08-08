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
        // Peta pelajaran → kelas yg beneran diajar mapel itu (via Ngajar, LINTAS SEMUA guru —
        // sama spt kelasPilihan() yg dipakai store()/syncKelas() memvalidasi, supaya opsi yg
        // tampil di form konsisten dgn apa yg SERVER benar2 terima; guru tetap cuma bisa MEMULAI
        // dari pelajaran yg dia ajar sendiri — $ngajars di atas sudah scoped ke situ). Dipakai
        // Alpine di create.blade.php utk mengisi ulang pilihan Kelas begitu Mata Pelajaran
        // dipilih — "menyesuaikan dgn data ngajarnya" drpd nampilin SEMUA kelas sekolah.
        $bolehAturKelas = $this->bolehAturKelas($request->user());
        // Guru tak perlu (& tak boleh) menetapkan kelas sendiri saat membuat ujian — kelas
        // memang keputusan admin/pengelola (koordinasi jadwal lintas kelas/tingkat), guru
        // fokus ke konten (soal). Peta ini cuma dihitung & dikirim ke view kalau memang
        // dipakai (admin/pengelola) — lihat bolehAturKelas().
        $kelasByPelajaran = $bolehAturKelas
            ? $ngajars->unique('id_pelajaran')->mapWithKeys(
                fn ($n) => [$n->id_pelajaran => $this->kelasPilihan($n->id_pelajaran)->map(fn ($k) => ['uuid' => $k->uuid, 'label' => $k->tingkat . $k->kelas])->values()]
              )
            : collect();

        return view('ujian.create', ['ngajars' => $ngajars, 'materiByNgajar' => $materiByNgajar, 'kelasByPelajaran' => $kelasByPelajaran, 'bolehAturKelas' => $bolehAturKelas]);
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
            // TIDAK required_if lagi — guru tak mengirim id_kelas sama sekali (lihat blok
            // target_nilai!=sumatif di bawah); admin/pengelola tetap diwajibkan isi minimal
            // satu lewat abort_if manual, bukan validasi, supaya pesannya konsisten dgn
            // "kelas tak sesuai Ngajar" di bawahnya.
            'id_kelas'              => 'nullable|array',
            'id_kelas.*'            => 'uuid|exists:kelas,uuid',
            'durasi_menit'          => 'required|integer|min:5|max:600',
            'acak_soal'             => 'nullable|boolean',
            'acak_opsi'             => 'nullable|boolean',
            'tampilkan_pembahasan'  => 'nullable|boolean',
        ]);

        // target_nilai=sumatif: id_pelajaran & kelas diturunkan dari Materi->Ngajar (bukan dari
        // input pengguna) — Materi selalu benar mencerminkan mapel+kelas Ngajar pemiliknya, dan
        // sudah pasti tunggal (satu Materi = satu penugasan mengajar = satu kelas).
        $idKelas = [];
        if ($data['target_nilai'] === 'sumatif') {
            $materi = Materi::with('ngajar.kelas')->findOrFail($data['id_materi']);
            abort_unless($materi->ngajar, 422, 'Materi ini tidak terhubung ke penugasan mengajar yang valid.');
            if (!$user->isAdmin() && !$user->canAccess('manage_ujian')) {
                abort_unless($materi->ngajar->id_guru === $user->guru?->uuid, 403, 'Materi ini bukan milik penugasan mengajar Anda.');
            }
            $data['id_pelajaran'] = $materi->ngajar->id_pelajaran;
            if ($materi->ngajar->id_kelas) {
                $idKelas = [$materi->ngajar->id_kelas];
            }
        } else {
            $data['id_materi'] = null;
            if (!$this->bolehAturKelas($user)) {
                $mengajar = Ngajar::where('id_guru', $user->guru?->uuid ?? '-')
                    ->where('id_pelajaran', $data['id_pelajaran'])->exists();
                abort_unless($mengajar, 403, 'Anda tidak mengajar mata pelajaran ini.');
                // Guru tak menetapkan kelas sendiri saat membuat ujian — itu keputusan admin/
                // pengelola (koordinasi jadwal lintas kelas/tingkat), ditetapkan belakangan
                // lewat panel "Kelas & Token Masuk" di halaman ujian.
            } else {
                abort_if(empty($data['id_kelas'] ?? []), 422, 'Pilih minimal satu kelas untuk ujian ini.');
                // Kelas yg boleh dipilih WAJIB benar2 diajar mapel ini (via Ngajar) — cegah
                // admin menetapkan ujian ke kelas yg sama sekali tak ada penugasan mengajarnya
                // utk mapel tsb, walau id-nya valid di tabel kelas.
                $kelasValid = $this->kelasPilihan($data['id_pelajaran'])->pluck('uuid');
                $idKelas = collect($data['id_kelas'])->intersect($kelasValid)->values()->all();
                abort_if(empty($idKelas), 422, 'Kelas yang dipilih tidak sesuai dengan data penugasan mengajar (Ngajar) untuk mata pelajaran ini.');
            }
        }

        $ujian = DB::transaction(function () use ($request, $data, $user, $idKelas) {
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

            if (!empty($idKelas)) {
                $this->assignKelas($ujian, $idKelas);
            }

            return $ujian;
        });

        return redirect()->route('ujian.show', $ujian)->with('success', 'Ujian dibuat' . (!empty($idKelas) ? ' dan kelas ditetapkan' : '') . '. Lanjutkan menyusun soal.');
    }

    public function show(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->load(['soal.opsi', 'kelas.kelas', 'pelajaran', 'materi']);
        $kelasPilihan = $ujian->id_pelajaran ? $this->kelasPilihan($ujian->id_pelajaran) : collect();
        $bolehAturKelas = $this->bolehAturKelas($request->user());

        return view('ujian.show', compact('ujian', 'kelasPilihan', 'bolehAturKelas'));
    }

    public function edit(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->load(['soal.opsi', 'pelajaran']);

        return view('ujian.edit', compact('ujian'));
    }

    /** Halaman "Pengaturan Ujian" — metadata (judul/jenis/durasi/pelajaran/toggle), TERPISAH dari Susun Soal (ujian.edit). */
    public function editPengaturan(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->load('pelajaran');
        [$ngajars] = $this->ngajarPilihan($request->user());
        $pelajaranPilihan = $ngajars->pluck('pelajaran')->filter()->unique('uuid')->sortBy('nama')->values();

        return view('ujian.pengaturan', compact('ujian', 'pelajaranPilihan'));
    }

    public function update(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        abort_if($ujian->isClosed(), 422, 'Ujian yang sudah ditutup tidak bisa diubah.');

        $data = $request->validate([
            'judul'                 => 'required|string|max:150',
            'instruksi'             => 'nullable|string|max:5000',
            'jenis'                 => 'required|in:harian,pts,pas,uas',
            'id_pelajaran'          => 'nullable|uuid|exists:pelajarans,uuid',
            'durasi_menit'          => 'required|integer|min:5|max:600',
            'acak_soal'             => 'nullable|boolean',
            'acak_opsi'             => 'nullable|boolean',
            'tampilkan_pembahasan'  => 'nullable|boolean',
        ]);

        $update = [
            'judul'                 => $data['judul'],
            'instruksi'             => $data['instruksi'] ?? null,
            'jenis'                 => $data['jenis'],
            'durasi_menit'          => $data['durasi_menit'],
            'acak_soal'             => $request->boolean('acak_soal'),
            'acak_opsi'             => $request->boolean('acak_opsi'),
            'tampilkan_pembahasan'  => $request->boolean('tampilkan_pembahasan'),
        ];

        // Mata pelajaran cuma boleh diubah selama masih draf & bukan sumatif (sumatif diturunkan
        // dari Materi->Ngajar, tak masuk akal diubah lepas dari situ) — cegah ujian yg sudah
        // terbit/sudah ada kelas+token/soal tiba2 pindah konteks mapel di tengah jalan.
        if (!empty($data['id_pelajaran']) && $ujian->status === 'draft' && !$ujian->butuhSatuKelas()) {
            $user = $request->user();
            if (!$user->isAdmin() && !$user->canAccess('manage_ujian')) {
                abort_unless(Ngajar::where('id_guru', $user->guru?->uuid ?? '-')->where('id_pelajaran', $data['id_pelajaran'])->exists(), 403, 'Anda tidak mengajar mata pelajaran ini.');
            }
            if ($data['id_pelajaran'] !== $ujian->id_pelajaran) {
                // Ganti mapel bikin kelas+token LAMA (kalau ada) tak relevan lagi (kelas itu blm
                // tentu diajar mapel BARU) — lepas semua drpd diam2 nyimpen kelas salah konteks.
                $ujian->kelas()->delete();
            }
            $update['id_pelajaran'] = $data['id_pelajaran'];
        }

        $ujian->update($update);

        return redirect()->route('ujian.show', $ujian)->with('success', 'Ujian diperbarui.');
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
        abort_unless($this->bolehAturKelas($request->user()), 403, 'Hanya admin/pengelola yang bisa mengatur kelas ujian — guru pengampu fokus menyusun soal, kelas ditetapkan admin/pengelola.');
        abort_if($ujian->isClosed(), 422, 'Ujian yang sudah ditutup tidak bisa diubah kelasnya.');
        abort_unless($ujian->id_pelajaran, 422, 'Ujian ini belum punya mata pelajaran.');

        $data = $request->validate([
            'id_kelas'   => 'required|array|min:1',
            'id_kelas.*' => 'uuid|exists:kelas,uuid',
        ]);

        if ($ujian->butuhSatuKelas() && count($data['id_kelas']) > 1) {
            return back()->withErrors(['id_kelas' => 'Ujian dengan target nilai Sumatif (jenis Harian) hanya boleh untuk satu kelas — Materi bersifat khusus satu penugasan mengajar.'])->withInput();
        }

        // Kelas yg boleh ditetapkan WAJIB benar2 diajar mapel ujian ini (via Ngajar) — sama
        // spt di store(), cegah menetapkan kelas yg tak ada penugasan mengajarnya utk mapel ini.
        $kelasValid = $this->kelasPilihan($ujian->id_pelajaran)->pluck('uuid');
        $idKelas = collect($data['id_kelas'])->intersect($kelasValid)->values()->all();
        if (empty($idKelas)) {
            return back()->withErrors(['id_kelas' => 'Kelas yang dipilih tidak sesuai dengan data penugasan mengajar (Ngajar) untuk mata pelajaran ujian ini.'])->withInput();
        }

        DB::transaction(fn () => $this->assignKelas($ujian, $idKelas));

        return back()->with('success', 'Kelas ujian diperbarui.');
    }

    /**
     * Kelas yg BENERAN diajar mapel ini (via Ngajar) — SENGAJA lintas SEMUA guru (bukan
     * cuma guru yg sedang login), sama spt filosofi kolaborasi UjianPolicy::manage(): satu
     * ujian PTS/PAS bisa mencakup banyak kelas satu tingkat yg diampu guru BERBEDA-BEDA
     * (lihat test_guru_lain_yg_mengajar_kelas_lain...). Ini murni cek VALIDITAS DATA ("apakah
     * kelas ini beneran diajar mapel ini oleh SIAPAPUN"), bukan cek IZIN user saat ini —
     * izin "apakah user boleh kelola ujian ini" tetap ditegakkan terpisah lewat
     * UjianPolicy::manage() (utk kelas yg sudah ditetapkan) & cek `mengajar` di store() (utk
     * mapel yg mau dipakai). Dipakai baik saat Buat Ujian maupun panel "Kelas & Token Masuk".
     */
    private function kelasPilihan(string $idPelajaran)
    {
        return Ngajar::with('kelas')->where('id_pelajaran', $idPelajaran)->whereNotNull('id_kelas')
            ->get()->pluck('kelas')->filter()->unique('uuid')
            ->sortBy(fn ($k) => [$k->tingkat, $k->kelas])->values();
    }

    /**
     * Inti logika assign kelas+token (dipakai store() saat buat-sekaligus-tetapkan-kelas, DAN
     * syncKelas() saat ubah belakangan) — kelas dlm TINGKAT yg sama berbagi SATU token_masuk.
     */
    private function assignKelas(Ujian $ujian, array $idKelas): void
    {
        $existing = $ujian->kelas()->pluck('id_kelas', 'id_kelas');
        $kelasByUuid = \App\Models\Kelas::whereIn('uuid', $idKelas)->get()->keyBy('uuid');

        $tokenByTingkat = $ujian->kelas()->with('kelas')->get()
            ->filter(fn ($uk) => $uk->kelas)
            ->keyBy(fn ($uk) => $uk->kelas->tingkat)
            ->map(fn ($uk) => $uk->token_masuk);

        foreach ($idKelas as $id) {
            if ($existing->has($id)) {
                continue;
            }
            $tingkat = $kelasByUuid->get($id)?->tingkat;
            $token = $tokenByTingkat->get($tingkat) ?? UjianKelas::generateToken();
            $tokenByTingkat[$tingkat] = $token;

            UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $id, 'token_masuk' => $token]);
        }
        // Kelas yg tidak lagi dipilih dilepas — aman selama belum ada attempt (FK cascade
        // akan ikut menghapus attempt jika ada; UI harus memperingatkan sebelum submit).
        $ujian->kelas()->whereNotIn('id_kelas', $idKelas)->delete();
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

    /**
     * Reset token utk SEMUA kelas ujian ini sekaligus (dipakai dari kartu ringkas /ujian —
     * tanpa perlu buka halaman detail dulu) — tiap grup tingkat tetap dapat token barunya
     * sendiri-sendiri, sama spt regenerateToken() per-tingkat di halaman detail.
     */
    public function regenerateSemuaToken(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        abort_if($ujian->isClosed(), 422, 'Ujian yang sudah ditutup tidak bisa diubah tokennya.');
        abort_if($ujian->kelas()->count() === 0, 422, 'Ujian ini belum punya kelas ditetapkan.');

        $ujian->kelas()->with('kelas')->get()
            ->filter(fn ($uk) => $uk->kelas)
            ->groupBy(fn ($uk) => $uk->kelas->tingkat)
            ->each(function ($grup, $tingkat) use ($ujian) {
                $token = UjianKelas::generateToken();
                UjianKelas::where('id_ujian', $ujian->uuid)
                    ->whereHas('kelas', fn ($q) => $q->where('tingkat', $tingkat))
                    ->update(['token_masuk' => $token]);
            });

        return back()->with('success', 'Token masuk diperbarui untuk semua kelas pada ujian ini.');
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

    /**
     * Guru pengampu fokus menyusun soal — penetapan/perubahan KELAS ujian (siapa yg ambil,
     * koordinasi lintas kelas/tingkat) adalah keputusan admin/pengelola, bukan guru per-mapel.
     */
    private function bolehAturKelas($user): bool
    {
        return $user->isAdmin() || $user->canAccess('manage_ujian');
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
