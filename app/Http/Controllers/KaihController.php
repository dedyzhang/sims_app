<?php

namespace App\Http\Controllers;

use App\Models\KaihJawaban;
use App\Models\KaihJawabanDetail;
use App\Models\KaihOpsi;
use App\Models\KaihPertanyaan;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Support\KaihSiswa;
use Illuminate\Http\Request;

class KaihController extends Controller
{
    /** Kelas homeroom guru saat ini bila BUKAN admin/manage_kaih (null = boleh semua kelas). */
    private function walikelasKelasId(): ?string
    {
        return auth()->user()->canAccess('manage_kaih') ? null : auth()->user()->guru?->walikelas?->id_kelas;
    }

    private function ensureRekapAccess(): void
    {
        $boleh = auth()->user()->canAccess('manage_kaih') || auth()->user()->guru?->walikelas;
        abort_unless($boleh, 403, 'Akses ditolak.');
    }

    private function ensureSoalAccess(): void
    {
        abort_unless(auth()->user()->canAccess('manage_kaih'), 403, 'Hanya admin/kurikulum yang bisa mengubah soal 7 KAIH.');
    }

    // ───────────────────────── Isi harian (siswa) ─────────────────────────

    public function isi(Request $request)
    {
        $siswa = auth()->user()->siswa;
        abort_unless($siswa, 403, 'Halaman ini khusus siswa.');

        $tanggal = now()->toDateString();
        $existing = KaihJawaban::with('detail')->where('id_siswa', $siswa->uuid)->whereDate('tanggal', $tanggal)->first();
        $pertanyaans = KaihPertanyaan::with('opsi')->where('aktif', true)->orderBy('urutan')->get();

        return view('kaih.isi', [
            'pertanyaans' => $pertanyaans,
            'existing'    => $existing,
            'tanggal'     => $tanggal,
        ]);
    }

    public function simpan(Request $request)
    {
        $siswa = auth()->user()->siswa;
        abort_unless($siswa, 403, 'Halaman ini khusus siswa.');

        $tanggal = now()->toDateString();
        if (KaihJawaban::where('id_siswa', $siswa->uuid)->whereDate('tanggal', $tanggal)->exists()) {
            return back()->with('error', 'Kuesioner 7 KAIH hari ini sudah diisi.');
        }

        $pertanyaans = KaihPertanyaan::where('aktif', true)->orderBy('urutan')->get();
        $rules = ['refleksi' => 'required|string|max:1000'];
        foreach ($pertanyaans as $p) {
            $rules["jawaban.{$p->uuid}"] = 'required|uuid';
        }
        $data = $request->validate($rules);

        $this->simpanJawaban($siswa->uuid, $tanggal, $pertanyaans, $data['jawaban'], null, null, $data['refleksi']);

        return redirect()->route('kaih.isi')->with('success', 'Kuesioner 7 KAIH hari ini berhasil disimpan. Kamu sudah bisa absen.');
    }

    /** Simpan header+detail jawaban. $diisiOleh null = siswa isi sendiri, terisi = override admin/walikelas. */
    private function simpanJawaban(string $idSiswa, string $tanggal, $pertanyaans, array $jawabanOpsi, ?string $diisiOleh, ?string $keterangan = null, ?string $refleksi = null): KaihJawaban
    {
        $totalSkor = 0;
        $detailData = [];
        foreach ($pertanyaans as $p) {
            $opsiUuid = $jawabanOpsi[$p->uuid] ?? null;
            $opsi = $opsiUuid ? KaihOpsi::where('uuid', $opsiUuid)->where('id_pertanyaan', $p->uuid)->first() : null;
            if (!$opsi) {
                abort(422, "Jawaban untuk pertanyaan \"{$p->kebiasaan}\" tidak valid.");
            }
            $totalSkor += $opsi->bobot;
            $detailData[] = ['id_pertanyaan' => $p->uuid, 'id_opsi' => $opsi->uuid, 'bobot' => $opsi->bobot];
        }

        $jawaban = KaihJawaban::create([
            'id_siswa'    => $idSiswa,
            'tanggal'     => $tanggal,
            'total_skor'  => $totalSkor,
            'refleksi'    => $refleksi,
            'status'      => 'diisi',
            'diisi_oleh'  => $diisiOleh,
            'keterangan'  => $keterangan,
        ]);
        foreach ($detailData as $d) {
            KaihJawabanDetail::create(array_merge($d, ['id_jawaban' => $jawaban->uuid]));
        }

        return $jawaban;
    }

    // ───────────────────────── Rekap (walikelas/admin) ─────────────────────────

    public function rekap(Request $request)
    {
        $this->ensureRekapAccess();

        $walikelasKelas = $this->walikelasKelasId();

        $kelasList = Kelas::when($walikelasKelas, fn ($q) => $q->where('uuid', $walikelasKelas))
            ->orderBy('tingkat')->orderBy('kelas')->get();

        $selectedKelas = $walikelasKelas ?: ($request->query('kelas') ?: $kelasList->first()?->uuid);
        $tampilan = $request->query('tampilan') === 'rentang' ? 'rentang' : 'harian';
        $maxSkor = KaihPertanyaan::where('aktif', true)->count() * 4;

        $siswas = collect();
        if ($selectedKelas) {
            $siswas = Siswa::where('id_kelas', $selectedKelas)->orderBy('nama')->get();
        }

        if ($tampilan === 'rentang') {
            $dari   = $request->query('dari')   ?: now()->startOfMonth()->toDateString();
            $sampai = $request->query('sampai') ?: now()->toDateString();
            if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];

            $dates = AbsensiController::dateRange($dari, $sampai);

            $jawabans = KaihJawaban::with(['detail.pertanyaan', 'detail.opsi'])
                ->whereIn('id_siswa', $siswas->pluck('uuid'))
                ->whereDate('tanggal', '>=', $dari)
                ->whereDate('tanggal', '<=', $sampai)
                ->get()->groupBy('id_siswa');

            $rekap = $siswas->map(function ($s) use ($jawabans) {
                $rows = $jawabans->get($s->uuid, collect());
                $diisi = $rows->where('status', 'diisi');
                return [
                    'siswa'     => $s,
                    'diisi'     => $diisi->count(),
                    'dilewati'  => $rows->where('status', 'dilewati')->count(),
                    'totalSkor' => $diisi->sum('total_skor'),
                    'rataRata'  => $diisi->count() ? round($diisi->avg('total_skor'), 1) : 0,
                    'byDate'    => $rows->keyBy(fn ($r) => $r->tanggal->format('Y-m-d')),
                ];
            });

            return view('kaih.rekap', compact('kelasList', 'selectedKelas', 'tampilan', 'dari', 'sampai', 'dates', 'rekap', 'maxSkor', 'siswas'));
        }

        // ── Per hari ──
        $tanggal = $request->query('tanggal') ?: now()->toDateString();
        $jawabanHarian = KaihJawaban::with(['detail.pertanyaan', 'detail.opsi'])
            ->whereIn('id_siswa', $siswas->pluck('uuid'))
            ->whereDate('tanggal', $tanggal)
            ->get()->keyBy('id_siswa');

        return view('kaih.rekap', compact('kelasList', 'selectedKelas', 'tampilan', 'tanggal', 'siswas', 'jawabanHarian', 'maxSkor'));
    }

    /** Form override (isi manual / tandai dilewati) untuk 1 siswa pada 1 tanggal. */
    public function overrideForm(Request $request, Siswa $siswa)
    {
        $this->ensureRekapAccess();
        $this->ensureSiswaInScope($siswa);

        $tanggal  = $request->query('tanggal') ?: now()->toDateString();
        $dari     = $request->query('dari');
        $sampai   = $request->query('sampai');
        $tampilan = $request->query('tampilan', 'harian');
        if (KaihJawaban::where('id_siswa', $siswa->uuid)->whereDate('tanggal', $tanggal)->exists()) {
            return back()->with('error', 'Kuesioner 7 KAIH siswa ini pada tanggal tsb sudah ada datanya.');
        }

        $pertanyaans = KaihPertanyaan::with('opsi')->where('aktif', true)->orderBy('urutan')->get();

        return view('kaih.override', compact('siswa', 'pertanyaans', 'tanggal', 'dari', 'sampai', 'tampilan'));
    }

    public function overrideStore(Request $request, Siswa $siswa)
    {
        $this->ensureRekapAccess();
        $this->ensureSiswaInScope($siswa);

        $tanggal  = $request->input('tanggal') ?: now()->toDateString();
        $tampilan = $request->input('tampilan', 'harian');
        $rekapParams = array_filter([
            'kelas'    => $siswa->id_kelas,
            'tampilan' => $tampilan,
            'dari'     => $tampilan === 'rentang' ? $request->input('dari') : null,
            'sampai'   => $tampilan === 'rentang' ? $request->input('sampai') : null,
            'tanggal'  => $tampilan === 'harian' ? $tanggal : null,
        ]);
        if (KaihJawaban::where('id_siswa', $siswa->uuid)->whereDate('tanggal', $tanggal)->exists()) {
            return back()->with('error', 'Kuesioner 7 KAIH siswa ini pada tanggal tsb sudah ada datanya.');
        }

        if ($request->input('aksi') === 'lewati') {
            $request->validate(['keterangan' => 'required|string|max:255']);
            KaihJawaban::create([
                'id_siswa'   => $siswa->uuid,
                'tanggal'    => $tanggal,
                'total_skor' => 0,
                'status'     => 'dilewati',
                'diisi_oleh' => auth()->id(),
                'keterangan' => $request->input('keterangan'),
            ]);
            return redirect()->route('kaih.rekap', $rekapParams)
                ->with('success', "Kuesioner 7 KAIH {$siswa->nama} ditandai dilewati.");
        }

        $pertanyaans = KaihPertanyaan::where('aktif', true)->orderBy('urutan')->get();
        $rules = ['refleksi' => 'required|string|max:1000'];
        foreach ($pertanyaans as $p) {
            $rules["jawaban.{$p->uuid}"] = 'required|uuid';
        }
        $data = $request->validate($rules);
        $this->simpanJawaban($siswa->uuid, $tanggal, $pertanyaans, $data['jawaban'], auth()->id(), 'Diisi manual oleh ' . auth()->user()->displayName(), $data['refleksi']);

        return redirect()->route('kaih.rekap', $rekapParams)
            ->with('success', "Kuesioner 7 KAIH {$siswa->nama} berhasil diisi manual.");
    }

    private function ensureSiswaInScope(Siswa $siswa): void
    {
        $walikelasKelas = $this->walikelasKelasId();
        abort_if($walikelasKelas && $siswa->id_kelas !== $walikelasKelas, 403, 'Siswa ini bukan di kelas Anda.');
    }

    // ───────────────────────── Master soal (admin/kurikulum) ─────────────────────────

    public function soal()
    {
        $this->ensureSoalAccess();
        $pertanyaans = KaihPertanyaan::with('opsi')->withCount('jawabanDetail')->orderBy('urutan')->get();
        $aktif = KaihSiswa::wajibSebelumAbsen();

        return view('kaih.soal', compact('pertanyaans', 'aktif'));
    }

    /** Nyalakan/matikan gating 7 KAIH sebelum absen (admin/kurikulum). */
    public function toggleAktif(Request $request)
    {
        $this->ensureSoalAccess();
        Setting::set('kaih_wajib_sebelum_absen', $request->boolean('aktif') ? '1' : '0');

        return back()->with('success', $request->boolean('aktif')
            ? 'Fitur 7 KAIH diaktifkan — siswa wajib isi sebelum absen.'
            : 'Fitur 7 KAIH dinonaktifkan — siswa bisa langsung absen tanpa isi kuesioner.');
    }

    public function soalUpdate(Request $request, KaihPertanyaan $pertanyaan)
    {
        $this->ensureSoalAccess();
        $data = $request->validate([
            'kebiasaan'   => 'required|string|max:100',
            'pertanyaan'  => 'required|string|max:500',
            'aktif'       => 'nullable|boolean',
        ]);
        $data['aktif'] = $request->boolean('aktif');
        $pertanyaan->update($data);

        return back()->with('success', 'Pertanyaan berhasil diperbarui.');
    }

    public function soalStore(Request $request)
    {
        $this->ensureSoalAccess();
        $data = $request->validate([
            'kebiasaan'  => 'required|string|max:100',
            'pertanyaan' => 'required|string|max:500',
        ]);
        $data['urutan'] = (int) KaihPertanyaan::max('urutan') + 1;
        $data['aktif'] = true;
        $pertanyaan = KaihPertanyaan::create($data);

        // Bekali 2 opsi awal (bobot tertinggi & terendah) supaya langsung bisa dipakai; admin tinggal sesuaikan/tambah.
        KaihOpsi::create(['id_pertanyaan' => $pertanyaan->uuid, 'label' => 'Ya / Baik', 'bobot' => 4, 'urutan' => 0]);
        KaihOpsi::create(['id_pertanyaan' => $pertanyaan->uuid, 'label' => 'Tidak / Kurang', 'bobot' => 1, 'urutan' => 1]);

        return back()->with('success', 'Pertanyaan baru berhasil ditambahkan. Sesuaikan opsi jawabannya di bawah.');
    }

    public function soalDestroy(KaihPertanyaan $pertanyaan)
    {
        $this->ensureSoalAccess();
        $sudahDipakai = \App\Models\KaihJawabanDetail::where('id_pertanyaan', $pertanyaan->uuid)->exists();
        if ($sudahDipakai) {
            return back()->with('error', 'Pertanyaan ini sudah pernah dijawab siswa, tidak bisa dihapus. Nonaktifkan saja lewat centang "Aktif".');
        }
        $pertanyaan->delete();

        return back()->with('success', 'Pertanyaan berhasil dihapus.');
    }

    public function opsiStore(Request $request, KaihPertanyaan $pertanyaan)
    {
        $this->ensureSoalAccess();
        $data = $request->validate([
            'label' => 'required|string|max:150',
            'bobot' => 'required|integer|min:1|max:4',
        ]);
        $data['id_pertanyaan'] = $pertanyaan->uuid;
        $data['urutan'] = $pertanyaan->opsi()->max('urutan') + 1;
        KaihOpsi::create($data);

        return back()->with('success', 'Opsi jawaban berhasil ditambahkan.');
    }

    public function opsiUpdate(Request $request, KaihOpsi $opsi)
    {
        $this->ensureSoalAccess();
        $data = $request->validate([
            'label' => 'required|string|max:150',
            'bobot' => 'required|integer|min:1|max:4',
        ]);
        $opsi->update($data);

        return back()->with('success', 'Opsi jawaban berhasil diperbarui.');
    }

    public function opsiDestroy(KaihOpsi $opsi)
    {
        $this->ensureSoalAccess();
        abort_if($opsi->pertanyaan->opsi()->count() <= 2, 422, 'Minimal 2 opsi jawaban per pertanyaan.');
        $opsi->delete();

        return back()->with('success', 'Opsi jawaban dihapus.');
    }
}
