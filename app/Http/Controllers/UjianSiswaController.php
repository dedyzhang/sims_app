<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RetriesOnDbBusy;
use App\Models\Siswa;
use App\Models\Ujian;
use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Models\UjianSoal;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

/**
 * Sisi siswa: masuk pakai token per-kelas → kerjakan (satu soal per layar,
 * autosave) → kumpul. Randomisasi & deadline dibuat SEKALI di start(), dipersist
 * di ujian_attempts (lihat UjianAttempt::urutan_soal/urutan_opsi/batas_waktu_pada) —
 * tak pernah diacak ulang saat render/reload berikutnya.
 */
class UjianSiswaController extends Controller implements HasMiddleware
{
    use RetriesOnDbBusy;

    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                if (!auth()->check()) {
                    return redirect()->guest(route('login'));
                }
                abort_unless($request->user()?->access === 'siswa', 403, 'Halaman ini khusus siswa.');
                return $next($request);
            }),
        ];
    }

    private function siswaAtauGagal(Request $request): Siswa
    {
        $user = $request->user();
        abort_unless($user, 401);

        $memoKey = 'profil_siswa_' . $user->uuid;

        // 1. Cek Request attributes memoization (dalam siklus request yang sama)
        if ($siswa = $request->attributes->get($memoKey)) {
            return $siswa;
        }

        // 2. Cek Session memoization (antar request siswa)
        if ($request->hasSession() && $request->session()->has($memoKey)) {
            $siswa = $request->session()->get($memoKey);
            if ($siswa instanceof Siswa) {
                $request->attributes->set($memoKey, $siswa);
                return $siswa;
            }
        }

        // 3. Fallback query ke database jika belum ada di session/request — kolom minimal saja
        //    (Siswa::KOLOM_PROFIL_SESI), krn hasilnya ikut disimpan di session di bawah.
        $siswa = Siswa::profilUntukSesi($user->uuid);
        abort_unless($siswa, 404, 'Profil siswa tidak ditemukan.');

        if ($request->hasSession()) {
            $request->session()->put($memoKey, $siswa);
        }
        $request->attributes->set($memoKey, $siswa);

        return $siswa;
    }

    public function index(Request $request)
    {
        // Halaman "buka ujian" — rawan tembakan bersamaan saat banyak siswa masuk
        // serentak (mis. simulasi ujian serentak). retryOnDbBusy: coba lagi 3x diam2
        // kalau kena penolakan koneksi sesaat, bukan langsung gagal ke siswa.
        return $this->retryOnDbBusy(function () use ($request) {
            $siswa = $this->siswaAtauGagal($request);

            $ujianKelasList = UjianKelas::with(['ujian.pelajaran', 'ujian.paket'])
                ->where('id_kelas', $siswa->id_kelas)
                ->whereHas('ujian', fn ($q) => $q->whereIn('status', ['published', 'closed']))
                ->get();

            // Attempt yg 'dibatalkan' (soft-cancel, Fase 5: reset oleh guru/admin) TIDAK
            // boleh dianggap "sedang dikerjakan" di sini — siswa harus bisa mulai baru.
            // orderBy('created_at') ASC (bukan latest()) supaya keyBy() menyimpan baris
            // TERBARU per id_ujian_kelas (Collection::keyBy menimpa dgn item yg diproses
            // belakangan, jadi item terlama harus diproses duluan).
            // Pangkas kolom HANYA ke status & identitas, jangan bawa urutan_soal & urutan_opsi yg berat.
            $attempts = UjianAttempt::whereIn('id_ujian_kelas', $ujianKelasList->pluck('uuid'))
                ->where('id_siswa', $request->user()->uuid)
                ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
                ->select(['uuid', 'id_ujian_kelas', 'id_siswa', 'status', 'dikunci'])
                ->orderBy('created_at')
                ->get()->keyBy('id_ujian_kelas');

            $susulanIds = \App\Models\UjianSusulan::whereIn('id_ujian', $ujianKelasList->pluck('id_ujian'))
                ->where('id_siswa', $siswa->uuid)
                ->whereDate('tanggal', now()->toDateString())
                ->pluck('id_ujian')
                ->toArray();

            // OPTIMASI N+1: Ambil semua paket yg butuh scan QR dalam 1 batch query gabungan
            $paketIdsWajibQr = $ujianKelasList->pluck('ujian.paket')
                ->filter(fn ($p) => $p && $p->wajib_scan_qr)
                ->pluck('uuid')
                ->unique();

            $paketSudahHadir = collect();
            if ($paketIdsWajibQr->isNotEmpty()) {
                $paketSudahHadir = \App\Models\UjianDaftarHadir::where('ujian_daftar_hadir.id_siswa', $siswa->uuid)
                    ->where('ujian_daftar_hadir.status', 'hadir')
                    ->whereDate('ujian_daftar_hadir.tanggal', now()->toDateString())
                    ->join('ujian_ruangan', 'ujian_daftar_hadir.id_ruangan', '=', 'ujian_ruangan.uuid')
                    ->whereIn('ujian_ruangan.id_ujian_paket', $paketIdsWajibQr)
                    ->pluck('ujian_ruangan.id_ujian_paket')
                    ->flip();
            }

            $ujianKelasList = $ujianKelasList->filter(function($uk) use ($siswa, $susulanIds, $paketSudahHadir) {
                $ujian = $uk->ujian;
                if ($ujian && $ujian->id_ujian_paket) {
                    $paket = $ujian->paket;
                    
                    if (in_array($ujian->uuid, $susulanIds)) {
                        return true; // Susulan meng-override syarat jadwal & QR
                    }

                    // Kalau bukan susulan, terapkan aturan standar Paket
                    if ($uk->dibuka_mulai && now()->startOfDay()->lt($uk->dibuka_mulai->startOfDay())) {
                        return false;
                    }

                    if ($paket && $paket->wajib_scan_qr) {
                        if (!$paketSudahHadir->has($paket->uuid)) {
                            return false;
                        }
                    }
                }
                return true;
            });

            $groupedUjianKelas = $ujianKelasList->groupBy(function ($uk) use ($susulanIds) {
                if (in_array($uk->id_ujian, $susulanIds)) {
                    return 'Ujian Susulan';
                }
                if ($uk->ujian->id_ujian_paket && $uk->ujian->paket) {
                    return 'Paket: ' . $uk->ujian->paket->nama;
                }
                return 'Tidak Dalam Paket';
            });

            return view('ujian.siswa.index', compact('groupedUjianKelas', 'attempts', 'susulanIds'));
        });
    }

    public function gate(Request $request, Ujian $ujian)
    {
        // Halaman gate ini yg pertama dibuka siswa sebelum masukkan token/scan QR —
        // rawan tembakan bersamaan spt index(). {ujian} sendiri sudah diresolve via
        // route-model-binding SEBELUM method ini jalan (di luar jangkauan retry di
        // sini) — kalau itu yg gagal, ditangani penangan global (bootstrap/app.php).
        return $this->retryOnDbBusy(function () use ($request, $ujian) {
            $siswa = $this->siswaAtauGagal($request);
            // SENGAJA tak ada lagi "self-healing" (buang cache + query ulang) di sini: dulu
            // SETIAP siswa yg membuka/refresh halaman ini sebelum jam mulai ikut menghapus
            // cache jadwal milik semua siswa — tepat di menit2 menunggu paling ramai. Cache
            // jadwal kini dibuang oleh jalur tulisnya sendiri + TTL pendek (Ujian::getCachedKelas),
            // dan halaman ini menghitung mundur sendiri di browser (tak perlu di-refresh).
            $ujianKelas = $ujian->getCachedKelas()->firstWhere('id_kelas', $siswa->id_kelas);

            abort_unless($ujianKelas, 404, 'Ujian ini tidak ditetapkan untuk kelas Anda.');
            abort_unless($ujian->isPublished() || $ujian->isClosed(), 404);

            $ujian->loadMissing(['pelajaran', 'paket']);
            $ujianKelas->setRelation('ujian', $ujian);

            $attempt = UjianAttempt::where('id_ujian_kelas', $ujianKelas->uuid)
                ->where('id_siswa', $request->user()->uuid)
                ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
                ->select(['uuid', 'id_ujian_kelas', 'id_siswa', 'status', 'dikunci', 'wajib_token_ulang'])
                ->latest()->first();

            if ($attempt && $attempt->status !== UjianAttempt::STATUS_IN_PROGRESS) {
                if ($request->wantsJson()) { return response()->json(['redirect' => route('ujian.siswa.hasil', [$ujian, $attempt])]); } return redirect()->route('ujian.siswa.hasil', [$ujian, $attempt]);
            }
            if ($attempt && $attempt->isLocked()) {
                return view('ujian.siswa.terkunci', compact('ujian', 'attempt'));
            }
            // Attempt yg baru dibuka kembali (UjianController::bukaAksesSelesai()) SENGAJA
            // TIDAK langsung diarahkan ke kerjakan() walau statusnya sudah in_progress — demi
            // keamanan, siswa tetap harus masukkan token yg benar lagi (lewat form gate ini,
            // diproses start() yg akan MELANJUTKAN attempt yg sama, bukan bikin baru).
            if ($attempt && $attempt->wajib_token_ulang) {
                return $this->butuhScanQr($ujian, $siswa)
                    ? $this->viewWajibScan($ujian, $siswa)
                    : view('ujian.siswa.gate', compact('ujian', 'ujianKelas'));
            }
            if ($attempt) {
                return redirect()->route('ujian.siswa.kerjakan', [$ujian, $attempt]);
            }

            if ($this->butuhScanQr($ujian, $siswa)) {
                return $this->viewWajibScan($ujian, $siswa);
            }

            return view('ujian.siswa.gate', compact('ujian', 'ujianKelas'));
        });
    }

    private function butuhScanQr(Ujian $ujian, Siswa $siswa): bool
    {
        if ($ujian->wajibScanQr()) {
            $isSusulanHariIni = \App\Models\UjianSusulan::where('id_ujian', $ujian->uuid)
                ->where('id_siswa', $siswa->uuid)
                ->whereDate('tanggal', now()->toDateString())
                ->exists();

            if ($isSusulanHariIni) {
                return false;
            }

            return !$ujian->paket->sudahDicekSiswa($siswa);
        }

        return false;
    }

    private function viewWajibScan(Ujian $ujian, Siswa $siswa)
    {
        $peserta = \App\Models\UjianRuanganPeserta::where('id_siswa', $siswa->uuid)
            ->whereHas('ruangan', fn ($q) => $q->where('id_ujian_paket', $ujian->id_ujian_paket))
            ->with('ruangan')
            ->first();

        return view('ujian.siswa.wajibScan', ['ujian' => $ujian, 'ruangan' => $peserta?->ruangan]);
    }

    public function start(Request $request, Ujian $ujian)
    {
        // Titik paling rawan tembakan bersamaan — semua siswa satu kelas submit token
        // hampir bersamaan pas ujian dibuka. Aman diulang utuh: gagal konek terjadi
        // SEBELUM query manapun terkirim, jadi tak pernah ada tulis-separuh yg keulang.
        return $this->retryOnDbBusy(function () use ($request, $ujian) {
            $siswa = $this->siswaAtauGagal($request);
            $ujianKelas = $ujian->getCachedKelas()->firstWhere('id_kelas', $siswa->id_kelas);
            abort_unless($ujianKelas, 404);
            $ujian->loadMissing('paket');
            $ujianKelas->setRelation('ujian', $ujian);

            // Cek DB HANYA saat versi cache menolak (jadwal mungkin baru diubah) — dan cache
            // bersama cuma dibuang kalau memang terbukti basi (lihat Ujian::kelasSegarUntuk).
            $sudahDicekSegar = false;
            if ($request->user()->cannot('take', $ujianKelas)) {
                $sudahDicekSegar = true;
                $freshUk = $ujian->kelasSegarUntuk($siswa->id_kelas, $ujianKelas);
                if ($freshUk) {
                    $freshUk->setRelation('ujian', $ujian);
                    $ujianKelas = $freshUk;
                }
            }

            if ($request->user()->cannot('take', $ujianKelas)) {
                $pesan = 'Ujian belum dibuka untuk kelas Anda atau waktu pelaksanaan belum tiba.';

                if ($ujianKelas->status === 'closed' || !$ujian->isPublished()) {
                    $pesan = 'Ujian ini sedang ditutup atau belum diterbitkan oleh guru/panitia.';
                } elseif ($ujianKelas->dibuka_mulai && now()->lt($ujianKelas->dibuka_mulai)) {
                    $pesan = 'Ujian belum dimulai. Jadwal mulai: ' . $ujianKelas->dibuka_mulai->translatedFormat('d M Y, H:i');
                } elseif ($ujianKelas->dibuka_sampai && now()->gt($ujianKelas->dibuka_sampai)) {
                    $pesan = 'Waktu pelaksanaan ujian ini sudah berakhir pada ' . $ujianKelas->dibuka_sampai->translatedFormat('d M Y, H:i') . '. Hubungi guru/pengawas jika membutuhkan ujian susulan atau perpanjangan jadwal.';
                } elseif ($ujian->wajibScanQr() && !$ujian->paket?->sudahDicekSiswa($siswa)) {
                    $pesan = 'Anda wajib scan QR kehadiran ruangan ujian terlebih dahulu sebelum memasukkan token.';
                } elseif ($siswa->id_kelas !== $ujianKelas->id_kelas) {
                    $pesan = 'Ujian ini tidak dibuka untuk kelas Anda.';
                } elseif ($request->user()->access !== 'siswa') {
                    $pesan = 'Hanya akun siswa yang dapat mengerjakan ujian ini.';
                }

                abort(403, $pesan);
            }

            $data = $request->validate(['token' => 'required|string']);
            $inputToken = trim($data['token']);
            $tokenCocok = hash_equals((string) $ujianKelas->token_masuk, $inputToken);

            // Token salah menurut cache BISA berarti panitia baru saja "Generate ulang token"
            // (cache masih pegang token lama) — cocokkan sekali lagi ke DB sebelum menolak.
            if (! $tokenCocok && ! $sudahDicekSegar) {
                $freshUk = $ujian->kelasSegarUntuk($siswa->id_kelas, $ujianKelas);
                if ($freshUk && hash_equals((string) $freshUk->token_masuk, $inputToken)) {
                    $freshUk->setRelation('ujian', $ujian);
                    abort_if($request->user()->cannot('take', $freshUk), 403, 'Ujian belum dibuka untuk kelas Anda atau waktu pelaksanaan belum tiba.');
                    $ujianKelas = $freshUk;
                    $tokenCocok = true;
                }
            }

            if (! $tokenCocok) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'ok' => false,
                        'message' => 'Token salah. Minta token yang benar ke guru/panitia ujian.',
                        'errors' => ['token' => ['Token salah. Minta token yang benar ke guru/panitia ujian.']]
                    ], 422);
                }
                return back()->withErrors(['token' => 'Token salah. Minta token yang benar ke guru/panitia ujian.']);
            }

            // Kunci singkat per (kelas-ujian, siswa): "cek sudah punya attempt?" + "buat attempt baru"
            // harus satu kesatuan. Tanpa ini, 2 request token dari siswa yg sama yg diproses
            // BERSAMAAN (mis. browser mencoba ulang setelah 408 padahal request pertama masih
            // jalan) bisa sama-sama lolos cek & membuat 2 attempt — jawaban jadi terbelah. Siswa
            // berbeda tak pernah saling menunggu (kunci per siswa). Tabel attempt tak bisa diberi
            // unique index krn attempt 'dibatalkan' boleh lebih dari satu.
            $kunci = \Illuminate\Support\Facades\Cache::lock('ujian_mulai_' . $ujianKelas->uuid . '_' . $request->user()->uuid, 15);
            try {
                return $kunci->block(8, function () use ($request, $ujian, $ujianKelas) {
            $existing = UjianAttempt::where('id_ujian_kelas', $ujianKelas->uuid)
                ->where('id_siswa', $request->user()->uuid)
                ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
                ->select(['uuid', 'id_ujian_kelas', 'id_siswa', 'status', 'wajib_token_ulang'])
                ->latest()->first();
            if ($existing) {
                // Token sudah tervalidasi (hash_equals di atas) — kalau attempt ini sedang
                // menunggu token-ulang (baru dibuka kembali guru), lepas syaratnya sekarang,
                // TANPA membuat attempt baru/mengacak ulang urutan/menyentuh jawaban.
                if ($existing->wajib_token_ulang) {
                    $existing->update(['wajib_token_ulang' => false]);
                }
                $redirectUrl = route('ujian.siswa.kerjakan', [$ujian, $existing]);
                if ($request->wantsJson()) {
                    return response()->json(['ok' => true, 'redirect' => $redirectUrl]);
                }
                return redirect()->route('ujian.siswa.kerjakan', [$ujian, $existing]);
            }

            $soal = $ujian->getCachedSoalDanOpsi();

            $urutanSoal = $ujian->acak_soal ? $soal->pluck('uuid')->shuffle()->values()->all() : $soal->pluck('uuid')->all();

            $urutanOpsi = [];
            foreach ($soal as $s) {
                if ($s->tipe === 'match') {
                    $pairs = collect($s->meta['pairs'] ?? [])->map(fn ($p) => [
                        'left'  => $p['left'] ?? $p['kiri'] ?? '',
                        'right' => $p['right'] ?? $p['kanan'] ?? '',
                    ])->filter(fn ($p) => $p['left'] !== '' || $p['right'] !== '')->values();
                    $jumlahPasangan = $pairs->count();
                    $idx = $jumlahPasangan > 0 ? range(0, $jumlahPasangan - 1) : [];
                    $urutanOpsi[$s->uuid] = ($ujian->acak_opsi && !empty($idx)) ? collect($idx)->shuffle()->values()->all() : $idx;
                } elseif ($s->butuhOpsi()) {
                    $ids = $s->opsi->pluck('uuid');
                    $urutanOpsi[$s->uuid] = $ujian->acak_opsi ? $ids->shuffle()->values()->all() : $ids->all();
                }
            }

            // Simpan attempt langsung tanpa lock transaction agar tidak terjadi deadlock saat 1000 siswa submit bersamaan
            $attempt = UjianAttempt::create([
                'id_ujian_kelas'    => $ujianKelas->uuid,
                'id_siswa'          => $request->user()->uuid,
                'urutan_soal'       => $urutanSoal,
                'urutan_opsi'       => $urutanOpsi,
                'mulai_pada'        => now(),
                'batas_waktu_pada'  => now()->addMinutes($ujian->durasi_menit),
                'status'            => UjianAttempt::STATUS_IN_PROGRESS,
            ]);

            $redirectUrl = route('ujian.siswa.kerjakan', [$ujian, $attempt]);
            if ($request->wantsJson()) {
                return response()->json(['ok' => true, 'redirect' => $redirectUrl]);
            }

            return redirect()->route('ujian.siswa.kerjakan', [$ujian, $attempt]);
                });
            } catch (\Illuminate\Contracts\Cache\LockTimeoutException) {
                // Request token sebelumnya dari siswa yg sama masih diproses — minta browser
                // mencoba lagi sebentar lagi (gate.blade.php menghormati Retry-After).
                $pesan = 'Permintaan sebelumnya masih diproses. Tunggu beberapa detik lalu coba lagi.';
                return $request->wantsJson()
                    ? response()->json(['ok' => false, 'message' => $pesan], 503, ['Retry-After' => '3'])
                    : back()->withErrors(['token' => $pesan]);
            }
        });
    }

    public function kerjakan(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        // Halaman utama mengerjakan ujian — dibuka semua siswa hampir bersamaan begitu
        // token/scan berhasil, jadi ikut rawan tembakan bersamaan.
        return $this->retryOnDbBusy(function () use ($request, $ujian, $attempt) {
            $this->pastikanMilikSiswa($request, $attempt);

            if ($attempt->isLocked()) {
                return view('ujian.siswa.terkunci', compact('ujian', 'attempt'));
            }
            if ($attempt->status !== UjianAttempt::STATUS_IN_PROGRESS) {
                if ($request->wantsJson()) { return response()->json(['redirect' => route('ujian.siswa.hasil', [$ujian, $attempt])]); } return redirect()->route('ujian.siswa.hasil', [$ujian, $attempt]);
            }
            if ($attempt->isExpired()) {
                // JANGAN redirect ke gate() di sini — gate() akan redirect balik ke sini lagi
                // selama attempt masih 'in_progress' & belum lewat sweep cron ujian:auto-submit,
                // jadi finalisasi langsung di tempat supaya tidak loop tak berujung.
                app(\App\Services\UjianGrader::class)->autoSubmitKarenaWaktuHabis($attempt);
                if ($request->wantsJson()) { return response()->json(['redirect' => route('ujian.siswa.hasil', [$ujian, $attempt])]); } return redirect()->route('ujian.siswa.hasil', [$ujian, $attempt]);
            }

            // OPTIMASI: Muat HANYA kolom jawaban aktif siswa, hemat transfer memori dari DB
            $jawabanTersimpan = UjianJawaban::where('id_attempt', $attempt->uuid)
                ->select(['id_soal', 'id_opsi_dipilih', 'opsi_dipilih_multi', 'jawaban_pasangan', 'jawaban_esai'])
                ->get()
                ->keyBy('id_soal');

            // Isi soal (teks_soal/opsi/pasangan) diambil dari cache yg SUDAH disanitasi
            // RichText::clean() sekali per ujian (Ujian::getCachedSoalTampil) — konten ini
            // dirender client-side lewat x-html, jadi sanitasi tetap WAJIB terjadi sebelum titik
            // ini; bedanya kini tak diulang per siswa. is_benar memang tak pernah ada di cache itu.
            // Di sini cuma urutan acak per siswa (urutan_soal/urutan_opsi) yg diterapkan.
            $isiSoal = $ujian->getCachedSoalTampil();
            $soalTampil = collect($attempt->urutan_soal)
                ->map(fn ($id) => $isiSoal[$id] ?? null)
                ->filter()
                ->values()
                ->map(function (array $s) use ($attempt, $ujian) {
                    $item = ['uuid' => $s['uuid'], 'tipe' => $s['tipe'], 'teks_soal' => $s['teks_soal'], 'poin' => $s['poin']];
                    if ($s['tipe'] === 'match') {
                        $pairs = $s['pairs'];

                        $urutanIdx = $attempt->urutan_opsi[$s['uuid']] ?? [];
                        if (empty($urutanIdx) || count($urutanIdx) !== count($pairs)) {
                            $urutanIdx = array_keys($pairs);
                            if ($ujian->acak_opsi) {
                                shuffle($urutanIdx);
                            }
                        }

                        $item['kiri'] = array_column($pairs, 'left');
                        $item['kanan_acak'] = array_values(array_map(fn ($i) => $pairs[$i]['right'] ?? '', $urutanIdx));
                    } elseif (isset($s['opsi'])) {
                        $urutanOpsiUuid = $attempt->urutan_opsi[$s['uuid']] ?? array_keys($s['opsi']);
                        $item['opsi'] = collect($urutanOpsiUuid)
                            ->filter(fn ($id) => isset($s['opsi'][$id]))
                            ->map(fn ($id) => ['uuid' => $id, 'teks' => $s['opsi'][$id]])
                            ->values()->all();
                    }
                    return $item;
                });

            return view('ujian.siswa.kerjakan', [
                'ujian' => $ujian, 'attempt' => $attempt, 'soalTampil' => $soalTampil, 'jawabanTersimpan' => $jawabanTersimpan,
            ]);
        });
    }

    public function status(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        $this->pastikanMilikSiswa($request, $attempt);

        if ($attempt->status === UjianAttempt::STATUS_IN_PROGRESS && !$attempt->isLocked() && $attempt->isExpired()) {
            app(\App\Services\UjianGrader::class)->autoSubmitKarenaWaktuHabis($attempt);
            $attempt->refresh();
        }

        return response()->json([
            'status'            => $attempt->status,
            'dikunci'           => $attempt->isLocked(),
            'batas_waktu_pada'  => $attempt->batas_waktu_pada?->toIso8601String(),
            'sudah_lewat'       => $attempt->isExpired(),
        ]);
    }

    public function simpanJawaban(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        return $this->retryOnDbBusy(function () use ($request, $ujian, $attempt) {
            $this->pastikanMilikSiswa($request, $attempt);
            abort_if($attempt->isLocked(), 403, 'Ujian terkunci — hubungi guru/panitia untuk membuka kembali.');
            abort_unless($attempt->status === UjianAttempt::STATUS_IN_PROGRESS, 422, 'Ujian ini sudah dikumpulkan.');
            abort_if($attempt->isExpired(), 422, 'Waktu ujian sudah habis.');

            $data = $request->validate([
                'id_soal'            => 'required|uuid',
                'id_opsi_dipilih'    => 'nullable|uuid',
                'opsi_dipilih_multi' => 'nullable|array',
                'opsi_dipilih_multi.*' => 'uuid',
                'jawaban_pasangan'   => 'nullable|array',
                'jawaban_esai'       => 'nullable|string|max:10000',
            ]);

            // Cukup peta kecil uuid=>tipe (bukan membongkar seluruh cache soal+opsi).
            $tipe = $ujian->getCachedTipeSoal()[$data['id_soal']] ?? null;
            abort_unless($tipe, 404, 'Soal tidak ditemukan.');

            UjianJawaban::updateOrCreate(
                ['id_attempt' => $attempt->uuid, 'id_soal' => $data['id_soal']],
                [
                    'id_opsi_dipilih'    => in_array($tipe, ['mcq', 'true_false'], true) ? ($data['id_opsi_dipilih'] ?? null) : null,
                    'opsi_dipilih_multi' => $tipe === 'mcq_complex' ? ($data['opsi_dipilih_multi'] ?? []) : null,
                    'jawaban_pasangan'   => $tipe === 'match' ? ($data['jawaban_pasangan'] ?? []) : null,
                    'jawaban_esai'       => $tipe === 'essay' ? ($data['jawaban_esai'] ?? null) : null,
                    'dijawab_pada'       => now(),
                ]
            );

            return response()->json(['ok' => true]);
        });
    }

    /**
     * Endpoint Sinkron Batch (Rencana 3):
     * Menyimpan sekumpulan jawaban sekaligus dalam 1 single database query (bulk upsert),
     * memotong puluhan ribu request HTTP individual menjadi beberapa request batch ringan.
     */
    public function sinkronBatch(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        return $this->retryOnDbBusy(function () use ($request, $ujian, $attempt) {
            $this->pastikanMilikSiswa($request, $attempt);
            abort_if($attempt->isLocked(), 403, 'Ujian terkunci — hubungi guru/panitia untuk membuka kembali.');
            abort_unless($attempt->status === UjianAttempt::STATUS_IN_PROGRESS, 422, 'Ujian ini sudah dikumpulkan.');
            abort_if($attempt->isExpired(), 422, 'Waktu ujian sudah habis.');

            $validated = $request->validate([
                'jawaban'                        => 'required|array|max:200',
                'jawaban.*.id_soal'              => 'required|uuid',
                'jawaban.*.id_opsi_dipilih'      => 'nullable|uuid',
                'jawaban.*.opsi_dipilih_multi'   => 'nullable|array',
                'jawaban.*.opsi_dipilih_multi.*' => 'uuid',
                'jawaban.*.jawaban_pasangan'     => 'nullable|array',
                'jawaban.*.jawaban_esai'         => 'nullable|string|max:10000',
            ]);

            $daftarJawaban = $validated['jawaban'];
            if (empty($daftarJawaban)) {
                return response()->json(['ok' => true, 'count' => 0]);
            }

            // Cukup peta kecil uuid=>tipe (Cache 6 jam) — dulu tiap sinkron membongkar SELURUH
            // cache soal+opsi (termasuk teks/gambar) cuma utk tahu tipe soal.
            $tipeSoal = $ujian->getCachedTipeSoal();
            $now = now()->toDateTimeString();

            $rows = [];
            foreach ($daftarJawaban as $item) {
                $tipe = $tipeSoal[$item['id_soal']] ?? null;
                if (!$tipe) {
                    continue;
                }

                $idOpsiDipilih = null;
                $opsiMulti = null;
                $pasangan = null;
                $esai = null;

                if (in_array($tipe, ['mcq', 'true_false'], true)) {
                    $idOpsiDipilih = $item['id_opsi_dipilih'] ?? null;
                } elseif ($tipe === 'mcq_complex') {
                    $opsiMulti = isset($item['opsi_dipilih_multi']) ? json_encode($item['opsi_dipilih_multi']) : json_encode([]);
                } elseif ($tipe === 'match') {
                    $pasangan = isset($item['jawaban_pasangan']) ? json_encode($item['jawaban_pasangan']) : json_encode([]);
                } elseif ($tipe === 'essay') {
                    $esai = $item['jawaban_esai'] ?? null;
                }

                $rows[] = [
                    'uuid'               => (string) \Illuminate\Support\Str::uuid(),
                    'id_attempt'         => $attempt->uuid,
                    'id_soal'            => $item['id_soal'],
                    'id_opsi_dipilih'    => $idOpsiDipilih,
                    'opsi_dipilih_multi' => $opsiMulti,
                    'jawaban_pasangan'   => $pasangan,
                    'jawaban_esai'       => $esai,
                    'dijawab_pada'       => $now,
                    'created_at'         => $now,
                    'updated_at'         => $now,
                ];
            }

            if (!empty($rows)) {
                UjianJawaban::upsert(
                    $rows,
                    ['id_attempt', 'id_soal'],
                    ['id_opsi_dipilih', 'opsi_dipilih_multi', 'jawaban_pasangan', 'jawaban_esai', 'dijawab_pada', 'updated_at']
                );
            }

            return response()->json(['ok' => true, 'count' => count($rows)]);
        });
    }

    public function laporKeluar(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        return $this->retryOnDbBusy(function () use ($request, $attempt) {
            $this->pastikanMilikSiswa($request, $attempt);

            $data = $request->validate(['tipe' => 'required|in:keluar_fullscreen,ganti_tab']);

            if ($attempt->status === UjianAttempt::STATUS_IN_PROGRESS && !$attempt->isLocked()) {
                \App\Models\UjianPelanggaran::create(['id_attempt' => $attempt->uuid, 'id_siswa' => $attempt->id_siswa, 'tipe' => $data['tipe']]);
                $attempt->update(['dikunci' => true]);
            }

            return response()->json(['ok' => true]);
        });
    }

    public function submit(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        return $this->retryOnDbBusy(function () use ($request, $ujian, $attempt) {
            $this->pastikanMilikSiswa($request, $attempt);

            // IDEMPOTEN: "Kumpulkan" yg diulang utk attempt yg SUDAH terkumpul dianggap sukses
            // (arahkan ke hasil), bukan error 422. Kasus nyata: saat server padat, web server
            // (LiteSpeed) bisa memutus dgn "408 Request Time-out" padahal PHP tetap menyelesaikan
            // submit-nya — siswa melihat "Pengumpulan Tertunda", menekan lagi, lalu dulu dapat 422
            // yg tampil sbg "Tertunda" LAGI tanpa akhir, padahal ujiannya sudah terkumpul.
            if (in_array($attempt->status, [UjianAttempt::STATUS_SUBMITTED, UjianAttempt::STATUS_DINILAI], true)) {
                return $request->wantsJson()
                    ? response()->json(['redirect' => route('ujian.siswa.hasil', [$ujian, $attempt])])
                    : redirect()->route('ujian.siswa.hasil', [$ujian, $attempt]);
            }

            abort_if($attempt->isLocked(), 403, 'Ujian terkunci — hubungi guru/panitia untuk membuka kembali.');
            abort_unless($attempt->status === UjianAttempt::STATUS_IN_PROGRESS, 422, 'Ujian ini sudah dikumpulkan.');

            DB::transaction(function () use ($attempt) {
                $attempt = UjianAttempt::where('uuid', $attempt->uuid)->lockForUpdate()->first();
                if ($attempt->status !== UjianAttempt::STATUS_IN_PROGRESS) {
                    return;
                }
                // Simpan sederhana (Fast-Submit): Cukup ubah status ke SUBMITTED & catat selesai_pada.
                // Jika Mode Ujian AKTIF: lewati kalkulasi grading sinkron untuk memangkas CPU & RAM server.
                // Grading lengkap akan dihitung on-demand saat Guru/Admin membuka menu Hasil atau Analisis.
                // Jika Mode Ujian NONAKTIF (harian): hitung langsung seperti biasa.
                $attempt->update(['status' => UjianAttempt::STATUS_SUBMITTED, 'selesai_pada' => now()]);

                if (!\App\Support\ModeUjian::aktif()) {
                    app(\App\Services\UjianGrader::class)->finalisasiObjektif($attempt->fresh());
                }
            });

            if ($request->wantsJson()) {
                return response()->json(['redirect' => route('ujian.siswa.hasil', [$ujian, $attempt])]);
            }
            
            return redirect()->route('ujian.siswa.hasil', [$ujian, $attempt]);
        });
    }

    public function hasil(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        return $this->retryOnDbBusy(function () use ($request, $ujian, $attempt) {
            $this->pastikanMilikSiswa($request, $attempt);

            // Jika attempt masih berstatus in_progress, arahkan kembali ke layar kerjakan (atau auto-submit jika kedaluwarsa)
            if ($attempt->status === UjianAttempt::STATUS_IN_PROGRESS && ! $attempt->isLocked()) {
                if ($attempt->isExpired()) {
                    app(\App\Services\UjianGrader::class)->autoSubmitKarenaWaktuHabis($attempt);
                    $attempt->refresh();
                } else {
                    return redirect()->route('ujian.siswa.kerjakan', [$ujian, $attempt]);
                }
            }

            $soalList = collect();
            $jawabanBySoal = collect();

            // Efisiensi Memori & Bebas N+1:
            // Hanya muat soal & jawaban jika pembahasan diaktifkan guru dan ujian sudah berstatus 'dinilai'.
            if ($ujian->tampilkan_pembahasan && $attempt->status === UjianAttempt::STATUS_DINILAI) {
                // 1. Ambil soal + opsi dari cache 6 jam (tanpa query DB berulang, opsi sudah eager-loaded sehingga bebas N+1 saat poinEfektif())
                $soalList = $ujian->getCachedSoalDanOpsi();

                // 2. Muat HANYA kolom esensial dari jawaban (id_soal, is_benar, skor_diperoleh) dalam 1 query, di-keyBy id_soal untuk lookup O(1)
                $jawabanBySoal = UjianJawaban::where('id_attempt', $attempt->uuid)
                    ->select(['id_soal', 'is_benar', 'skor_diperoleh'])
                    ->get()
                    ->keyBy('id_soal');
            }

            return view('ujian.siswa.hasil', compact('ujian', 'attempt', 'soalList', 'jawabanBySoal'));
        });
    }

    private function pastikanMilikSiswa(Request $request, UjianAttempt $attempt): void
    {
        abort_unless($attempt->id_siswa === $request->user()->uuid, 403);
    }
}

