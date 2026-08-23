<?php

namespace App\Http\Controllers;

use App\Models\Guru;
use App\Models\Setting;
use App\Models\Siswa;
use App\Models\UjianAttempt;
use App\Models\UjianBeritaAcara;
use App\Models\UjianDaftarHadir;
use App\Models\UjianJadwal;
use App\Models\UjianKelas;
use App\Models\UjianPelanggaran;
use App\Models\UjianRuangan;
use App\Models\UjianSesi;
use App\Services\UjianKuncian;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Halaman "1 halaman" pengawas ruangan — roster peserta + status ujian hari
 * ini (fork pola UjianMonitorController::poll(), tapi berbasis roster ruangan
 * BUKAN UjianKelas) + tombol buka-kunci (via UjianKuncian, SAMA persis
 * mekanismenya dgn monitor ujian lama, TAPI otorisasi via
 * UjianRuanganPolicy::awasi() — guru mana pun boleh, tak lagi lewat tabel
 * penugasan) + modal gabungan Berita Acara & Daftar Hadir per SESI (satu ruangan
 * bisa jalankan beberapa sesi mapel berbeda hari yg sama, lihat UjianSesi).
 * Jalur masuk utama
 * ke sini SEKARANG lewat scan QR ruangan (lihat UjianRuanganScanController);
 * halaman ini + daftarRuanganSaya() tetap ada sbg jalur alternatif tanpa scan
 * fisik. TIDAK mengubah alur token/attempt siswa sama sekali.
 */
class UjianRuanganMonitorController extends Controller
{
    /** Ujian yg dijadwalkan (UjianJadwal) hari ini utk paket ruangan ini — dasar pencarian attempt aktif tiap siswa. */
    private function idUjianHariIni(UjianRuangan $ruangan): array
    {
        return UjianJadwal::where('id_ujian_paket', $ruangan->id_ujian_paket)
            ->whereDate('tanggal', now()->toDateString())
            ->pluck('id_ujian')->unique()->all();
    }

    /** Daftar ruangan yg boleh dimasuki guru TANPA scan fisik — semua ruangan yg py ujian dijadwalkan hari ini (siapa pun guru boleh masuk, lihat UjianRuanganPolicy::awasi()). */
    public function daftarRuanganSaya(Request $request)
    {
        $user = $request->user();
        if ($user->isAdmin() || $user->canAccess('manage_ujian')) {
            $ruanganList = UjianRuangan::with('paket')->latest()->get();
        } else {
            abort_unless($user->guru, 403, 'Halaman ini khusus guru.');
            $idPaketHariIni = UjianJadwal::whereDate('tanggal', now()->toDateString())->pluck('id_ujian_paket')->unique();
            $ruanganList = UjianRuangan::with('paket')->whereIn('id_ujian_paket', $idPaketHariIni)->latest()->get();
        }

        // SENGAJA tidak auto-redirect walau cuma 1 ruangan — guru harus tetap lihat & tap
        // tombol "Scan & Masuk"-nya sendiri (itu yg memicu catatPengawasSesiAktif()), bukan
        // dilompati diam-diam ke monitor tanpa pernah menyentuh langkah scan-nya.
        return view('ujian.ruangan.daftar', compact('ruanganList'));
    }

    public function show(Request $request, UjianRuangan $ruangan)
    {
        $this->authorize('awasi', $ruangan);
        $ruangan->load(['paket', 'peserta.siswa.kelas']);

        $sesiHariIni = $this->sesiHariIni($ruangan);
        $adaJadwalHariIni = $sesiHariIni->isNotEmpty();
        $guruList = Guru::orderBy('nama')->get();
        $baAdhocList = $this->baAdhocHariIni($ruangan);
        $allUjianList = $ruangan->paket->ujian ?? collect();

        return view('ujian.ruangan.monitor', compact('ruangan', 'sesiHariIni', 'adaJadwalHariIni', 'guruList', 'baAdhocList', 'allUjianList'));
    }

    /**
     * Sesi ujian hari ini + berita acaranya (kalau sudah ada, dgn daftar mapel tercentang
     * via ujianList) + daftar hadir per sesi (keyBy id_siswa) + jumlah peserta "seharusnya"
     * (peserta ruangan yg kelasnya memang diujikan salah satu mapel sesi itu) — dipakai
     * sbg satu sumber data utk modal gabungan Berita Acara + Daftar Hadir per sesi.
     */
    private function sesiHariIni(UjianRuangan $ruangan)
    {
        $sesiList = $ruangan->sesiPada();
        if ($sesiList->isEmpty()) {
            return $sesiList;
        }

        $idSesi = $sesiList->pluck('uuid');

        $beritaAcaraBySesi = UjianBeritaAcara::with(['pengawas', 'ujianList'])
            ->where('id_ruangan', $ruangan->uuid)
            ->whereIn('id_sesi', $idSesi)
            ->get()->keyBy('id_sesi');

        $hadirBySesi = UjianDaftarHadir::where('id_ruangan', $ruangan->uuid)
            ->whereIn('id_sesi', $idSesi)
            ->get()->groupBy('id_sesi');

        return $sesiList->map(function (UjianSesi $sesi) use ($beritaAcaraBySesi, $hadirBySesi, $ruangan) {
            $ba = $beritaAcaraBySesi->get($sesi->uuid);
            if (!$ba) {
                $ba = new UjianBeritaAcara(['id_sesi' => $sesi->uuid]);
                $ba->setRelation('ujianList', collect());
            }
            $sesi->beritaAcara = $ba;
            $sesi->hadirMap = ($hadirBySesi->get($sesi->uuid) ?? collect())->keyBy('id_siswa');
            $sesi->jumlahSeharusnya = $this->jumlahPesertaSeharusnya($ruangan, $sesi);

            return $sesi;
        });
    }

    /** Peserta ruangan yg kelasnya memang diujikan salah satu mapel sesi ini — dipakai baik di halaman monitor maupun cetak PDF, satu sumber kebenaran. */
    private function jumlahPesertaSeharusnya(UjianRuangan $ruangan, UjianSesi $sesi): int
    {
        $idKelasSesiIni = UjianKelas::whereIn('id_ujian', $sesi->jadwal->pluck('id_ujian'))->pluck('id_kelas');

        return $ruangan->peserta()->with('siswa')->get()
            ->filter(fn ($p) => $idKelasSesiIni->contains($p->siswa?->id_kelas))
            ->count();
    }

    /**
     * JSON poll — HANYA peserta yg SUDAH mulai mengerjakan ujian apa pun hari ini (tabel
     * live ini bukan daftar roster lengkap — itu urusan Daftar Hadir di bawahnya; tabel ini
     * murni "siapa sedang/sudah ngerjain apa", supaya tidak penuh baris "Belum Mulai" x2/x3
     * per siswa selama semua orang memang belum mulai apa2).
     *
     * Satu ruangan bisa berisi siswa lintas kelas/tingkat, DAN satu kelas bisa saja diuji
     * >1 mata pelajaran hari yg sama (mis. Matematika pagi + Bahasa Inggris siang, sama2
     * "hari ini") — jadi satu peserta bisa punya LEBIH dari satu baris kalau dia sudah mulai
     * lebih dari satu ujian hari ini, satu per (peserta, UjianKelas) yg py attempt. Baris
     * DIPECAH per kombinasi itu (bukan 1 baris per siswa spt versi awal) supaya reset-kunci
     * bisa tepat sasaran per mapel+tingkat, dan filter `?mapel=` (uuid Ujian) bisa dipakai
     * pengawas mempersempit tampilan.
     */
    public function poll(Request $request, UjianRuangan $ruangan)
    {
        $this->authorize('awasi', $ruangan);

        $idUjianHariIni = $this->idUjianHariIni($ruangan);
        $peserta = $ruangan->peserta()->with('siswa.kelas')->get();
        $idKelasPeserta = $peserta->pluck('siswa.id_kelas')->filter()->unique()->values();

        $ujianKelasHariIni = collect();
        if (!empty($idUjianHariIni) && $idKelasPeserta->isNotEmpty()) {
            $ujianKelasHariIni = UjianKelas::with(['ujian.pelajaran', 'kelas'])
                ->whereIn('id_ujian', $idUjianHariIni)
                ->whereIn('id_kelas', $idKelasPeserta)
                ->get();
        }
        $ujianKelasByKelas = $ujianKelasHariIni->groupBy('id_kelas');

        // Kunci attempt = (id_ujian_kelas, id_siswa/user uuid) — TIDAK cukup id_siswa saja,
        // krn satu siswa bisa py >1 UjianKelas relevan hari ini (lihat docblock). Tanpa
        // orderBy eksplisit (pola sama spt App\Services\UjianRoster) krn UUID v7 primary key
        // di app ini sudah terurut waktu — get() mengembalikan lama→baru, keyBy() menimpa
        // dgn yg diproses BELAKANGAN, jadi otomatis attempt TERBARU yg tersimpan per kunci.
        $attemptsByUjianKelas = UjianAttempt::whereIn('id_ujian_kelas', $ujianKelasHariIni->pluck('uuid'))
            ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
            ->get()
            ->groupBy('id_ujian_kelas')
            ->map(fn ($grp) => $grp->keyBy('id_siswa'));

        $rows = collect();
        foreach ($peserta as $p) {
            $siswa = $p->siswa;
            if (!$siswa) {
                continue;
            }
            foreach ($ujianKelasByKelas->get($siswa->id_kelas, collect()) as $uk) {
                $a = $attemptsByUjianKelas->get($uk->uuid)?->get($siswa->id_login);
                if (!$a) {
                    continue; // belum mulai ujian ini — tak ditampilkan di tabel live, lihat docblock
                }
                $rows->push([
                    'id_peserta' => $p->uuid . '-' . $uk->uuid,
                    'nama'       => $siswa->nama,
                    'kelas'      => trim(($siswa->kelas?->tingkat ?? '') . ($siswa->kelas?->kelas ?? '')),
                    'id_ujian'   => $uk->id_ujian,
                    'mapel'      => $uk->ujian?->pelajaran?->nama,
                    'attempt'    => $a,
                ]);
            }
        }

        $mapelFilter = $request->string('mapel')->toString();
        if ($mapelFilter !== '') {
            $rows = $rows->filter(fn ($r) => $r['id_ujian'] === $mapelFilter)->values();
        }

        $pelanggaranCount = UjianPelanggaran::whereIn('id_attempt', $rows->pluck('attempt.uuid')->filter())
            ->selectRaw('id_attempt, count(*) as jumlah')->groupBy('id_attempt')->pluck('jumlah', 'id_attempt');

        // $a SELALU ada (non-null) di titik ini — baris tanpa attempt sudah di-skip di loop atas.
        $data = $rows->sortBy('nama')->values()->map(function ($r) use ($pelanggaranCount) {
            $a = $r['attempt'];

            return [
                'id_peserta'       => $r['id_peserta'],
                'nama'             => $r['nama'],
                'kelas'            => $r['kelas'],
                'mapel'            => $r['mapel'],
                'attempt_uuid'     => $a->uuid,
                'status'           => $a->status,
                'status_label'     => $a->statusLabel(),
                'dikunci'          => $a->isLocked(),
                'batas_waktu_pada' => $a->batas_waktu_pada?->toIso8601String(),
                'pelanggaran'      => (int) ($pelanggaranCount[$a->uuid] ?? 0),
            ];
        });

        $mapelOpsi = $ujianKelasHariIni->groupBy('id_ujian')->map(function ($grp) {
            $tingkat = $grp->pluck('kelas.tingkat')->filter()->unique()->sort()->implode(', ');

            return [
                'uuid'  => $grp->first()->id_ujian,
                'label' => trim(($grp->first()->ujian?->pelajaran?->nama ?? '—') . ($tingkat !== '' ? " · Tingkat {$tingkat}" : '')),
            ];
        })->sortBy('label')->values();

        return response()->json(['peserta' => $data, 'mapelOpsi' => $mapelOpsi]);
    }

    /** Buka-kunci siswa yg "terkeluar" — HARUS anggota roster ruangan ini (bukan siswa dari ruangan lain). */
    public function bukaKunci(Request $request, UjianRuangan $ruangan, UjianAttempt $attempt, UjianKuncian $kuncian)
    {
        $this->authorize('awasi', $ruangan);

        $siswaUuid = Siswa::where('id_login', $attempt->id_siswa)->value('uuid');
        abort_unless($siswaUuid && $ruangan->peserta()->where('id_siswa', $siswaUuid)->exists(), 404, 'Siswa ini bukan peserta ruangan ini.');
        abort_unless($attempt->isLocked(), 422, 'Attempt ini tidak sedang terkunci.');

        $kuncian->bukaKunci($attempt, 'reset_oleh_pengawas');

        return back()->with('success', 'Kunci dibuka — siswa bisa melanjutkan dari titik terakhir.');
    }

    /**
     * Simpan Berita Acara + Daftar Hadir SATU SESI sekaligus (satu modal, satu submit) —
     * `id_ujian[]` = mapel yg dicentang guru (bisa >1, disimpan sbg SATU dokumen Berita
     * Acara gabungan via relasi ujianList), `hadir[]` = koreksi status kehadiran roster
     * utk sesi ini.
     */
    public function simpanSesi(Request $request, UjianRuangan $ruangan, UjianSesi $sesi)
    {
        $this->authorize('awasi', $ruangan);
        abort_unless($sesi->id_ujian_paket === $ruangan->id_ujian_paket, 404);

        $idUjianValid = $sesi->jadwal->pluck('id_ujian')->all();

        $data = $request->validate([
            'id_ujian'            => ['required', 'array', 'min:1'],
            'id_ujian.*'          => [Rule::in($idUjianValid)],
            'jam_mulai_aktual'    => 'nullable|date_format:H:i',
            'jam_selesai_aktual'  => 'nullable|date_format:H:i',
            'jumlah_hadir'        => 'nullable|integer|min:0',
            'jumlah_tidak_hadir'  => 'nullable|integer|min:0',
            'catatan_kejadian'    => 'nullable|string|max:5000',
            'id_guru_pengawas'    => 'nullable|uuid|exists:gurus,uuid',
            'hadir'               => 'nullable|array',
            'hadir.*.id_siswa'    => 'required|uuid',
            'hadir.*.status'      => 'required|in:hadir,izin,sakit,alpa',
            'hadir.*.keterangan'  => 'nullable|string|max:255',
        ], [], ['id_ujian' => 'mata pelajaran']);

        $idUjianTerpilih = $data['id_ujian'];
        $hadirData = $data['hadir'] ?? [];
        unset($data['id_ujian'], $data['hadir']);

        $data['tanggal'] = $sesi->tanggal->toDateString();
        $data['diisi_oleh'] = $request->user()->uuid;
        $data['diisi_pada'] = now();

        DB::transaction(function () use ($ruangan, $sesi, $data, $idUjianTerpilih, $hadirData, $request) {
            $ba = UjianBeritaAcara::updateOrCreate(
                ['id_ruangan' => $ruangan->uuid, 'id_sesi' => $sesi->uuid],
                $data
            );
            $ba->ujianList()->sync($idUjianTerpilih);

            $idRosterValid = $ruangan->peserta()->pluck('id_siswa');
            foreach ($hadirData as $baris) {
                if (!$idRosterValid->contains($baris['id_siswa'])) {
                    continue; // abaikan diam2 kalau ada id yg bukan peserta ruangan ini
                }
                UjianDaftarHadir::updateOrCreate(
                    ['id_ruangan' => $ruangan->uuid, 'id_siswa' => $baris['id_siswa'], 'id_sesi' => $sesi->uuid],
                    [
                        'status'       => $baris['status'],
                        'keterangan'   => $baris['keterangan'] ?? null,
                        'tanggal'      => $sesi->tanggal->toDateString(),
                        'dicatat_oleh' => $request->user()->uuid,
                        'dicatat_pada' => now(),
                    ]
                );
            }
        });

        return back()->with('success', 'Berita acara & daftar hadir tersimpan.');
    }

    /**
     * Data kop surat (logo + nama sekolah + kepsek) utk PDF resmi — nama/teks kop konvensi
     * sama spt QrAbsensiController::cetak(), TAPI logo WAJIB base64 data URI (bukan URL
     * asset() spt di QrAbsensiController, yg itu utk halaman HTML biasa) — dompdf me-render
     * server-side, tak bisa fetch URL http kecuali "isRemoteEnabled" diaktifkan; pola benar
     * ada di KartuGuruController::fileToDataUri(), disamakan di sini.
     */
    private function kopData(): array
    {
        $kepsek = Guru::whereHas('user', fn ($q) => $q->where('access', 'kepala'))->first();

        return [
            'namaSekolah'   => Setting::get('nama_sekolah', ''),
            'alamatSekolah' => Setting::get('alamat_sekolah', ''),
            'kopTeks'       => Setting::get('kop_teks'),
            'kopLogoKiri'   => $this->kopImgDataUri('kop_logo_kiri', 'img/tutwuri.png'),
            'kopLogoKanan'  => $this->kopImgDataUri('kop_logo_kanan', 'img/maitreyawira_square.png'),
            'kepsekNama'    => $kepsek?->nama ?? Setting::get('kepala_sekolah', ''),
        ];
    }

    private function kopImgDataUri(string $key, string $default): ?string
    {
        $v = Setting::get($key);
        if ($v && Storage::disk('public')->exists($v)) {
            return $this->fileToDataUri(Storage::disk('public')->path($v));
        }
        if (file_exists(public_path($default))) {
            return $this->fileToDataUri(public_path($default));
        }

        return null;
    }

    private function fileToDataUri(string $path): ?string
    {
        if (!is_file($path)) {
            return null;
        }
        $mime = @mime_content_type($path) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode((string) file_get_contents($path));
    }

    /** Cetak PDF Berita Acara satu sesi (bisa mencakup beberapa mapel sekaligus) — format mengikuti dokumen resmi sekolah (kop + narasi + data + TTD pengawas & kepsek). */





    public function simpanAdhoc(Request $request, UjianRuangan $ruangan, ?UjianBeritaAcara $beritaAcara = null)
    {
        $this->authorize('awasi', $ruangan);

        $idUjianValid = $ruangan->paket->ujian()->pluck('uuid')->all();

        $data = $request->validate([
            'id_ujian'            => ['required', 'array', 'min:1'],
            'id_ujian.*'          => [\Illuminate\Validation\Rule::in($idUjianValid)],
            'jam_mulai_aktual'    => 'nullable|date_format:H:i',
            'jam_selesai_aktual'  => 'nullable|date_format:H:i',
            'jumlah_hadir'        => 'nullable|integer|min:0',
            'jumlah_tidak_hadir'  => 'nullable|integer|min:0',
            'catatan_kejadian'    => 'nullable|string|max:5000',
            'id_guru_pengawas'    => 'nullable|uuid|exists:gurus,uuid',
            'hadir'               => 'nullable|array',
            'hadir.*.id_siswa'    => 'required|uuid',
            'hadir.*.status'      => 'required|in:hadir,izin,sakit,alpa',
            'hadir.*.keterangan'  => 'nullable|string|max:255',
        ], [], ['id_ujian' => 'mata pelajaran']);

        $idUjianTerpilih = $data['id_ujian'];
        $hadirData = $data['hadir'] ?? [];
        unset($data['id_ujian'], $data['hadir']);

        $data['tanggal'] = $request->input('tanggal') ?? now()->toDateString();
        $data['diisi_oleh'] = $request->user()->uuid;
        $data['diisi_pada'] = now();

        DB::transaction(function () use ($ruangan, $beritaAcara, $data, $idUjianTerpilih, $hadirData, $request) {
            if (!$beritaAcara || !$beritaAcara->exists) {
                // Buat UjianSesi ad-hoc (tanpa UjianJadwal, tidak akan muncul di admin/Jadwal)
                $sesi = UjianSesi::create([
                    'id_ujian_paket' => $ruangan->id_ujian_paket,
                    'tanggal' => $data['tanggal'],
                    'jam_mulai' => $data['jam_mulai_aktual'] ?? '00:00',
                    'jam_selesai' => $data['jam_selesai_aktual'] ?? '23:59',
                    'label' => 'Ad-hoc',
                ]);
                $data['id_ruangan'] = $ruangan->uuid;
                $data['id_sesi'] = $sesi->uuid;
                $beritaAcara = UjianBeritaAcara::create($data);
            } else {
                $sesi = $beritaAcara->sesi;
                if ($sesi) {
                    $sesi->update([
                        'jam_mulai' => $data['jam_mulai_aktual'] ?? $sesi->jam_mulai,
                        'jam_selesai' => $data['jam_selesai_aktual'] ?? $sesi->jam_selesai,
                    ]);
                }
                $beritaAcara->update($data);
            }

            $beritaAcara->ujianList()->sync($idUjianTerpilih);

            $idRosterValid = $ruangan->peserta()->pluck('id_siswa');
            foreach ($hadirData as $baris) {
                if (!$idRosterValid->contains($baris['id_siswa'])) {
                    continue;
                }
                UjianDaftarHadir::updateOrCreate(
                    ['id_ruangan' => $ruangan->uuid, 'id_siswa' => $baris['id_siswa'], 'tanggal' => $data['tanggal']],
                    [
                        'status'       => $baris['status'],
                        'keterangan'   => $baris['keterangan'] ?? null,
                        'id_sesi'      => null, // lepaskan dari sesi spesifik, berlaku utk seharian
                        'dicatat_oleh' => $request->user()->uuid,
                        'dicatat_pada' => now(),
                    ]
                );
            }
        });

        return back()->with('success', 'Berita acara ad-hoc tersimpan.');
    }

    private function baAdhocHariIni(UjianRuangan $ruangan)
    {
        $baList = UjianBeritaAcara::with(['pengawas', 'ujianList', 'sesi'])
            ->where('id_ruangan', $ruangan->uuid)
            ->whereDate('tanggal', now()->toDateString())
            ->whereHas('sesi', function ($query) {
                $query->whereDoesntHave('jadwal');
            })
            ->get();

        $hadirHariIni = UjianDaftarHadir::where('id_ruangan', $ruangan->uuid)
            ->whereDate('tanggal', now()->toDateString())
            ->get()->keyBy('id_siswa');

        return $baList->map(function ($ba) use ($hadirHariIni, $ruangan) {
            $ba->hadirMap = clone $hadirHariIni;
            $ba->jumlahSeharusnya = $this->jumlahPesertaSeharusnyaAdhoc($ruangan, $ba);
            return $ba;
        });
    }

    private function jumlahPesertaSeharusnyaAdhoc(UjianRuangan $ruangan, UjianBeritaAcara $ba): int
    {
        $idKelasBaIni = UjianKelas::whereIn('id_ujian', $ba->ujianList->pluck('uuid'))->pluck('id_kelas');

        return $ruangan->peserta()->with('siswa')->get()
            ->filter(fn ($p) => $idKelasBaIni->contains($p->siswa?->id_kelas))
            ->count();
    }

    public function cetakHadirAdhoc(UjianRuangan $ruangan, UjianBeritaAcara $beritaAcara)
    {
        $this->authorize('awasi', $ruangan);
        abort_unless($beritaAcara->id_ruangan === $ruangan->uuid, 404);
        
        $sesi = $beritaAcara->sesi;
        abort_unless($sesi, 404);

        $ruangan->load(['paket.semester', 'peserta.siswa.kelas']);
        $hadirBySiswa = UjianDaftarHadir::where('id_ruangan', $ruangan->uuid)->whereDate('tanggal', $beritaAcara->tanggal->toDateString())->get()->keyBy('id_siswa');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('ujian.ruangan.hadirCetak', [
            'ruangan'      => $ruangan,
            'paket'        => $ruangan->paket,
            'sesi'         => $sesi, // view cetak bisa terima walau tanpa jadwal
            'tanggal'      => $beritaAcara->tanggal->toDateString(),
            'hadirBySiswa' => $hadirBySiswa,
            'isAdhoc'      => true,
            'beritaAcara'  => $beritaAcara,
        ] + $this->kopData())
            ->setPaper('a4', 'portrait')
            ->stream('Daftar Hadir Adhoc - ' . $ruangan->nama . ' - ' . $beritaAcara->tanggal->toDateString() . '.pdf');
    }

    public function cetakBeritaAcara(UjianRuangan $ruangan, UjianBeritaAcara $beritaAcara)
    {
        $this->authorize('awasi', $ruangan);
        abort_unless($beritaAcara->id_ruangan === $ruangan->uuid, 404);

        $beritaAcara->load(['ujianList.pelajaran', 'pengawas', 'sesi.jadwal', 'ruangan.paket.semester']);
        $paket = $beritaAcara->ruangan->paket;
        $sesi = $beritaAcara->sesi;

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('ujian.ruangan.beritaAcaraCetak', [
            'ruangan'          => $ruangan,
            'paket'            => $paket,
            'beritaAcara'      => $beritaAcara,
            'sesi'             => $sesi,
            'jumlahSeharusnya' => $sesi ? $this->jumlahPesertaSeharusnya($ruangan, $sesi) : 0,
        ] + $this->kopData())
            ->setPaper('a4', 'portrait')
            ->stream('Berita Acara - ' . $ruangan->nama . ' - ' . $beritaAcara->tanggal->toDateString() . '.pdf');
    }

    /** Cetak PDF Daftar Hadir satu SESI — roster ruangan + status kehadiran sesi itu (otomatis terisi lewat scan QR siswa). */
    public function cetakHadir(UjianRuangan $ruangan, UjianSesi $sesi)
    {
        $this->authorize('awasi', $ruangan);
        abort_unless($sesi->id_ujian_paket === $ruangan->id_ujian_paket, 404);
        $ruangan->load(['paket.semester', 'peserta.siswa.kelas']);

        $hadirBySiswa = UjianDaftarHadir::where('id_ruangan', $ruangan->uuid)->whereDate('tanggal', $sesi->tanggal->toDateString())->get()->keyBy('id_siswa');

        return \Barryvdh\DomPDF\Facade\Pdf::loadView('ujian.ruangan.hadirCetak', [
            'ruangan'      => $ruangan,
            'paket'        => $ruangan->paket,
            'sesi'         => $sesi,
            'tanggal'      => $sesi->tanggal->toDateString(),
            'hadirBySiswa' => $hadirBySiswa,
        ] + $this->kopData())
            ->setPaper('a4', 'portrait')
            ->stream('Daftar Hadir - ' . $ruangan->nama . ' - ' . $sesi->tanggal->toDateString() . '.pdf');
    }
}
