<?php

namespace App\Http\Controllers;

use App\Models\Jadwal;
use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\Walikelas;
use App\Support\Audit;
use App\Survei\AnalisisGuru;
use App\Survei\Kios;
use App\Survei\Periode;
use App\Survei\Pertanyaan;
use App\Survei\Respons;
use App\Survei\SafeExamBrowser;
use App\Survei\Sesi;
use App\Survei\Target;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SurveiPembelajaranController extends Controller
{
    public function __construct(private SafeExamBrowser $seb, private Kios $kios) {}

    private function admin(): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);
    }

    private function siswa()
    {
        $user = auth()->user();
        abort_unless($user->access === 'siswa', 403);
        $siswa = $user->siswa;

        abort_unless($siswa && $siswa->id_kelas, 403);

        return $siswa;
    }

    private function targetSiswa(Target $target): void
    {
        abort_unless($target->kelas_id === $this->siswa()->id_kelas, 403);
        abort_unless($target->periode && $target->periode->terbuka(), 403, 'Survei belum dibuka atau sudah berakhir.');
    }

    private function denganStatusJawaban(Builder $query, Siswa $siswa): Builder
    {
        return $query
            ->select(['uuid', 'periode_id', 'kelas_id', 'kelas_nama', 'jenis', 'guru_nama', 'pelajaran_id', 'pelajaran_nama'])
            ->withExists(['respons as sudah_diisi' => fn ($query) => $query->where('siswa_id', $siswa->uuid)])
            ->orderByRaw("CASE WHEN jenis = 'wali' THEN 1 ELSE 0 END")
            ->orderBy('pelajaran_nama');
    }

    private function targetDenganStatus(Periode $periode, Siswa $siswa): Collection
    {
        return $this->denganStatusJawaban(Target::where('periode_id', $periode->uuid), $siswa)->get();
    }

    private function periodeSudahDiisi(Collection $targets): bool
    {
        return $targets->groupBy('kelas_id')->contains(
            fn ($classTargets) => $classTargets->every(fn ($target) => $target->sudah_diisi)
        );
    }

    public function index()
    {
        $user = auth()->user();

        if ($user->isAdmin()) {
            return view('survei.admin', [
                'periodes' => Periode::latest()->get(),
                'kelas' => Kelas::orderBy('tingkat')->orderBy('kelas')->get(),
            ]);
        }

        if ($user->canFacilitateSurvei()) {
            $terbuka = Periode::query()
                ->where('ditutup', false)
                ->where('mulai', '<=', now())
                ->where('selesai', '>=', now())
                ->with([
                    'targets' => fn ($query) => $query->select('uuid', 'periode_id', 'kelas_id', 'kelas_nama'),
                    'sesi' => fn ($query) => $query
                        ->select('uuid', 'periode_id', 'kelas_ids', 'berlaku_sampai', 'diakhiri_pada', 'created_at')
                        ->whereNull('diakhiri_pada')
                        ->where('berlaku_sampai', '>', now())
                        ->latest('created_at'),
                ])
                ->latest('mulai')
                ->get();
            $lihatSemua = $user->canViewSurveiResults();
            $lihatSendiri = $user->canViewOwnSurveiResults();
            $rekap = ($lihatSemua || $lihatSendiri)
                ? Periode::where(fn ($query) => $query->where('ditutup', true)->orWhere('selesai', '<', now()))
                    ->when(! $lihatSemua, fn ($query) => $query->whereHas('targets',
                        fn ($targets) => $targets->where('guru_id', $user->guru->uuid)))
                    ->latest('selesai')
                    ->get()
                : collect();

            return response()->view('survei.surveier', compact('terbuka', 'rekap', 'lihatSemua'))
                ->header('Cache-Control', 'private, no-store');
        }

        if ($user->access === 'siswa') {
            $siswa = $this->siswa();
            $periodes = Periode::where('ditutup', false)
                ->where('mulai', '<=', now())
                ->where('selesai', '>=', now())
                ->whereHas('targets', fn ($query) => $query->where('kelas_id', $siswa->id_kelas))
                ->with(['targets' => fn ($relation) => $this->denganStatusJawaban($relation->getQuery(), $siswa)])
                ->orderBy('uuid')
                ->get()
                ->map(function (Periode $periode) use ($siswa): array {
                    $targets = $periode->targets->where('kelas_id', $siswa->id_kelas);

                    return [
                        'periode' => $periode,
                        'targets' => $targets,
                        'selesai' => $this->periodeSudahDiisi($periode->targets),
                        'jumlah_selesai' => $targets->where('sudah_diisi', true)->count(),
                    ];
                });

            return response()->view('survei.siswa', compact('periodes'))->header('Cache-Control', 'private, no-store');
        }

        $lihatSemua = $user->canViewSurveiResults();
        abort_unless($lihatSemua || $user->canViewOwnSurveiResults(), 403);

        $periodes = Periode::where(fn ($query) => $query->where('ditutup', true)->orWhere('selesai', '<', now()))
            ->when(! $lihatSemua, fn ($query) => $query->whereHas('targets',
                fn ($targets) => $targets->where('guru_id', $user->guru->uuid)))
            ->latest('selesai')
            ->get();

        return response()->view('survei.rekap', compact('periodes', 'lihatSemua'))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request)
    {
        $this->admin();

        $values = $request->validate([
            'judul' => 'required|string|max:150',
            'mulai' => 'required|date',
            'selesai' => 'required|date|after:mulai',
            'kelas' => 'required|array|min:1',
            'kelas.*' => ['required', 'uuid', 'distinct', Rule::exists('kelas', 'uuid')],
            'wajib_seb' => ['sometimes', 'boolean'],
            'wajib_kios' => ['sometimes', 'boolean'],
        ]);

        $periode = DB::transaction(function () use ($values) {
            $jadwal = Jadwal::query()
                ->whereIn('id_kelas', $values['kelas'])
                ->whereNotNull('id_guru')
                ->whereNotNull('id_pelajaran')
                ->select(['id_kelas', 'id_guru', 'id_pelajaran'])
                ->distinct()
                ->with(['kelas', 'guru', 'pelajaran'])
                ->get()
                ->filter(fn (Jadwal $slot) => $slot->kelas && $slot->guru && $slot->pelajaran);

            $kelasTanpaJadwal = array_diff($values['kelas'], $jadwal->pluck('id_kelas')->all());
            if ($kelasTanpaJadwal !== []) {
                $namaKelas = Kelas::whereIn('uuid', $kelasTanpaJadwal)->get()->pluck('nama_lengkap')->implode(', ');

                throw ValidationException::withMessages([
                    'kelas' => 'Lengkapi jadwal yang berisi guru dan mata pelajaran untuk '.$namaKelas.'.',
                ]);
            }

            $periode = Periode::create([
                'judul' => $values['judul'],
                'mulai' => $values['mulai'],
                'selesai' => $values['selesai'],
                'dibuat_oleh' => auth()->id(),
                'wajib_token' => true,
                'wajib_seb' => empty($values['wajib_kios']) && ($values['wajib_seb'] ?? false),
                'wajib_kios' => $values['wajib_kios'] ?? false,
                'pertanyaan' => [
                    'mapel' => Pertanyaan::untuk('mapel'),
                    'wali' => Pertanyaan::untuk('wali'),
                    'komentar_mapel' => Pertanyaan::terbuka('mapel'),
                    'komentar_wali' => Pertanyaan::terbuka('wali'),
                    'kendala_mapel' => Pertanyaan::kendala('mapel'),
                    'kendala_wali' => Pertanyaan::kendala('wali'),
                    'dibalik_mapel' => Pertanyaan::dibalik('mapel'),
                ],
            ]);

            $seen = [];

            foreach ($jadwal as $slot) {
                $periode->targets()->create([
                    'kelas_id' => $slot->id_kelas,
                    'guru_id' => $slot->id_guru,
                    'pelajaran_id' => $slot->id_pelajaran,
                    'jenis' => 'mapel',
                    'kelas_nama' => $slot->kelas->nama_lengkap,
                    'guru_nama' => $slot->guru->nama,
                    'pelajaran_nama' => $slot->pelajaran->nama,
                ]);
            }

            foreach (Walikelas::with(['kelas', 'guru'])->whereIn('id_kelas', $values['kelas'])->get() as $walikelas) {
                if (! $walikelas->kelas || ! $walikelas->guru) {
                    continue;
                }

                $key = 'wali:'.$walikelas->id_kelas.':'.$walikelas->id_guru;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;

                $periode->targets()->create([
                    'kelas_id' => $walikelas->id_kelas,
                    'guru_id' => $walikelas->id_guru,
                    'jenis' => 'wali',
                    'kelas_nama' => $walikelas->kelas->nama_lengkap,
                    'guru_nama' => $walikelas->guru->nama,
                ]);
            }

            Audit::log('survei_periode_dibuat', $periode);

            return $periode;
        });

        return redirect()->route('survei.periode', $periode)
            ->with('success', 'Survei dibuat. Periksa daftar sasaran dan bagikan QR saat survei dibuka.');
    }

    public function periode(Periode $periode)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->canFacilitateSurvei(), 403);
        $targets = $periode->targets()
            ->orderBy('kelas_nama')
            ->orderByRaw("CASE WHEN jenis = 'wali' THEN 1 ELSE 0 END")
            ->orderBy('pelajaran_nama')
            ->withCount('respons')
            ->get();

        $pembuat = $periode->bolehMelihatIdentitas($user);
        $memandu = $periode->bolehMemanduSesi($user);
        $kelasSesi = collect();
        $sesi = null;
        $sudahMengirim = collect();
        $sebSiap = $this->seb->siap();
        $kiosSiap = $this->kios->siap();
        if ($memandu && $periode->terbuka()) {
            $sesi = $periode->sesiAktif();
            $targetsPerKelas = $targets->toBase()->groupBy('kelas_id');
            $kelasSesi = $targetsPerKelas->map(fn ($bagian) => $bagian->first()->kelas_nama);
            $jumlahBagian = $targetsPerKelas->map->count();
            $sudahMengirim = Respons::query()
                ->join('survei_targets', 'survei_targets.uuid', '=', 'survei_respons.target_id')
                ->whereIn('survei_targets.uuid', $periode->targets()->select('survei_targets.uuid'))
                ->groupBy('survei_targets.kelas_id', 'survei_respons.siswa_id')
                ->selectRaw('survei_targets.kelas_id, COUNT(*) AS jumlah_bagian')
                ->toBase()
                ->get()
                ->filter(fn ($row) => (int) $row->jumlah_bagian === $jumlahBagian[$row->kelas_id])
                ->countBy('kelas_id');
        }

        return response()->view('survei.periode', compact('periode', 'targets', 'pembuat', 'memandu', 'kelasSesi', 'sesi', 'sudahMengirim', 'sebSiap', 'kiosSiap'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function mulaiSesi(Request $request, Periode $periode)
    {
        abort_unless($periode->bolehMemanduSesi($request->user()), 403);
        $values = $request->validate([
            'kelas_sesi' => ['required', 'array', 'list', 'min:1'],
            'kelas_sesi.*' => ['required', 'uuid', 'distinct', Rule::in($periode->targets()->distinct()->pluck('kelas_id')->all())],
        ]);

        DB::transaction(function () use ($periode, $values): void {
            $locked = Periode::whereKey($periode->uuid)->lockForUpdate()->firstOrFail();
            abort_unless($locked->terbuka(), 403, 'Sesi hanya dapat dimulai dalam jadwal periode.');
            if ($locked->wajib_seb && ! $this->seb->siap()) {
                throw ValidationException::withMessages(['wajib_seb' => 'Siapkan konfigurasi Safe Exam Browser sebelum memulai sesi.']);
            }
            if ($locked->wajib_kios && ! $this->kios->siap()) {
                throw ValidationException::withMessages(['wajib_kios' => 'Daftarkan perangkat SIMS Kios sebelum memulai sesi.']);
            }
            if ($locked->sesiAktif()) {
                throw ValidationException::withMessages(['kelas_sesi' => 'Tekan Siap pada sesi yang sedang berjalan terlebih dahulu.']);
            }

            $tokenSebelumnya = $locked->sesi()->latest('uuid')->first()?->token;
            $sesi = $locked->sesi()->create([
                'kelas_ids' => array_map(strval(...), array_values($values['kelas_sesi'])),
                'token' => Sesi::buatToken($tokenSebelumnya),
                'berlaku_sampai' => $locked->selesai,
            ]);
            $locked->update(['wajib_token' => true]);
            Audit::log('survei_sesi_dimulai', $sesi);
        });

        return redirect()->route('survei.periode', $periode)->with('success', 'Sesi dimulai. Bagikan token kepada kelas yang dicentang. Tekan Siap bila kelas selesai.');
    }

    public function wajibkanSeb(Request $request, Periode $periode)
    {
        abort_unless($periode->bolehMelihatIdentitas($request->user()), 403);

        DB::transaction(function () use ($periode): void {
            $locked = Periode::whereKey($periode->uuid)->lockForUpdate()->firstOrFail();
            abort_if($locked->berakhir() || $locked->sesiAktif(), 403, 'Akhiri sesi aktif sebelum mewajibkan Safe Exam Browser.');
            abort_if($locked->wajib_kios, 403, 'Periode ini menggunakan SIMS Kios.');
            if (! $locked->wajib_seb) {
                $locked->update(['wajib_seb' => true]);
                Audit::log('survei_seb_diwajibkan', $locked);
            }
        });

        return redirect()->route('survei.periode', $periode)->with('success', 'Safe Exam Browser diwajibkan untuk periode ini.');
    }

    public function wajibkanKios(Request $request, Periode $periode)
    {
        abort_unless($periode->bolehMelihatIdentitas($request->user()), 403);

        DB::transaction(function () use ($periode): void {
            $locked = Periode::whereKey($periode->uuid)->lockForUpdate()->firstOrFail();
            abort_if($locked->berakhir() || $locked->sesiAktif(), 403, 'Akhiri sesi aktif sebelum mengubah mode perangkat.');
            if (! $locked->wajib_kios) {
                $locked->update(['wajib_kios' => true, 'wajib_seb' => false, 'wajib_token' => true]);
                Audit::log('survei_kios_diwajibkan', $locked);
            }
        });

        return redirect()->route('survei.periode', $periode)->with('success', 'SIMS Kios diwajibkan untuk periode ini.');
    }

    public function gantiToken(Request $request, Periode $periode, string $sesiId)
    {
        abort_unless($periode->bolehMemanduSesi($request->user()), 403);


        DB::transaction(function () use ($periode, $sesiId): void {
            $locked = Periode::whereKey($periode->uuid)->lockForUpdate()->firstOrFail();
            $sesi = $locked->sesiAktif();
            abort_unless($sesi && $sesi->uuid === $sesiId, 403, 'Sesi sudah berakhir.');

            $sesi->update(['token' => Sesi::buatToken($sesi->token)]);
            Audit::log('survei_token_diganti', $sesi);
        });

        return redirect()->route('survei.periode', $periode)->with('success', 'Token diganti. Siswa yang belum mengirim perlu memasukkan token baru.');
    }

    public function akhiriSesi(Request $request, Periode $periode, string $sesiId)
    {
        abort_unless($periode->bolehMemanduSesi($request->user()), 403);

        DB::transaction(function () use ($periode, $sesiId): void {
            $locked = Periode::whereKey($periode->uuid)->lockForUpdate()->firstOrFail();
            $sesi = $locked->sesi()->findOrFail($sesiId);
            if (! $sesi->diakhiri_pada) {
                $sesi->update(['diakhiri_pada' => now()]);
                Audit::log('survei_sesi_diakhiri', $sesi);
            }
        });

        return redirect()->route('survei.periode', $periode)->with('success', 'Siap. Sesi kelas ini berakhir dan token sudah tidak berlaku.');
    }

    public function bukaDenganToken(Request $request, Periode $periode)
    {
        $siswa = $this->siswa();
        abort_unless($periode->wajib_token && $periode->targets()->where('kelas_id', $siswa->id_kelas)->exists(), 403);
        $sesi = $periode->sesiAktif();
        $token = $request->input('token');
        if (! $sesi || ! $sesi->mencakup($siswa->id_kelas) || ! is_string($token)
            || ! preg_match('/\A[0-9]{6}\z/', $token) || ! hash_equals($sesi->token, $token)) {
            return redirect()->route('survei.form', $periode)->withErrors(['token' => 'Token tidak berlaku untuk kelasmu. Periksa kode atau hubungi surveyor.']);
        }

        $sesi->beriAkses($request, $siswa->id_kelas);

        return redirect()->route('survei.form', $periode);
    }

    public function tutup(Periode $periode)
    {
        $this->admin();

        DB::transaction(function () use ($periode): void {
            $locked = Periode::whereKey($periode->uuid)->lockForUpdate()->firstOrFail();
            abort_if($locked->wajib_token && ! $locked->bolehMelihatIdentitas(auth()->user()), 403);
            if (! $locked->ditutup) {
                $locked->sesi()->whereNull('diakhiri_pada')->where('berlaku_sampai', '>', now())
                    ->update(['diakhiri_pada' => now()]);
                $locked->update(['ditutup' => true]);
                Audit::log('survei_periode_ditutup', $locked);
            }
        });

        return back()->with('success', 'Survei telah ditutup dan tidak dapat dibuka kembali.');
    }

    public function masuk(Periode $periode)
    {
        if ($kembali = $this->kembalikanPemandu($periode)) {
            return $kembali;
        }

        if (auth()->user()->access !== 'siswa') {
            return $this->akunTidakSesuai($periode, 'isi', 'Gunakan akun siswa untuk mengisi survei.');
        }

        $siswa = $this->siswa();
        abort_unless(($periode->terbuka() || (($periode->wajib_seb || $periode->wajib_kios) && $periode->berakhir()))
            && $periode->targets()->where('kelas_id', $siswa->id_kelas)->exists(), 403, 'Survei tidak tersedia untuk kelasmu.');

        return redirect()->route('survei.form', $periode);
    }

    public function form(Request $request, Periode $periode)
    {
        if ($kembali = $this->kembalikanPemandu($periode)) {
            return $kembali;
        }

        if (auth()->user()->access !== 'siswa') {
            return $this->akunTidakSesuai($periode, 'isi', 'Gunakan akun siswa untuk mengisi survei.');
        }

        $siswa = $this->siswa();
        abort_if(! $periode->wajib_seb && ! $periode->wajib_kios && ! $periode->terbuka(), 403, 'Survei belum dibuka atau sudah berakhir.');
        $status = $this->targetDenganStatus($periode, $siswa);
        $targets = $status->where('kelas_id', $siswa->id_kelas);
        abort_unless($targets->isNotEmpty(), 403, 'Survei tidak tersedia untuk kelasmu.');

        if ($this->periodeSudahDiisi($status)) {
            return $this->jawabanTerkirim($request, $periode);
        }

        abort_unless($periode->terbuka(), 403, 'Survei belum dibuka atau sudah berakhir.');

        $tandaSesi = null;
        if ($periode->wajib_token) {
            $sesi = $periode->sesiAktif();
            $tandaSesi = $sesi?->akses($request, $siswa->id_kelas);
            if (! $tandaSesi) {
                $bisaMasukkanToken = $sesi?->mencakup($siswa->id_kelas) ?? false;
                $kelasLainBerjalan = (bool) ($sesi && ! $bisaMasukkanToken);

                return response()->view('survei.token', compact('periode', 'bisaMasukkanToken', 'kelasLainBerjalan'))
                    ->header('Cache-Control', 'private, no-store');
            }
        }

        $tantanganKios = null;
        if ($periode->wajib_kios) {
            $tantanganKios = $this->kios->tantangan($request, $periode);
            $kiosSiap = $this->kios->siap();
            if (! $kiosSiap || $request->session()->pull($this->kios->kunciAkses($request, $periode)) !== $tandaSesi) {
                return response()->view('survei.kios', compact('periode', 'tantanganKios', 'tandaSesi', 'kiosSiap'))
                    ->header('Cache-Control', 'private, no-store');
            }
        }

        if ($periode->wajib_seb) {
            $sebSiap = $this->seb->siap();
            if ($sebSiap) {
                $url = $this->seb->urlForm($request, $periode);
                if ($request->fullUrl() !== $url) {
                    return redirect()->to($url)->header('Cache-Control', 'private, no-store');
                }
            }

            if (! $this->aksesSeb($request, $periode)) {
                return response()->view('survei.seb', compact('periode', 'sebSiap'))
                    ->header('Cache-Control', 'private, no-store');
            }
        }

        $targets = $targets->where('sudah_diisi', false)->values();

        return response()->view('survei.form', [
            'periode' => $periode,
            'targets' => $targets,
            'skala' => Pertanyaan::SKALA,
            'tandaSesi' => $tandaSesi,
            'tantanganKios' => $tantanganKios,
            'isKiosk' => true,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function isi(Target $target)
    {
        $this->targetSiswa($target);

        return redirect()->route('survei.form', $target->periode);
    }

    public function kirimBatch(Request $request, Periode $periode)
    {
        $siswa = $this->siswa();
        $periode = DB::transaction(function () use ($request, $periode, $siswa): Periode {
            $locked = Periode::whereKey($periode->uuid)->lockForUpdate()->firstOrFail();
            $this->simpanJawaban($request, $locked, $siswa);

            return $locked;
        });

        return $this->jawabanTerkirim($request, $periode);
    }

    private function simpanJawaban(Request $request, Periode $periode, Siswa $siswa): void
    {
        abort_if(! $periode->wajib_seb && ! $periode->wajib_kios && ! $periode->terbuka(), 403, 'Survei belum dibuka atau sudah berakhir.');
        $status = $this->targetDenganStatus($periode, $siswa);
        $targets = $status->where('kelas_id', $siswa->id_kelas);
        abort_unless($targets->isNotEmpty(), 403, 'Survei tidak tersedia untuk kelasmu.');

        if ($this->periodeSudahDiisi($status)) {
            return;
        }

        $pending = $targets->where('sudah_diisi', false);
        abort_unless($periode->terbuka(), 403, 'Survei belum dibuka atau sudah berakhir.');
        $this->pastikanSesiSiswa($request, $periode, $siswa->id_kelas);
        abort_if($periode->wajib_seb && ! $this->aksesSeb($request, $periode), 403, 'Pengiriman memerlukan Safe Exam Browser.');
        $this->pastikanKios($request, $periode);

        $rules = [
            'jawaban' => 'required|array',
            'kendala' => 'nullable|array',
            'kendala_lainnya' => 'nullable|array',
            'komentar' => 'nullable|array',
        ];

        foreach ($pending as $target) {
            $key = $target->uuid;
            $questions = $periode->butir($target->jenis);
            $comments = $periode->pertanyaanTerbuka($target->jenis);
            $kendala = $periode->pilihanKendala($target->jenis);
            $rules["jawaban.$key"] = ['required', 'array', 'size:'.count($questions)];
            foreach ($questions as $index => $_) {
                $rules["jawaban.$key.$index"] = ['required', Rule::in(Pertanyaan::NILAI_JAWABAN)];
            }
            $rules["kendala.$key"] = $target->jenis === 'wali'
                ? ['required', 'array', 'list', 'size:1']
                : ['nullable', 'array', 'list', 'max:'.count($kendala)];
            $rules["kendala.$key.*"] = ['string', Rule::in(array_keys($kendala))];
            if ($target->jenis === 'mapel') {
                $rules["kendala_lainnya.$key"] = ['nullable', 'string', 'max:1000'];
            }
            foreach ($comments as $index => $_) {
                $rules["komentar.$key.$index"] = ['nullable', 'string', 'max:1000'];
            }
        }

        $values = $request->validate($rules);

        foreach ($pending as $target) {
            $pilihan = $values['kendala'][$target->uuid] ?? [];
            if (in_array('tidak_ada', $pilihan, true) && count(array_unique($pilihan)) > 1) {
                throw ValidationException::withMessages([
                    'kendala.'.$target->uuid => 'Pilih “Tidak ada kendala” saja, atau pilih kendala yang kamu alami.',
                ]);
            }
        }

        $rows = [];
        foreach ($pending as $target) {
            $targetKey = $target->uuid;
            $pilihan = array_values(array_unique($values['kendala'][$targetKey] ?? []));
            $lainnya = in_array('lainnya', $pilihan, true)
                ? ($values['kendala_lainnya'][$targetKey] ?? null)
                : null;
            $jawaban = [];
            foreach (array_keys($periode->butir($target->jenis)) as $index) {
                $jawaban[$index] = $values['jawaban'][$targetKey][$index];
            }
            $komentar = [];
            if ($target->jenis !== 'wali' || $pilihan === ['ya']) {
                foreach (array_keys($periode->pertanyaanTerbuka($target->jenis)) as $index) {
                    $komentar[$index] = $values['komentar'][$targetKey][$index] ?? null;
                }
            }

            $rows[] = [
                'school_id' => $periode->school_id,
                'target_id' => $target->uuid,
                'siswa_id' => $siswa->uuid,
                'jawaban' => $jawaban,
                'kendala' => ['pilihan' => $pilihan, 'lainnya' => $lainnya],
                'komentar' => $komentar,
            ];
        }

        Respons::query()->fillAndInsert($rows);
        Audit::log('survei_jawaban_dikirim', $periode);
    }

    private function pastikanSesiSiswa(Request $request, Periode $periode, string $kelasId): void
    {
        if ($periode->wajib_token) {
            $sesi = $periode->sesiAktif();
            abort_unless($sesi && $sesi->mencakup($kelasId), 403, 'Kelasmu tidak termasuk sesi yang sedang berjalan.');
            $tanda = $sesi->akses($request, $kelasId);
            abort_unless($tanda && $request->input('tanda_sesi') === $tanda, 403, 'Akses sesi sudah berakhir. Hubungi surveyor untuk melanjutkan.');
        }
    }

    private function aksesSeb(Request $request, Periode $periode): bool
    {
        if ($this->seb->verifikasi($request->header('X-SafeExamBrowser-RequestHash'), $request->fullUrl())) {
            return true;
        }

        $bukti = $request->isMethod('POST')
            ? $request->input('bukti_seb')
            : $request->session()->get($this->seb->kunciAkses($request, $periode));

        return is_string($bukti) && $this->seb->verifikasi($bukti, $this->seb->urlForm($request, $periode));
    }

    public function verifikasiKios(Request $request, Periode $periode)
    {
        $siswa = $this->siswa();
        abort_unless($periode->wajib_kios && $periode->wajib_token, 403);
        $this->pastikanSesiSiswa($request, $periode, $siswa->id_kelas);
        $tanda = $request->input('tanda_sesi');
        abort_unless($this->kios->verifikasi($request, $periode, 'buka', $tanda), 403);

        $request->session()->put($this->kios->kunciAkses($request, $periode), $tanda);

        return response()->json(['form' => route('survei.form', $periode)])->header('Cache-Control', 'private, no-store');
    }

    private function pastikanKios(Request $request, Periode $periode): void
    {
        if ($periode->wajib_kios) {
            $tanda = $request->input('tanda_sesi');
            abort_unless(is_string($tanda) && $this->kios->verifikasi($request, $periode, 'kirim', $tanda), 403,
                'Pengiriman memerlukan perangkat yang terkunci oleh SIMS Kios.');
        }
    }

    public function verifikasiSeb(Request $request, Periode $periode)
    {
        $siswa = $this->siswa();
        abort_unless($periode->wajib_seb && $periode->terbuka()
            && $periode->sesiAktif()?->akses($request, $siswa->id_kelas), 403);

        $bukti = $request->input('bukti_seb');
        $url = $this->seb->urlForm($request, $periode);
        abort_unless(is_string($bukti) && $this->seb->verifikasi($bukti, $url), 403);

        $request->session()->put($this->seb->kunciAkses($request, $periode), $bukti);

        return response()->json(['form' => $url])->header('Cache-Control', 'private, no-store');
    }

    public function keluarSeb(Request $request, string $kode)
    {
        $selesaiOleh = $request->session()->get('survei.seb.selesai');
        abort_unless($this->seb->siap() && hash_equals((string) config('survei.seb_quit_id'), $kode)
            && $selesaiOleh === $request->user()->getKey(), 404);
        $request->session()->forget('survei.seb.selesai');

        return redirect()->route('survei.index')->with('success', 'Jawaban sudah dikirim dan bersifat rahasia.')
            ->header('Cache-Control', 'private, no-store');
    }

    private function jawabanTerkirim(Request $request, Periode $periode): JsonResponse|RedirectResponse
    {
        $request->session()->flash('success', 'Jawaban sudah dikirim dan bersifat rahasia.');

        $keluar = null;
        if ($periode->wajib_seb && $this->aksesSeb($request, $periode)) {
            $request->session()->put('survei.seb.selesai', $request->user()->getKey());
            $keluar = $this->seb->urlKeluar();
        }

        $kiosSelesai = $periode->wajib_kios ? route('survei.kios.selesai', $periode) : null;
        if ($request->expectsJson()) {
            return response()->json(array_filter(['tersimpan' => true, 'keluar' => $keluar, 'kios_selesai' => $kiosSelesai]))
                ->header('Cache-Control', 'private, no-store');
        }

        return redirect()->to($keluar ?? $kiosSelesai ?? route('survei.index'))->header('Cache-Control', 'private, no-store');
    }

    public function selesaiKios(Request $request, Periode $periode)
    {
        $siswa = $this->siswa();
        abort_unless($periode->wajib_kios && $this->periodeSudahDiisi($this->targetDenganStatus($periode, $siswa)), 404);

        return response('Jawaban tersimpan.')->header('Cache-Control', 'private, no-store');
    }

    public function kirim(Request $request, Target $target)
    {
        $this->targetSiswa($target);

        return redirect()->route('survei.form', $target->periode)
            ->with('info', 'Survei diisi dalam satu formulir kelas.');
    }

    public function analisis(Periode $periode, AnalisisGuru $analisis)
    {
        $user = auth()->user();
        $lihatSemua = $user->canViewSurveiResults();
        if (! $lihatSemua && ! $user->canViewOwnSurveiResults()) {
            return $this->akunTidakSesuai($periode, 'analisis', 'Gunakan akun guru pengampu atau petugas yang memiliki izin melihat rekap.');
        }

        abort_unless($periode->berakhir(), 403, 'Analisis tersedia setelah survei berakhir.');

        $lihatCatatan = $periode->bolehMelihatIdentitas($user);
        $gurus = $analisis->rekap($periode, guruId: $lihatSemua ? null : $user->guru->uuid, sertakanCatatan: $lihatCatatan);
        abort_if(! $lihatSemua && $gurus->isEmpty(), 403);
        Audit::log('survei_analisis_dibuka', $periode);

        $kelas = $lihatSemua
            ? $gurus->flatMap(fn (array $guru) => $guru['peran']->flatMap(fn (array $peran) => $peran['targets']))
                ->sortBy('kelas_nama')->pluck('kelas_nama', 'kelas_id')
            : collect();

        return response()->view('survei.analisis', [
            'periode' => $periode,
            'gurus' => $gurus,
            'kelas' => $kelas,
            'lihatSemua' => $lihatSemua,
            'lihatCatatan' => $lihatCatatan,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function laporanGuru(Periode $periode, AnalisisGuru $analisis)
    {
        $user = auth()->user();
        abort_unless($user->canViewSurveiResults(), 403);
        abort_unless($periode->berakhir(), 403, 'Laporan tersedia setelah survei berakhir.');
        $lihatCatatan = $periode->bolehMelihatIdentitas($user);
        $gurus = $analisis->rekap($periode, sertakanCatatan: $lihatCatatan);
        $pdf = Pdf::loadView('survei.laporan-guru-pdf', [
            'periode' => $periode,
            'sekolah' => Setting::get('nama_sekolah', 'Sekolah'),
            'gurus' => $gurus,
            'lihatCatatan' => $lihatCatatan,
            'minimum' => AnalisisGuru::MINIMUM,
        ])->setPaper('a4');
        $pdf->render();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $pdf->getDomPDF()->getCanvas()->page_text(520, 820, '{PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.34, 0.40, 0.46]);
        $response = $pdf->download('rekap-survei-per-guru-'.(Str::slug($periode->judul) ?: $periode->uuid).'.pdf');

        Audit::log('survei_laporan_guru_diekspor', $periode, ['dengan_catatan' => $lihatCatatan]);

        return $response->header('Cache-Control', 'private, no-store');
    }

    public function laporanKelas(Periode $periode, string $kelasId, AnalisisGuru $analisis)
    {
        abort_unless(auth()->user()->canViewSurveiResults(), 403);
        abort_unless($periode->berakhir(), 403, 'Laporan tersedia setelah survei berakhir.');

        $target = $periode->targets()->where('kelas_id', $kelasId)->firstOrFail();
        $gurus = $analisis->rekap($periode, $kelasId);
        $namaKelas = $target->kelas_nama;
        $filename = 'laporan-survei-'.(Str::slug($namaKelas) ?: $kelasId).'.pdf';

        $pdf = Pdf::loadView('survei.laporan-kelas-pdf', [
            'periode' => $periode,
            'namaKelas' => $namaKelas,
            'sekolah' => Setting::get('nama_sekolah', 'Sekolah'),
            'gurus' => $gurus,
            'skorKelas' => $analisis->skorKelas($gurus),
            'minimum' => AnalisisGuru::MINIMUM,
        ])->setPaper('a4');
        $pdf->render();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $pdf->getDomPDF()->getCanvas()->page_text(520, 820, '{PAGE_NUM} / {PAGE_COUNT}', $font, 7, [0.34, 0.40, 0.46]);
        $response = $pdf->download($filename);

        Audit::log('survei_laporan_kelas_diekspor', $periode, ['kelas_id' => $kelasId]);

        return $response->header('Cache-Control', 'private, no-store');
    }

    public function responden(Periode $periode)
    {
        abort_unless($periode->bolehMelihatIdentitas(auth()->user()), 403);
        abort_unless($periode->berakhir(), 403, 'Daftar responden tersedia setelah survei berakhir.');

        $kelas = Respons::query()
            ->join('survei_targets', 'survei_targets.uuid', '=', 'survei_respons.target_id')
            ->join('siswa', 'siswa.uuid', '=', 'survei_respons.siswa_id')
            ->whereIn('survei_targets.uuid', $periode->targets()->select('survei_targets.uuid'))
            ->select('survei_targets.kelas_id', 'survei_targets.kelas_nama', 'siswa.uuid', 'siswa.nama')
            ->distinct()
            ->orderBy('survei_targets.kelas_nama')
            ->orderBy('siswa.nama')
            ->get()
            ->groupBy('kelas_id');

        Audit::log('survei_responden_dibuka', $periode);

        return response()->view('survei.responden', compact('periode', 'kelas'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function kembalikanPemandu(Periode $periode): ?RedirectResponse
    {
        if (! $periode->bolehMemanduSesi(auth()->user())) {
            return null;
        }

        return redirect()
            ->route('survei.periode', $periode)
            ->with('success', 'Tautan itu untuk siswa. Pemantauan survei tetap di halaman ini.');
    }

    private function akunTidakSesuai(Periode $periode, string $tujuan, string $pesan)
    {
        return response()->view('survei.ganti-akun', compact('periode', 'tujuan', 'pesan'), 403)
            ->header('Cache-Control', 'private, no-store');
    }

    public function export(Periode $periode, AnalisisGuru $analisis)
    {
        $this->admin();
        abort_unless($periode->berakhir(), 403, 'Ekspor tersedia setelah survei ditutup.');
        Audit::log('survei_rekap_diekspor', $periode);

        return response()->streamDownload(function () use ($periode, $analisis): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Kelas', 'Guru', 'Mata pelajaran', 'Pertanyaan', 'Respons total', 'Jawaban dinilai', 'Tidak tahu', 'Rata-rata skor 1–5', 'Kendala terpilih']);
            $safe = static fn ($value) => preg_match('/^[\s]*[=+@\-\t\r]/u', (string) $value) ? "'".$value : $value;

            foreach ($periode->targets()->with('respons:target_id,jawaban,kendala')->get() as $target) {
                $responses = $target->respons;
                $count = $responses->count();
                if ($count < AnalisisGuru::MINIMUM) {
                    continue;
                }

                $kendala = $this->ringkasKendala($responses, $periode->pilihanKendala($target->jenis));
                $kendalaCsv = collect($kendala)->map(fn ($count, $label) => $label.': '.$count)->implode('; ');
                foreach ($analisis->butir($periode, $target->jenis, $responses) as $butir) {
                    if ($butir['n'] < AnalisisGuru::MINIMUM) {
                        continue;
                    }
                    fputcsv($out, array_map($safe, [
                        $target->kelas_nama,
                        $target->guru_nama,
                        $target->jenis === 'wali' ? 'Wali kelas' : $target->pelajaran_nama,
                        $butir['text'],
                        $count,
                        $butir['n'],
                        $butir['tidak'],
                        round($butir['rata'], 2),
                        $kendalaCsv,
                    ]));
                }
            }

            fclose($out);
        }, 'rekap-survei.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function hasil(Target $target, AnalisisGuru $analisis)
    {
        $user = auth()->user();
        $lihatSemua = $user->canViewSurveiResults();
        abort_unless($lihatSemua || ($user->canViewOwnSurveiResults() && $target->guru_id === $user->guru->uuid), 403);
        abort_unless($target->periode->berakhir(), 403, 'Hasil tersedia setelah survei ditutup.');

        $pembuat = $target->periode->bolehMelihatIdentitas($user);
        Audit::log('survei_hasil_dibuka', $target);
        $columns = $pembuat ? ['jawaban', 'kendala', 'komentar'] : ['jawaban', 'kendala'];
        $responses = $target->respons()->get($columns);
        $jumlah = $responses->count();
        $hasil = $jumlah >= AnalisisGuru::MINIMUM ? $analisis->butir($target->periode, $target->jenis, $responses) : collect();

        $komentar = [];
        if ($pembuat && $jumlah >= AnalisisGuru::MINIMUM) {
            foreach ($target->periode->pertanyaanTerbuka($target->jenis) as $index => $pertanyaan) {
                $komentar[$pertanyaan] = $responses->map(fn ($response) => $response->komentar[$index] ?? null)->filter()->values();
            }
            if ($target->jenis === 'mapel') {
                $komentar['Kendala lainnya'] = $responses->map(fn ($response) => $response->kendala['lainnya'] ?? null)->filter()->values();
            }
        }
        $kendala = $jumlah >= 5 ? $this->ringkasKendala($responses, $target->periode->pilihanKendala($target->jenis)) : [];
        if (! $lihatSemua) {
            $kendala = array_filter($kendala, fn (int $count) => $count >= AnalisisGuru::MINIMUM);
        }

        return response()->view('survei.hasil', compact('target', 'jumlah', 'hasil', 'komentar', 'kendala', 'pembuat'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function ringkasKendala($responses, array $labels): array
    {
        $counts = array_fill_keys(array_keys($labels), 0);

        foreach ($responses as $response) {
            $data = $response->kendala ?? [];
            $pilihan = is_array($data) && array_key_exists('pilihan', $data) ? $data['pilihan'] : (is_array($data) ? $data : []);
            foreach (array_unique($pilihan) as $key) {
                if (array_key_exists($key, $counts)) {
                    $counts[$key]++;
                }
            }
        }

        return collect($counts)->filter()->mapWithKeys(fn ($count, $key) => [$labels[$key] => $count])->all();
    }
}
