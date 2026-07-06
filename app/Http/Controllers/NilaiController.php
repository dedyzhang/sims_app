<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Materi;
use App\Models\Ngajar;
use App\Models\NilaiFormatif;
use App\Models\NilaiPas;
use App\Models\NilaiPenjabaran;
use App\Models\NilaiPts;
use App\Models\NilaiRapor;
use App\Models\PenjabaranKomponen;
use App\Models\NilaiSumatif;
use App\Models\RaporKonfirmasi;
use App\Models\Semester;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\TujuanPembelajaran;
use App\Support\Penilaian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NilaiController extends Controller
{
    /** Semester aktif (model Semester). */
    private function semester(): ?Semester
    {
        return Semester::aktif() ?? Semester::first();
    }

    private function bisaEdit(Ngajar $ngajar): bool
    {
        $user = auth()->user();
        if ($user->canAccess('edit_all_nilai')) return true;
        return (bool) ($user->guru && $ngajar->id_guru === $user->guru->uuid);
    }

    /** Wali kelas boleh LIHAT (read-only) nilai mapel lain di kelasnya, bila diizinkan admin lewat Setting. */
    private function walikelasBolehLihat(Ngajar $ngajar): bool
    {
        if (Setting::get('walikelas_lihat_nilai', '0') !== '1') return false;
        $idKelasWali = auth()->user()->guru?->walikelas?->id_kelas;
        return $idKelasWali && $idKelasWali === $ngajar->id_kelas;
    }

    private function ngajarOrAbort(string $uuid, bool $write = true): Ngajar
    {
        $ngajar = Ngajar::with(['pelajaran', 'kelas', 'guru'])->findOrFail($uuid);
        if ($this->bisaEdit($ngajar)) return $ngajar;
        $user = auth()->user();
        if (!$write && $user->canAccess('view_all_nilai')) return $ngajar;
        abort_unless(!$write && $this->walikelasBolehLihat($ngajar), 403, 'Anda tidak mengajar kelas/mapel ini.');
        return $ngajar;
    }

    /** Siswa satu kelas (urut nama). */
    private function siswaKelas(string $idKelas)
    {
        return Siswa::where('id_kelas', $idKelas)->orderBy('nama')->get();
    }

    /** Apakah rapor penugasan ini (semester aktif) sudah dikonfirmasi/terkunci? */
    private function terkunci(Ngajar $ngajar): bool
    {
        return RaporKonfirmasi::where('id_ngajar', $ngajar->uuid)
            ->where('id_semester', $this->semester()?->id)->exists();
    }

    /** Tolak perubahan bila rapor sudah dikonfirmasi (terkunci). */
    private function lockGuard(Ngajar $ngajar): void
    {
        abort_if($this->terkunci($ngajar), 423, 'Nilai sudah dikonfirmasi dan terkunci. Batalkan konfirmasi dulu untuk mengubah.');
    }

    /** ====== Daftar penugasan (ngajar) yang bisa dinilai ====== */
    public function index()
    {
        $user = auth()->user();
        $q = Ngajar::with(['pelajaran', 'kelas', 'guru'])
            ->whereNotNull('id_guru')->whereNotNull('id_pelajaran')->whereNotNull('id_kelas');

        if (!$user->canAccess('view_all_nilai')) {
            $guru = $user->guru;
            $q->where('id_guru', $guru?->uuid ?? '-');
        }

        $ngajars = $q->get()->sortBy(fn ($n) => [$n->pelajaran?->urutan, $n->pelajaran?->nama, $n->kelas?->tingkat, $n->kelas?->kelas])->values();

        return view('nilai.index', [
            'ngajars'  => $ngajars,
            'semester' => $this->semester(),
            'isAdmin'  => $user->isAdmin(),
        ]);
    }

    /** ====== Daftar mapel di kelas wali kelas (read-only, dipakai halaman Wali Kelas) ====== */
    public function walikelasNilaiIndex()
    {
        $kelas = auth()->user()->guru?->walikelas?->kelas;
        abort_unless($kelas, 403, 'Anda bukan wali kelas.');
        abort_unless(Setting::get('walikelas_lihat_nilai', '0') === '1', 403, 'Fitur lihat nilai kelas belum diaktifkan admin.');

        $ngajars = Ngajar::with(['pelajaran', 'kelas', 'guru'])
            ->where('id_kelas', $kelas->uuid)
            ->whereNotNull('id_guru')->whereNotNull('id_pelajaran')
            ->get()->sortBy(fn ($n) => [$n->pelajaran?->urutan, $n->pelajaran?->nama])->values();
        foreach ($ngajars as $n) { $n->mine = $this->bisaEdit($n); }

        return view('walikelas.nilai.index', [
            'kelas'    => $kelas,
            'ngajars'  => $ngajars,
            'semester' => $this->semester(),
        ]);
    }

    /** ====== Nilai Saya: siswa (atau orangtuanya) melihat daftar nilai formatif & sumatif sendiri ====== */
    public function selfShow()
    {
        $u = auth()->user();
        $siswa = $u->siswa ?: \App\Models\Orangtua::where('id_login', $u->uuid)->first()?->siswa;
        abort_unless($siswa, 404);

        $semester = $this->semester();

        $ngajars = Ngajar::where('id_kelas', $siswa->id_kelas)
            ->whereNotNull('id_pelajaran')->whereNotNull('id_guru')
            ->with(['pelajaran', 'guru'])->get()
            ->filter(fn ($n) => $n->pelajaran)
            ->sortBy(fn ($n) => [$n->pelajaran->urutan, $n->pelajaran->nama])
            ->values();

        $mapel = $ngajars->map(function ($ngajar) use ($siswa, $semester) {
            $materi = Materi::with('tujuan')->where('id_ngajar', $ngajar->uuid)
                ->where('id_semester', $semester?->id)->where('aktif', true)
                ->orderBy('urutan')->get();
            $tupeAll = $materi->flatMap(fn ($m) => $m->tujuan);

            $fmtRows = NilaiFormatif::where('id_siswa', $siswa->uuid)
                ->whereIn('id_tupe', $tupeAll->pluck('uuid'))->get()->keyBy('id_tupe');
            $sumRows = NilaiSumatif::where('id_siswa', $siswa->uuid)
                ->whereIn('id_materi', $materi->pluck('uuid'))->get()->keyBy('id_materi');

            return [
                'ngajar'  => $ngajar,
                'materi'  => $materi,
                'fmtRows' => $fmtRows,
                'sumRows' => $sumRows,
            ];
        });

        return view('nilai.self', compact('siswa', 'semester', 'mapel'));
    }

    /** ====== KKTP (dulu KKM) per penugasan ====== */
    public function kktp()
    {
        $user = auth()->user();
        $q = Ngajar::with(['pelajaran', 'kelas', 'guru'])
            ->whereNotNull('id_guru')->whereNotNull('id_pelajaran')->whereNotNull('id_kelas');
        if (!$user->canAccess('view_all_nilai')) {
            $q->where('id_guru', $user->guru?->uuid ?? '-');
        }
        $ngajars = $q->get()->sortBy(fn ($n) => [$n->pelajaran?->urutan, $n->pelajaran?->nama, $n->kelas?->tingkat, $n->kelas?->kelas])->values();

        return view('nilai.kktp', ['ngajars' => $ngajars, 'isAdmin' => $user->isAdmin()]);
    }

    public function kktpSave(Request $request)
    {
        $data = $request->validate([
            'kkm'   => 'array',
            'kkm.*' => 'nullable|integer|min:0|max:100',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['kkm'] ?? [] as $ngajarUuid => $val) {
                $ngajar = Ngajar::find($ngajarUuid);
                if (!$ngajar) continue;
                // hak akses: admin semua, guru hanya miliknya
                $this->lockGuard($this->ngajarOrAbort($ngajar->uuid));
                $ngajar->update(['kkm' => ($val === null || $val === '') ? null : (int) $val]);
            }
        });

        return back()->with('success', 'KKTP berhasil disimpan.');
    }

    /** ====== Materi + Tujuan Pembelajaran ====== */
    public function materi(string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid, write: false);
        $readOnly = !$this->bisaEdit($ngajar);
        $sem = $this->semester();
        $materi = Materi::with('tujuan')
            ->where('id_ngajar', $uuid)->where('id_semester', $sem?->id)
            ->orderBy('urutan')->orderBy('created_at')->get();

        // Get other teaching assignments by same teacher, same subject, same grade level
        $otherNgajars = Ngajar::with('kelas')
            ->where('id_guru', $ngajar->id_guru)
            ->where('id_pelajaran', $ngajar->id_pelajaran)
            ->where('uuid', '!=', $ngajar->uuid)
            ->whereHas('kelas', function($q) use ($ngajar) {
                $q->where('tingkat', $ngajar->kelas?->tingkat);
            })
            ->get();

        $terkunci = $this->terkunci($ngajar);
        $tpMin = (int) Setting::get('tp_min', 0);
        $tpMax = (int) Setting::get('tp_max', 0);
        return view('nilai.materi', compact('ngajar', 'materi', 'sem', 'otherNgajars', 'terkunci', 'tpMin', 'tpMax', 'readOnly'));
    }

    public function duplicateMateri(Request $request, string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid);
        $sem = $this->semester();

        $request->validate([
            'target_ngajar_ids' => 'required|array',
            'target_ngajar_ids.*' => 'required|string|exists:ngajars,uuid',
            'materi_ids' => 'required|array',
            'materi_ids.*' => 'required|string|exists:materi,uuid'
        ]);

        $targetIds = $request->input('target_ngajar_ids');
        $materiIds = $request->input('materi_ids');

        DB::transaction(function () use ($ngajar, $sem, $targetIds, $materiIds) {
            // Get selected source materials with objectives
            $sourceMateris = Materi::with('tujuan')
                ->whereIn('uuid', $materiIds)
                ->where('id_ngajar', $ngajar->uuid)
                ->where('id_semester', $sem?->id)
                ->get();

            foreach ($targetIds as $targetId) {
                $targetNgajar = Ngajar::findOrFail($targetId);
                
                // Security and sanity checks:
                // 1. Same teacher (unless user is admin)
                if (!auth()->user()->isAdmin() && $targetNgajar->id_guru !== auth()->user()->guru?->uuid) {
                    abort(403, 'Akses ditolak.');
                }
                // 2. Same subject
                if ($targetNgajar->id_pelajaran !== $ngajar->id_pelajaran) {
                    abort(400, 'Mata pelajaran harus sama.');
                }
                // 3. Same grade level
                if ($targetNgajar->kelas?->tingkat !== $ngajar->kelas?->tingkat) {
                    abort(400, 'Tingkat kelas harus sama.');
                }
                // 4. Target tidak boleh terkunci (rapor sudah dikonfirmasi)
                $this->lockGuard($targetNgajar);

                // Copy each material and its objectives
                foreach ($sourceMateris as $sMateri) {
                    // Check if a material with the same name already exists in target
                    $exists = Materi::where('id_ngajar', $targetNgajar->uuid)
                        ->where('id_semester', $sem?->id)
                        ->where('nama', $sMateri->nama)
                        ->exists();
                    if ($exists) continue; // Skip if already exists to avoid exact duplicates

                    $newMateri = Materi::create([
                        'id_ngajar' => $targetNgajar->uuid,
                        'nama' => $sMateri->nama,
                        'id_semester' => $sem?->id,
                        'urutan' => (int) Materi::where('id_ngajar', $targetNgajar->uuid)->where('id_semester', $sem?->id)->max('urutan') + 1,
                        'aktif' => $sMateri->aktif,
                    ]);

                    foreach ($sMateri->tujuan as $sTupe) {
                        TujuanPembelajaran::create([
                            'id_materi' => $newMateri->uuid,
                            'tupe' => $sTupe->tupe,
                            'urutan' => $sTupe->urutan,
                            'aktif' => $sTupe->aktif,
                        ]);
                    }
                }
            }
        });

        return back()->with('success', 'Materi dan Tujuan Pembelajaran berhasil diduplikasi ke kelas target.');
    }

    public function materiStore(Request $request, string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid);
        $this->lockGuard($ngajar);
        $data = $request->validate(['nama' => 'required|string|max:150']);
        $sem = $this->semester();
        Materi::create([
            'id_ngajar'   => $ngajar->uuid,
            'nama'        => $data['nama'],
            'id_semester' => $sem?->id,
            'urutan'      => (int) Materi::where('id_ngajar', $ngajar->uuid)->where('id_semester', $sem?->id)->max('urutan') + 1,
            'aktif'       => true,
        ]);
        return back()->with('success', 'Materi ditambahkan.');
    }

    public function materiUpdate(Request $request, string $materi)
    {
        $m = Materi::findOrFail($materi);
        $this->lockGuard($this->ngajarOrAbort($m->id_ngajar));
        $data = $request->validate([
            'nama'  => 'required|string|max:150',
            'aktif' => 'nullable|boolean',
        ]);
        $m->update(['nama' => $data['nama'], 'aktif' => $request->boolean('aktif', $m->aktif)]);
        return back()->with('success', 'Materi diperbarui.');
    }

    public function materiToggle(string $materi)
    {
        $m = Materi::findOrFail($materi);
        $this->lockGuard($this->ngajarOrAbort($m->id_ngajar));
        $m->update(['aktif' => !$m->aktif]);
        return back()->with('success', $m->aktif ? 'Materi diaktifkan.' : 'Materi dinonaktifkan (tidak dihitung di rapor).');
    }

    public function materiDestroy(string $materi)
    {
        $m = Materi::findOrFail($materi);
        $this->lockGuard($this->ngajarOrAbort($m->id_ngajar));
        DB::transaction(function () use ($m) {
            $tupeIds = TujuanPembelajaran::where('id_materi', $m->uuid)->pluck('uuid');
            // hapus nilai formatif (per TP & per materi) + sumatif materi ini
            NilaiFormatif::where('id_materi', $m->uuid)->orWhereIn('id_tupe', $tupeIds)->delete();
            NilaiSumatif::where('id_materi', $m->uuid)->delete();
            TujuanPembelajaran::where('id_materi', $m->uuid)->delete();
            $m->delete();
        });
        return back()->with('success', 'Materi dihapus.');
    }

    public function tupeStore(Request $request, string $materi)
    {
        $m = Materi::findOrFail($materi);
        $this->lockGuard($this->ngajarOrAbort($m->id_ngajar));
        $data = $request->validate(['tupe' => 'required|string|max:500']);
        $max = (int) Setting::get('tp_max', 0);
        if ($max > 0 && TujuanPembelajaran::where('id_materi', $m->uuid)->count() >= $max) {
            return back()->with('error', "Maksimal {$max} Tujuan Pembelajaran per materi.");
        }
        TujuanPembelajaran::create([
            'id_materi' => $m->uuid,
            'tupe'      => $data['tupe'],
            'urutan'    => (int) TujuanPembelajaran::where('id_materi', $m->uuid)->max('urutan') + 1,
            'aktif'     => true,
        ]);
        return back()->with('success', 'Tujuan Pembelajaran ditambahkan.');
    }

    public function tupeUpdate(Request $request, string $tupe)
    {
        $t = TujuanPembelajaran::findOrFail($tupe);
        $m = Materi::findOrFail($t->id_materi);
        $this->lockGuard($this->ngajarOrAbort($m->id_ngajar));
        $data = $request->validate(['tupe' => 'required|string|max:500']);
        $t->update(['tupe' => $data['tupe']]);
        return back()->with('success', 'TP diperbarui.');
    }

    public function tupeDestroy(string $tupe)
    {
        $t = TujuanPembelajaran::findOrFail($tupe);
        $m = Materi::findOrFail($t->id_materi);
        $this->lockGuard($this->ngajarOrAbort($m->id_ngajar));
        $min = (int) Setting::get('tp_min', 0);
        if ($min > 0 && TujuanPembelajaran::where('id_materi', $m->uuid)->count() <= $min) {
            return back()->with('error', "Minimal {$min} Tujuan Pembelajaran per materi — tidak bisa dihapus.");
        }
        DB::transaction(function () use ($t) {
            NilaiFormatif::where('id_tupe', $t->uuid)->delete();   // nilai formatif TP ini
            $t->delete();
        });
        return back()->with('success', 'TP dihapus beserta nilai formatifnya.');
    }

    /** ====== Formatif: grid siswa × TP ====== */
    public function formatif(string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid, write: false);
        $readOnly = !$this->bisaEdit($ngajar);
        $sem = $this->semester();
        $materi = Materi::with('tujuan')
            ->where('id_ngajar', $uuid)->where('id_semester', $sem?->id)
            ->orderBy('urutan')->get();
        $siswas = $this->siswaKelas($ngajar->id_kelas);

        $tupeIds = $materi->flatMap(fn ($m) => $m->tujuan->pluck('uuid'))->all();
        $skor = []; // [tupe][siswa] => nilai
        foreach (NilaiFormatif::whereIn('id_tupe', $tupeIds)->get() as $r) {
            $skor[$r->id_tupe][$r->id_siswa] = $r->nilai;
        }

        $kktp = $ngajar->kktp;
        $terkunci = $this->terkunci($ngajar);
        return view('nilai.formatif', compact('ngajar', 'materi', 'siswas', 'skor', 'sem', 'kktp', 'terkunci', 'readOnly'));
    }

    /** ====== Sumatif: grid siswa × materi ====== */
    public function sumatif(string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid, write: false);
        $readOnly = !$this->bisaEdit($ngajar);
        $sem = $this->semester();
        $materi = Materi::with('tujuan')->where('id_ngajar', $uuid)->where('id_semester', $sem?->id)
            ->orderBy('urutan')->get();
        $siswas = $this->siswaKelas($ngajar->id_kelas);

        $skor = []; // [materi][siswa] => nilai
        foreach (NilaiSumatif::whereIn('id_materi', $materi->pluck('uuid'))->get() as $r) {
            $skor[$r->id_materi][$r->id_siswa] = $r->nilai;
        }

        $kktp = $ngajar->kktp;
        $terkunci = $this->terkunci($ngajar);
        return view('nilai.sumatif', compact('ngajar', 'materi', 'siswas', 'skor', 'sem', 'kktp', 'terkunci', 'readOnly'));
    }

    /** ====== PAS: 1 nilai per siswa ====== */
    public function pas(string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid, write: false);
        $readOnly = !$this->bisaEdit($ngajar);
        $sem = $this->semester();
        $siswas = $this->siswaKelas($ngajar->id_kelas);
        $skor = NilaiPas::where('id_ngajar', $uuid)->where('id_semester', $sem?->id)
            ->pluck('nilai', 'id_siswa')->toArray();

        $kktp = $ngajar->kktp;
        $terkunci = $this->terkunci($ngajar);
        return view('nilai.pas', compact('ngajar', 'siswas', 'skor', 'sem', 'kktp', 'terkunci', 'readOnly'));
    }

    // ====== Simpan per-sel (AJAX, input langsung di tabel) ======

    /** Bersihkan & batasi nilai 0-100 (bilangan bulat); null bila kosong. */
    private function nilaiBersih(mixed $v): ?int
    {
        if ($v === null || $v === '') return null;
        return (int) max(0, min(100, round((float) $v)));
    }

    public function formatifCell(Request $request, string $uuid)
    {
        $this->lockGuard($this->ngajarOrAbort($uuid));
        $data = $request->validate([
            'id_tupe'  => 'required|exists:tujuan_pembelajaran,uuid',
            'id_siswa' => 'required|exists:siswa,uuid',
            'nilai'    => 'nullable|numeric|min:0|max:100',
        ]);
        $key = ['id_tupe' => $data['id_tupe'], 'id_siswa' => $data['id_siswa']];
        $nilai = $this->nilaiBersih($data['nilai'] ?? null);
        if ($nilai === null) {
            NilaiFormatif::where($key)->delete();
        } else {
            $idMateri = TujuanPembelajaran::where('uuid', $data['id_tupe'])->value('id_materi');
            NilaiFormatif::updateOrCreate($key, ['id_materi' => $idMateri, 'nilai' => $nilai]);
        }
        return response()->json(['ok' => true, 'nilai' => $nilai]);
    }

    public function sumatifCell(Request $request, string $uuid)
    {
        $this->lockGuard($this->ngajarOrAbort($uuid));
        $data = $request->validate([
            'id_materi' => 'required|exists:materi,uuid',
            'id_siswa'  => 'required|exists:siswa,uuid',
            'nilai'     => 'nullable|numeric|min:0|max:100',
        ]);
        $key = ['id_materi' => $data['id_materi'], 'id_siswa' => $data['id_siswa']];
        $nilai = $this->nilaiBersih($data['nilai'] ?? null);
        if ($nilai === null) {
            NilaiSumatif::where($key)->delete();
        } else {
            NilaiSumatif::updateOrCreate($key, ['nilai' => $nilai]);
        }
        return response()->json(['ok' => true, 'nilai' => $nilai]);
    }

    public function pasCell(Request $request, string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid);
        $this->lockGuard($ngajar);
        $data = $request->validate([
            'id_siswa' => 'required|exists:siswa,uuid',
            'nilai'    => 'nullable|numeric|min:0|max:100',
        ]);
        $sem = $this->semester();
        $key = ['id_ngajar' => $ngajar->uuid, 'id_siswa' => $data['id_siswa'], 'id_semester' => $sem?->id];
        $nilai = $this->nilaiBersih($data['nilai'] ?? null);
        if ($nilai === null) {
            NilaiPas::where($key)->delete();
        } else {
            NilaiPas::updateOrCreate($key, ['nilai' => $nilai]);
        }
        return response()->json(['ok' => true, 'nilai' => $nilai]);
    }

    /** ====== Penjabaran: grid siswa × komponen (komponen dikonfigurasi admin per mapel) ====== */
    public function penjabaran(string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid, write: false);
        $readOnly = !$this->bisaEdit($ngajar);
        $sem = $this->semester();
        $komponen = PenjabaranKomponen::where('id_pelajaran', $ngajar->id_pelajaran)->orderBy('urutan')->get();
        $siswas = $this->siswaKelas($ngajar->id_kelas);

        $skor = []; // [komponen][siswa] => nilai
        foreach (NilaiPenjabaran::whereIn('id_komponen', $komponen->pluck('uuid'))->where('id_semester', $sem?->id)->get() as $r) {
            $skor[$r->id_komponen][$r->id_siswa] = $r->nilai;
        }

        $kktp = $ngajar->kktp;
        $terkunci = $this->terkunci($ngajar);
        return view('nilai.penjabaran', compact('ngajar', 'komponen', 'siswas', 'skor', 'sem', 'kktp', 'terkunci', 'readOnly'));
    }

    public function penjabaranCell(Request $request, string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid);
        $this->lockGuard($ngajar);
        $data = $request->validate([
            'id_komponen' => 'required|exists:penjabaran_komponen,uuid',
            'id_siswa'    => 'required|exists:siswa,uuid',
            'nilai'       => 'nullable|numeric|min:0|max:100',
        ]);
        $sem = $this->semester();
        $key = ['id_siswa' => $data['id_siswa'], 'id_komponen' => $data['id_komponen'], 'id_semester' => $sem?->id];
        $nilai = $this->nilaiBersih($data['nilai'] ?? null);
        if ($nilai === null) {
            NilaiPenjabaran::where($key)->delete();
        } else {
            NilaiPenjabaran::updateOrCreate($key, ['id_ngajar' => $ngajar->uuid, 'nilai' => $nilai]);
        }
        return response()->json(['ok' => true, 'nilai' => $nilai]);
    }

    /** ====== PTS: 1 nilai per siswa (TIDAK masuk rumus rapor, hanya dicatat) ====== */
    public function pts(string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid, write: false);
        $readOnly = !$this->bisaEdit($ngajar);
        $sem = $this->semester();
        $siswas = $this->siswaKelas($ngajar->id_kelas);
        $skor = NilaiPts::where('id_ngajar', $uuid)->where('id_semester', $sem?->id)
            ->pluck('nilai', 'id_siswa')->toArray();
        $kktp = $ngajar->kktp;
        $terkunci = $this->terkunci($ngajar);
        return view('nilai.pts', compact('ngajar', 'siswas', 'skor', 'sem', 'kktp', 'terkunci', 'readOnly'));
    }

    public function ptsCell(Request $request, string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid);
        $this->lockGuard($ngajar);
        $data = $request->validate([
            'id_siswa' => 'required|exists:siswa,uuid',
            'nilai'    => 'nullable|numeric|min:0|max:100',
        ]);
        $sem = $this->semester();
        $key = ['id_ngajar' => $ngajar->uuid, 'id_siswa' => $data['id_siswa'], 'id_semester' => $sem?->id];
        $nilai = $this->nilaiBersih($data['nilai'] ?? null);
        if ($nilai === null) {
            NilaiPts::where($key)->delete();
        } else {
            NilaiPts::updateOrCreate($key, ['nilai' => $nilai]);
        }
        return response()->json(['ok' => true, 'nilai' => $nilai]);
    }

    /** Peta nilai rapor akhir per siswa (override bila ada, else hitung) — dipakai modul ekskul. */
    public function raporNilaiMap(Ngajar $ngajar, $siswas, ?Semester $sem, string $rumus): array
    {
        $materi = Materi::with('tujuan')->where('id_ngajar', $ngajar->uuid)
            ->where('id_semester', $sem?->id)->where('aktif', true)->get();
        $tupeIds = $materi->flatMap(fn ($m) => $m->tujuan)->pluck('uuid');
        $fmt = [];
        foreach (NilaiFormatif::whereIn('id_tupe', $tupeIds)->get() as $r) { $fmt[$r->id_siswa][] = (float) $r->nilai; }
        $sum = [];
        foreach (NilaiSumatif::whereIn('id_materi', $materi->pluck('uuid'))->get() as $r) { $sum[$r->id_siswa][] = (float) $r->nilai; }
        $pas = NilaiPas::where('id_ngajar', $ngajar->uuid)->where('id_semester', $sem?->id)->pluck('nilai', 'id_siswa')->toArray();
        $ov  = NilaiRapor::where('id_ngajar', $ngajar->uuid)->where('id_semester', $sem?->id)->pluck('nilai', 'id_siswa')->toArray();
        $out = [];
        foreach ($siswas as $s) {
            if (isset($ov[$s->uuid]) && $ov[$s->uuid] !== null) { $out[$s->uuid] = (int) $ov[$s->uuid]; continue; }
            $h = Penilaian::hitung($fmt[$s->uuid] ?? [], $sum[$s->uuid] ?? [], isset($pas[$s->uuid]) ? (float) $pas[$s->uuid] : null, $rumus);
            $out[$s->uuid] = $h['rapor'];
        }
        return $out;
    }

    /** ====== Rapor: hitung otomatis + deskripsi, bisa override & konfirmasi ====== */
    public function rapor(string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid, write: false);
        $readOnly = !$this->bisaEdit($ngajar);
        $sem = $this->semester();
        $rumus = Setting::get('rumus_rapor', 'bagi4');
        $kkm = $ngajar->kktp;
        $siswas = $this->siswaKelas($ngajar->id_kelas);

        // materi AKTIF + TP-nya
        $materi = Materi::with('tujuan')
            ->where('id_ngajar', $uuid)->where('id_semester', $sem?->id)
            ->where('aktif', true)->orderBy('urutan')->get();
        $tupeAll = $materi->flatMap(fn ($m) => $m->tujuan);   // semua TP materi aktif
        $tupeText = $tupeAll->pluck('tupe', 'uuid');
        $tupeList = $tupeAll->pluck('tupe')->filter()->values();   // utk dropdown deskripsi

        // ambil nilai
        $fmt = []; // [siswa][tupe] => nilai
        foreach (NilaiFormatif::whereIn('id_tupe', $tupeAll->pluck('uuid'))->get() as $r) {
            $fmt[$r->id_siswa][$r->id_tupe] = (float) $r->nilai;
        }
        $sum = []; // [siswa][materi] => nilai
        foreach (NilaiSumatif::whereIn('id_materi', $materi->pluck('uuid'))->get() as $r) {
            $sum[$r->id_siswa][$r->id_materi] = (float) $r->nilai;
        }
        $pas = NilaiPas::where('id_ngajar', $uuid)->where('id_semester', $sem?->id)
            ->pluck('nilai', 'id_siswa')->toArray();
        // PTS hanya info (tidak masuk rumus rapor)
        $ptsArr = NilaiPts::where('id_ngajar', $uuid)->where('id_semester', $sem?->id)
            ->pluck('nilai', 'id_siswa')->toArray();
        $final = NilaiRapor::where('id_ngajar', $uuid)->where('id_semester', $sem?->id)
            ->get()->keyBy('id_siswa');

        $baris = [];
        foreach ($siswas as $s) {
            $fList = array_values($fmt[$s->uuid] ?? []);
            $sList = array_values($sum[$s->uuid] ?? []);
            $pasVal = isset($pas[$s->uuid]) ? (float) $pas[$s->uuid] : null;
            $h = Penilaian::hitung($fList, $sList, $pasVal, $rumus);
            $pred = Penilaian::predikat($h['rapor'], $kkm);

            // deskripsi otomatis: TP tertinggi (positif) & terendah (negatif)
            $dPos = $dNeg = '';
            $skorTupe = $fmt[$s->uuid] ?? [];
            if (!empty($skorTupe)) {
                arsort($skorTupe);
                $maxTupe = array_key_first($skorTupe);
                asort($skorTupe);
                $minTupe = array_key_first($skorTupe);
                $predMax = Penilaian::predikat((int) round($skorTupe[$maxTupe]), $kkm);
                $dPos = Penilaian::kalimatPositif($predMax, (string) ($tupeText[$maxTupe] ?? ''));
                $dNeg = Penilaian::kalimatNegatif((string) ($tupeText[$minTupe] ?? ''));
            }

            $rowFinal = $final->get($s->uuid);
            $posStored = $rowFinal?->deskripsi_positif;
            $negStored = $rowFinal?->deskripsi_negatif;
            $baris[] = [
                'siswa'   => $s,
                'hitung'  => $h,
                'predikat' => $pred,
                'pts'     => isset($ptsArr[$s->uuid]) ? (int) $ptsArr[$s->uuid] : null,
                'desPos'  => $posStored ?? $dPos,
                'desPosAuto' => $dPos,
                'desPosOv'  => $posStored !== null,
                'desNeg'  => $negStored ?? $dNeg,
                'desNegAuto' => $dNeg,
                'desNegOv'  => $negStored !== null,
                'nilaiFinal' => $rowFinal->nilai ?? null,
            ];
        }

        return view('nilai.rapor', [
            'ngajar' => $ngajar, 'sem' => $sem, 'rumus' => $rumus, 'kktp' => $kkm,
            'baris' => $baris, 'rumusList' => Penilaian::RUMUS, 'tupeList' => $tupeList,
            'terkunci' => $this->terkunci($ngajar), 'readOnly' => $readOnly
        ]);
    }

    /** Ubah nilai rapor manual (AJAX). nilai null = kembalikan ke otomatis. */
    public function raporNilai(Request $request, string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid);
        $this->lockGuard($ngajar);
        $data = $request->validate([
            'id_siswa' => 'required|exists:siswa,uuid',
            'nilai'    => 'nullable|numeric|min:0|max:100',
        ]);
        $sem = $this->semester();
        $nilai = $this->nilaiBersih($data['nilai'] ?? null);
        NilaiRapor::updateOrCreate(
            ['id_ngajar' => $ngajar->uuid, 'id_siswa' => $data['id_siswa'], 'id_semester' => $sem?->id],
            ['nilai' => $nilai]
        );
        return response()->json(['ok' => true, 'nilai' => $nilai]);
    }

    /** Ubah deskripsi capaian (AJAX). */
    public function raporDesk(Request $request, string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid);
        $this->lockGuard($ngajar);
        $data = $request->validate([
            'id_siswa' => 'required|exists:siswa,uuid',
            'jenis'    => 'required|in:positif,negatif',
            'teks'     => 'nullable|string|max:1000',
        ]);
        $sem = $this->semester();
        $kolom = $data['jenis'] === 'positif' ? 'deskripsi_positif' : 'deskripsi_negatif';
        NilaiRapor::updateOrCreate(
            ['id_ngajar' => $ngajar->uuid, 'id_siswa' => $data['id_siswa'], 'id_semester' => $sem?->id],
            [$kolom => (($data['teks'] ?? '') === '' ? null : $data['teks'])]
        );
        return response()->json(['ok' => true]);
    }

    /** Konfirmasi rapor → kunci semua nilai penugasan ini (guru/admin). */
    public function raporKonfirmasi(string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid);
        $sem = $this->semester();
        RaporKonfirmasi::firstOrCreate(
            ['id_ngajar' => $ngajar->uuid, 'id_semester' => $sem?->id],
            ['dikonfirmasi_oleh' => auth()->id()]
        );
        return back()->with('success', 'Nilai rapor dikonfirmasi & dikunci. Untuk mengubah, batalkan konfirmasi dulu.');
    }

    /** Batalkan konfirmasi → buka kunci (HANYA admin). */
    public function raporBatalKonfirmasi(string $uuid)
    {
        $ngajar = $this->ngajarOrAbort($uuid);
        abort_unless(auth()->user()->isAdmin(), 403, 'Hanya admin yang bisa membatalkan konfirmasi.');
        RaporKonfirmasi::where('id_ngajar', $ngajar->uuid)
            ->where('id_semester', $this->semester()?->id)->delete();
        return back()->with('success', 'Konfirmasi dibatalkan. Nilai bisa diubah kembali.');
    }
}
