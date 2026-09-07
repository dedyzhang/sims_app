<?php

namespace App\Http\Controllers;

use App\Models\Materi;
use App\Models\Ngajar;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Models\UjianPelanggaran;
use App\Models\UjianSoal;
use App\Models\User;
use App\Services\UjianNilaiTransfer;
use App\Services\UjianRoster;
use App\Support\RichText;
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

        // Guru biasa (bukan admin/pengelola) cuma boleh bikin Ulangan Harian yang otomatis
        // masuk Sumatif — ujian formal PTS/PAS/UAS sekarang lewat Paket Ujian (admin/pengelola),
        // bukan lagi ujian lepas per-guru. Dipaksa di sini (bukan cuma disembunyikan di form)
        // supaya submit hasil tempering tetap kena aturan yang sama.
        if (!$this->bolehAturKelas($user)) {
            $data['jenis'] = 'harian';
            $data['target_nilai'] = 'sumatif';
            abort_unless(!empty($data['id_materi'] ?? null), 422, 'Pilih materi untuk ujian ini.');
        }

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
        $ujian->load(['soal.opsi', 'kelas.kelas', 'kelas.guruPengampu', 'pelajaran', 'materi']);
        $kelasPilihan = $ujian->id_pelajaran ? $this->kelasPilihan($ujian->id_pelajaran) : collect();
        $bolehAturKelas = $this->bolehAturKelas($request->user());

        // Daftar guru yg BENERAN cocok (via Ngajar) utk tiap kelas ter-assign — dipakai view
        // utk isi opsi dropdown "Guru Pengampu" (selalu tampil, apa pun jumlah kandidatnya).
        $guruPengampuOpsi = $ujian->id_pelajaran
            ? $ujian->kelas->mapWithKeys(function ($uk) use ($ujian) {
                $matches = Ngajar::with('guru')
                    ->where('id_pelajaran', $ujian->id_pelajaran)
                    ->where('id_kelas', $uk->id_kelas)
                    ->get()->pluck('guru')->filter()->unique('uuid')->values();
                return [$uk->uuid => $matches];
            })
            : collect();

        return view('ujian.show', compact('ujian', 'kelasPilihan', 'bolehAturKelas', 'guruPengampuOpsi'));
    }

    public function edit(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->load(['soal.opsi', 'pelajaran']);

        return view('ujian.edit', compact('ujian'));
    }

    /**
     * Pratinjau tampilan siswa (mobile) utk guru — cek visual soal SEBELUM ujian
     * diterbitkan. Pakai kerangka Alpine yg SAMA dgn UjianSiswaController::kerjakan()
     * (badge huruf, checkbox, layout match, rumus/gambar) supaya benar2 identik dgn yg
     * dilihat siswa nanti. BEDA dari kerjakan sungguhan: TAK ADA UjianAttempt, soal/opsi
     * tampil apa adanya sesuai urutan disusun (tak diacak spt attempt sungguhan), tak ada
     * autosave/countdown/fullscreen-lock — jawaban HANYA di memori Alpine browser, tak
     * pernah dikirim ke server, murni utk guru coba-coba lihat tampilan.
     *
     * BEDA LAIN dari kerjakan(): halaman ini KHUSUS GURU (tak pernah dilihat siswa), jadi
     * kunci jawaban (opsi.benar, kunci_esai) SENGAJA ikut dikirim — dipakai utk fitur
     * "Tampilkan Jawaban Benar" (form pratinjau bisa langsung terisi kunci) di sisi klien.
     */
    public function pratinjau(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->load(['soal' => fn ($q) => $q->orderBy('urutan'), 'soal.opsi']);

        $soalTampil = $ujian->soal->map(function (UjianSoal $s) {
            $item = ['uuid' => $s->uuid, 'tipe' => $s->tipe, 'teks_soal' => RichText::clean($s->teks_soal), 'poin' => $s->poinEfektif()];
            if ($s->tipe === 'match') {
                // kiri[i] & kanan_acak[i] SENGAJA berasal dari $pairs[i] yg sama (index sejajar,
                // TAK diacak spt attempt sungguhan) — jadi pasangan kunci-nya persis kanan_acak[i].
                $pairs = $s->meta['pairs'] ?? [];
                $item['kiri'] = collect($pairs)->pluck('left')->map(fn ($t) => RichText::clean($t))->all();
                $item['kanan_acak'] = collect($pairs)->pluck('right')->map(fn ($t) => RichText::clean($t))->all();
            } elseif ($s->butuhOpsi()) {
                $item['opsi'] = $s->opsi->map(fn ($o) => ['uuid' => $o->uuid, 'teks' => RichText::clean($o->teks_opsi), 'benar' => (bool) $o->is_benar])->values()->all();
            } elseif ($s->tipe === 'essay' && !empty($s->meta['kunci_jawaban'] ?? null)) {
                $item['kunci_esai'] = RichText::clean($s->meta['kunci_jawaban']);
            }
            return $item;
        })->values();

        return view('ujian.pratinjau', compact('ujian', 'soalTampil'));
    }

    /** Halaman "Pengaturan Ujian" — metadata (judul/jenis/durasi/pelajaran/toggle), TERPISAH dari Susun Soal (ujian.edit). */
    public function editPengaturan(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->load('pelajaran');
        [$ngajars] = $this->ngajarPilihan($request->user());
        $pelajaranPilihan = $ngajars->pluck('pelajaran')->filter()->unique('uuid')->sortBy('nama')->values();
        $bolehAturKelas = $this->bolehAturKelas($request->user());

        return view('ujian.pengaturan', compact('ujian', 'pelajaranPilihan', 'bolehAturKelas'));
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
            'target_nilai'          => 'nullable|in:pts,pas',
            'durasi_menit'          => 'required|integer|min:5|max:600',
            'acak_soal'             => 'nullable|boolean',
            'acak_opsi'             => 'nullable|boolean',
            'tampilkan_pembahasan'  => 'nullable|boolean',
        ]);

        $update = [
            'judul'                 => $data['judul'],
            'instruksi'             => $data['instruksi'] ?? null,
            // Guru biasa terkunci ke 'harian' (lihat catatan sama di store()) — cuma
            // admin/pengelola yang boleh ganti Jenis ke pts/pas/uas.
            'jenis'                 => $this->bolehAturKelas($request->user()) ? $data['jenis'] : 'harian',
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

        // Target nilai (ke mana skor ditransfer: NilaiPts vs NilaiPas) SEBELUMNYA tak bisa
        // diubah sama sekali stlh dibuat — cuma "Jenis" (label PTS/PAS/dst, murni tampilan)
        // yg py form field di sini, bikin guru kira ganti Jenis ikut mengubah target transfer
        // padahal tidak. Dibolehkan diubah persis spt id_pelajaran di atas: cuma selama masih
        // draf & bukan sumatif (sumatif diturunkan dari Materi->Ngajar, bukan pilihan bebas).
        if (!empty($data['target_nilai']) && $ujian->status === 'draft' && !$ujian->butuhSatuKelas()) {
            $update['target_nilai'] = $data['target_nilai'];
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

            // Kalau cuma SATU guru yg beneran cocok (Ngajar) utk mapel+kelas ini, langsung
            // jadikan pengampu — tak ada ambiguitas jadi tak perlu dipilih manual admin.
            // Kalau ada 2+, dibiarkan null, dipilih lewat panel "Guru Pengampu" di bawah.
            $guruMatch = Ngajar::where('id_pelajaran', $ujian->id_pelajaran)->where('id_kelas', $id)->pluck('id_guru');
            $pengampu = $guruMatch->unique()->count() === 1 ? $guruMatch->first() : null;

            UjianKelas::create(['id_ujian' => $ujian->uuid, 'id_kelas' => $id, 'token_masuk' => $token, 'id_guru_pengampu' => $pengampu]);
        }
        // Kelas yg tidak lagi dipilih dilepas — aman selama belum ada attempt (FK cascade
        // akan ikut menghapus attempt jika ada; UI harus memperingatkan sebelum submit).
        $ujian->kelas()->whereNotIn('id_kelas', $idKelas)->delete();

        // Kelas yg BARU ditambahkan blm py dibuka_mulai/sampai — kalau ujian ini sudah py
        // jadwal (UjianJadwal), terapkan ulang supaya kelas baru ikut kebagian window yg
        // sama, bukan cuma kelas yg sudah ada saat jadwal pertama kali disimpan. No-op
        // aman kalau ujian ini belum/tak pernah punya jadwal.
        app(\App\Services\UjianJadwalSync::class)->terapkan($ujian);
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
     * Tetapkan/ganti guru pengampu satu kelas ujian — dipakai baik saat kelas itu pertama kali
     * ambigu (2+ Ngajar match) MAUPUN utk mengganti pengampu belakangan (mis. staf berubah).
     * Satu aksi utk dua kegunaan, bukan dua endpoint terpisah.
     */
    public function setPengampu(Request $request, Ujian $ujian, UjianKelas $ujianKelas)
    {
        $this->authorize('manage', $ujian);
        abort_unless($ujianKelas->id_ujian === $ujian->uuid, 404);

        $data = $request->validate([
            'id_guru_pengampu' => 'required|uuid',
        ]);

        $cocok = Ngajar::where('id_pelajaran', $ujian->id_pelajaran)
            ->where('id_kelas', $ujianKelas->id_kelas)
            ->where('id_guru', $data['id_guru_pengampu'])
            ->exists();
        abort_unless($cocok, 422, 'Guru yang dipilih tidak mengajar mata pelajaran ini di kelas tersebut.');

        $ujianKelas->update(['id_guru_pengampu' => $data['id_guru_pengampu']]);

        return back()->with('success', 'Guru pengampu kelas ' . ($ujianKelas->kelas?->tingkat . $ujianKelas->kelas?->kelas) . ' disimpan.');
    }

    /**
     * Simpan pengampu utk SEMUA kelas ujian ini sekaligus lewat satu tombol — dipakai dari
     * form gabungan di ujian.show (bukan satu form+tombol per kelas spt setPengampu() di
     * atas, yg bikin banyak tombol berjejer kalau kelasnya banyak). Kelas yg select-nya
     * dibiarkan kosong ("— pilih pengampu —") dilewati, bukan dianggap "hapus pengampu".
     */
    public function setPengampuBatch(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);

        $data = $request->validate([
            'pengampu'   => 'array',
            'pengampu.*' => 'nullable|uuid',
        ]);

        $ujian->load('kelas.kelas');
        $disimpan = 0;

        foreach ($data['pengampu'] ?? [] as $idUjianKelas => $idGuru) {
            if (!$idGuru) continue;

            $ujianKelas = $ujian->kelas->firstWhere('uuid', $idUjianKelas);
            abort_unless($ujianKelas, 404);

            $cocok = Ngajar::where('id_pelajaran', $ujian->id_pelajaran)
                ->where('id_kelas', $ujianKelas->id_kelas)
                ->where('id_guru', $idGuru)
                ->exists();
            abort_unless($cocok, 422, 'Guru pengampu kelas ' . ($ujianKelas->kelas?->tingkat . $ujianKelas->kelas?->kelas) . ' tidak valid.');

            $ujianKelas->update(['id_guru_pengampu' => $idGuru]);
            $disimpan++;
        }

        return back()->with('success', $disimpan > 0 ? "{$disimpan} guru pengampu disimpan." : 'Tidak ada perubahan pengampu.');
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
     * Buka kembali ujian yg sudah ditutup — balik ke 'draft', BUKAN langsung 'published'.
     * Ini sengaja disamakan dgn syarat draft yg sudah ada di UjianSoalController (soal cuma
     * bisa diedit saat draft), jadi satu transisi status ini otomatis membuka edit soal lagi
     * tanpa perlu ubah guard di sana. Data attempt/jawaban/skor lama TIDAK disentuh — cuma
     * mengunci siswa mulai baru sampai admin/guru terbitkan ulang lewat publish().
     */
    public function reopen(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        abort_unless($ujian->isClosed(), 422, 'Hanya ujian yang sudah ditutup yang bisa dibuka kembali.');

        $ujian->update(['status' => 'draft']);

        return back()->with('success', 'Ujian dibuka kembali sebagai draf — soal bisa diedit lagi, siswa belum bisa mulai sampai diterbitkan ulang.');
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
    /**
     * Roster-driven (bukan cuma attempt yg submitted/dinilai) — SEMUA siswa kelas ter-assign
     * tampil (belum mulai/sedang mengerjakan/terkumpul/dinilai), sesuai konvensi laporan resmi
     * sekolah sendiri (lihat Excel Analisis Fase 6, siswa yg absen tetap dapat baris "-").
     * Dukung filter ?kelas=uuid utk ujian multi-kelas (PTS/PAS lintas tingkat).
     */
    public function hasil(Request $request, Ujian $ujian, UjianRoster $rosterService)
    {
        $this->authorize('manage', $ujian);

        $ujianKelasList = $ujian->kelas()->with('kelas')->get();
        $kelasFilter = $request->string('kelas')->toString();
        $untukRoster = $kelasFilter ? $ujianKelasList->where('id_kelas', $kelasFilter)->values() : $ujianKelasList;

        $roster = $rosterService->untukKelas($untukRoster);

        // Dihitung SEKALI di sini (bukan per-baris di view) supaya skorSementara() tak N+1
        // ke ujian_soal utk tiap siswa di roster — lihat UjianAttempt::skorSementara().
        $totalPoin = (int) $ujian->soal->sum(fn ($s) => $s->poinEfektif());
        $modeSkor = $ujian->pelajaran?->mode_skor_ujian ?? 'rata_rata';

        return view('ujian.hasil.index', compact('ujian', 'ujianKelasList', 'roster', 'kelasFilter', 'totalPoin', 'modeSkor'));
    }

    /** Rincian jawaban per-soal satu siswa — dipakai dari baris roster Hasil & Transfer/Pemantauan. */
    public function hasilDetail(Request $request, Ujian $ujian, User $siswa)
    {
        $this->authorize('manage', $ujian);

        $idUjianKelas = $ujian->kelas()->pluck('uuid');
        $attempt = UjianAttempt::whereIn('id_ujian_kelas', $idUjianKelas)
            ->where('id_siswa', $siswa->uuid)
            ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
            ->latest('created_at')
            ->first();

        $siswaProfil = Siswa::where('id_login', $siswa->uuid)->first();
        $jawabanBySoal = collect();
        $totalPoin = 0;
        $modeSkor = 'rata_rata';

        if ($attempt) {
            $ujian->load(['soal' => fn ($q) => $q->orderBy('urutan'), 'soal.opsi']);
            $jawabanBySoal = UjianJawaban::where('id_attempt', $attempt->uuid)->get()->keyBy('id_soal');
            $totalPoin = (int) $ujian->soal->sum(fn ($s) => $s->poinEfektif());
            $modeSkor = $ujian->pelajaran?->mode_skor_ujian ?? 'rata_rata';
        }

        return view('ujian.hasil.detail', compact('ujian', 'attempt', 'siswaProfil', 'jawabanBySoal', 'totalPoin', 'modeSkor'));
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

    public function paksaSelesai(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        $this->authorize('manage', $ujian);
        abort_unless($attempt->ujianKelas->id_ujian === $ujian->uuid, 404);
        abort_unless($attempt->status === UjianAttempt::STATUS_IN_PROGRESS, 422, 'Siswa tidak sedang mengerjakan.');

        $grader = app(\App\Services\UjianGrader::class);
        $grader->autoSubmitKarenaWaktuHabis($attempt);

        UjianPelanggaran::create(['id_attempt' => $attempt->uuid, 'id_siswa' => $attempt->id_siswa, 'tipe' => 'diselesaikan_paksa_admin']);

        return back()->with('success', 'Ujian siswa berhasil diselesaikan secara paksa.');
    }

    /**
     * "Buka Kembali Akses" utk attempt yg SUDAH SELESAI (submitted/dinilai) dari halaman
     * Hasil & Transfer — BEDA dari "Reset Ulang" di Pemantauan Live
     * (UjianMonitorController::resetAttempt(), utk attempt in_progress/terkunci yg soft-cancel
     * & mulai dari NOL krn kegagalan teknis). Di sini attempt yg SAMA dibuka kembali ke
     * in_progress — jawaban yg sudah tersimpan TETAP ADA (tidak disentuh sama sekali), siswa
     * lanjut dari titik terakhir dgn waktu pengerjaan baru. wajib_token_ulang=true SENGAJA
     * dipasang — demi keamanan, siswa TETAP harus masukkan token yg benar lagi lewat gate()
     * sebelum bisa melanjutkan (bukan langsung diarahkan ke kerjakan()), supaya reopening
     * tak bisa "diam-diam" dipakai tanpa sepengetahuan pengawas/guru yg memegang token.
     * Nilai yg sudah tertransfer ke buku nilai dihapus dulu (UjianNilaiTransfer::revert())
     * krn nilai itu sudah tak valid lagi setelah siswa bisa mengubah jawabannya.
     */
    public function bukaAksesSelesai(Request $request, Ujian $ujian, UjianAttempt $attempt, UjianNilaiTransfer $transfer)
    {
        $this->authorize('manage', $ujian);
        abort_unless($attempt->ujianKelas->id_ujian === $ujian->uuid, 404);
        abort_unless(in_array($attempt->status, [UjianAttempt::STATUS_SUBMITTED, UjianAttempt::STATUS_DINILAI], true), 422, 'Attempt ini belum selesai atau sudah dibuka kembali.');

        $nilaiIkutTerhapus = $attempt->status_transfer_nilai === 'berhasil';

        DB::transaction(function () use ($attempt, $ujian, $transfer, $nilaiIkutTerhapus) {
            if ($nilaiIkutTerhapus) {
                $transfer->revert($attempt);
            }
            $attempt->update([
                'status'                 => UjianAttempt::STATUS_IN_PROGRESS,
                'dikunci'                => false,
                'wajib_token_ulang'      => true,
                'batas_waktu_pada'       => now()->addMinutes($ujian->durasi_menit),
                'selesai_pada'           => null,
                'butuh_penilaian_manual' => false,
            ]);
            UjianPelanggaran::create(['id_attempt' => $attempt->uuid, 'id_siswa' => $attempt->id_siswa, 'tipe' => 'dibuka_kembali_admin']);
        });

        return back()->with('success', $nilaiIkutTerhapus
            ? 'Akses dibuka kembali & nilai yang sudah tertransfer ikut dihapus — jawaban siswa sebelumnya tetap tersimpan, siswa perlu masukkan token lagi utk melanjutkan.'
            : 'Akses dibuka kembali — jawaban siswa sebelumnya tetap tersimpan, siswa perlu masukkan token lagi utk melanjutkan.');
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
