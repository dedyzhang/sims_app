<?php

namespace App\Http\Controllers;

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
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_unless($request->user()?->access === 'siswa', 403, 'Halaman ini khusus siswa.');
                return $next($request);
            }),
        ];
    }

    private function siswaAtauGagal(Request $request): Siswa
    {
        $siswa = Siswa::where('id_login', $request->user()->uuid)->first();
        abort_unless($siswa, 404, 'Profil siswa tidak ditemukan.');
        return $siswa;
    }

    public function index(Request $request)
    {
        $siswa = $this->siswaAtauGagal($request);

        $ujianKelasList = UjianKelas::with('ujian.pelajaran')
            ->where('id_kelas', $siswa->id_kelas)
            ->whereHas('ujian', fn ($q) => $q->whereIn('status', ['published', 'closed']))
            ->get();

        // Attempt yg 'dibatalkan' (soft-cancel, Fase 5: reset oleh guru/admin) TIDAK
        // boleh dianggap "sedang dikerjakan" di sini — siswa harus bisa mulai baru.
        // orderBy('created_at') ASC (bukan latest()) supaya keyBy() menyimpan baris
        // TERBARU per id_ujian_kelas (Collection::keyBy menimpa dgn item yg diproses
        // belakangan, jadi item terlama harus diproses duluan).
        $attempts = UjianAttempt::whereIn('id_ujian_kelas', $ujianKelasList->pluck('uuid'))
            ->where('id_siswa', $request->user()->uuid)
            ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
            ->orderBy('created_at')
            ->get()->keyBy('id_ujian_kelas');

        return view('ujian.siswa.index', compact('ujianKelasList', 'attempts'));
    }

    public function gate(Request $request, Ujian $ujian)
    {
        $siswa = $this->siswaAtauGagal($request);
        $ujianKelas = $ujian->kelas()->where('id_kelas', $siswa->id_kelas)->first();
        abort_unless($ujianKelas, 404, 'Ujian ini tidak ditetapkan untuk kelas Anda.');
        abort_unless($ujian->isPublished() || $ujian->isClosed(), 404);

        $attempt = UjianAttempt::where('id_ujian_kelas', $ujianKelas->uuid)
            ->where('id_siswa', $request->user()->uuid)
            ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
            ->latest()->first();

        if ($attempt && $attempt->status !== UjianAttempt::STATUS_IN_PROGRESS) {
            return redirect()->route('ujian.siswa.hasil', [$ujian, $attempt]);
        }
        if ($attempt && $attempt->isLocked()) {
            return view('ujian.siswa.terkunci', compact('ujian', 'attempt'));
        }
        if ($attempt) {
            return redirect()->route('ujian.siswa.kerjakan', [$ujian, $attempt]);
        }

        return view('ujian.siswa.gate', compact('ujian', 'ujianKelas'));
    }

    public function start(Request $request, Ujian $ujian)
    {
        $siswa = $this->siswaAtauGagal($request);
        $ujianKelas = $ujian->kelas()->where('id_kelas', $siswa->id_kelas)->first();
        abort_unless($ujianKelas, 404);
        $this->authorize('take', $ujianKelas);

        $data = $request->validate(['token' => 'required|string']);
        if (!hash_equals((string) $ujianKelas->token_masuk, (string) $data['token'])) {
            return back()->withErrors(['token' => 'Token salah. Minta token yang benar ke guru/panitia ujian.']);
        }

        $existing = UjianAttempt::where('id_ujian_kelas', $ujianKelas->uuid)
            ->where('id_siswa', $request->user()->uuid)
            ->where('status', '!=', UjianAttempt::STATUS_DIBATALKAN)
            ->latest()->first();
        if ($existing) {
            return redirect()->route('ujian.siswa.kerjakan', [$ujian, $existing]);
        }

        $attempt = DB::transaction(function () use ($ujian, $ujianKelas, $request) {
            $soal = $ujian->soal()->with('opsi')->get();

            $urutanSoal = $ujian->acak_soal ? $soal->pluck('uuid')->shuffle()->values()->all() : $soal->pluck('uuid')->all();

            $urutanOpsi = [];
            foreach ($soal as $s) {
                if ($s->tipe === 'match') {
                    $jumlahPasangan = count($s->meta['pairs'] ?? []);
                    $idx = range(0, max(0, $jumlahPasangan - 1));
                    $urutanOpsi[$s->uuid] = $ujian->acak_opsi ? collect($idx)->shuffle()->values()->all() : $idx;
                } elseif ($s->butuhOpsi()) {
                    $ids = $s->opsi->pluck('uuid');
                    $urutanOpsi[$s->uuid] = $ujian->acak_opsi ? $ids->shuffle()->values()->all() : $ids->all();
                }
            }

            return UjianAttempt::create([
                'id_ujian_kelas'    => $ujianKelas->uuid,
                'id_siswa'          => $request->user()->uuid,
                'urutan_soal'       => $urutanSoal,
                'urutan_opsi'       => $urutanOpsi,
                'mulai_pada'        => now(),
                'batas_waktu_pada'  => now()->addMinutes($ujian->durasi_menit),
                'status'            => UjianAttempt::STATUS_IN_PROGRESS,
            ]);
        });

        return redirect()->route('ujian.siswa.kerjakan', [$ujian, $attempt]);
    }

    public function kerjakan(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        $this->pastikanMilikSiswa($request, $attempt);

        if ($attempt->isLocked()) {
            return view('ujian.siswa.terkunci', compact('ujian', 'attempt'));
        }
        if ($attempt->status !== UjianAttempt::STATUS_IN_PROGRESS) {
            return redirect()->route('ujian.siswa.hasil', [$ujian, $attempt]);
        }
        if ($attempt->isExpired()) {
            // JANGAN redirect ke gate() di sini — gate() akan redirect balik ke sini lagi
            // selama attempt masih 'in_progress' & belum lewat sweep cron ujian:auto-submit,
            // jadi finalisasi langsung di tempat supaya tidak loop tak berujung.
            app(\App\Services\UjianGrader::class)->autoSubmitKarenaWaktuHabis($attempt);
            return redirect()->route('ujian.siswa.hasil', [$ujian, $attempt]);
        }

        $soalById = $ujian->soal()->with('opsi')->get()->keyBy('uuid');
        $urutan = collect($attempt->urutan_soal)->map(fn ($id) => $soalById->get($id))->filter()->values();
        $jawabanTersimpan = UjianJawaban::where('id_attempt', $attempt->uuid)->get()->keyBy('id_soal');

        // Susun opsi tampil per soal sesuai urutan_opsi tersimpan, dan STRIP is_benar —
        // jawaban benar tidak boleh pernah terkirim ke browser siswa. teks_soal/opsi.teks
        // dibersihkan lagi lewat RichText::clean() (defense in depth — sudah dibersihkan saat
        // simpan di UjianSoalController juga) SEBELUM di-embed sbg JSON: konten ini dirender
        // client-side lewat x-html (bukan Blade {!! !!}), jadi sanitasi WAJIB terjadi di sini,
        // bukan cuma saat render, krn tak ada langkah render Blade lagi setelah titik ini.
        $soalTampil = $urutan->map(function (UjianSoal $s) use ($attempt) {
            $item = ['uuid' => $s->uuid, 'tipe' => $s->tipe, 'teks_soal' => RichText::clean($s->teks_soal), 'poin' => $s->poin];
            if ($s->tipe === 'match') {
                $pairs = $s->meta['pairs'] ?? [];
                $urutanIdx = $attempt->urutan_opsi[$s->uuid] ?? array_keys($pairs);
                $item['kiri'] = collect($pairs)->pluck('left')->map(fn ($t) => RichText::clean($t))->all();
                $item['kanan_acak'] = collect($urutanIdx)->map(fn ($i) => RichText::clean($pairs[$i]['right'] ?? ''))->all();
            } elseif ($s->butuhOpsi()) {
                $opsiById = $s->opsi->keyBy('uuid');
                $urutanOpsiUuid = $attempt->urutan_opsi[$s->uuid] ?? $s->opsi->pluck('uuid')->all();
                $item['opsi'] = collect($urutanOpsiUuid)->map(fn ($id) => ['uuid' => $id, 'teks' => $opsiById->get($id)?->teks_opsi])
                    ->filter(fn ($o) => $o['teks'] !== null)
                    ->map(fn ($o) => ['uuid' => $o['uuid'], 'teks' => RichText::clean($o['teks'])])
                    ->values()->all();
            }
            return $item;
        });

        return view('ujian.siswa.kerjakan', [
            'ujian' => $ujian, 'attempt' => $attempt, 'soalTampil' => $soalTampil, 'jawabanTersimpan' => $jawabanTersimpan,
        ]);
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

        $soal = UjianSoal::where('id_ujian', $ujian->uuid)->where('uuid', $data['id_soal'])->firstOrFail();

        UjianJawaban::updateOrCreate(
            ['id_attempt' => $attempt->uuid, 'id_soal' => $soal->uuid],
            [
                'id_opsi_dipilih'    => in_array($soal->tipe, ['mcq', 'true_false'], true) ? ($data['id_opsi_dipilih'] ?? null) : null,
                'opsi_dipilih_multi' => $soal->tipe === 'mcq_complex' ? ($data['opsi_dipilih_multi'] ?? []) : null,
                'jawaban_pasangan'   => $soal->tipe === 'match' ? ($data['jawaban_pasangan'] ?? []) : null,
                'jawaban_esai'       => $soal->tipe === 'essay' ? ($data['jawaban_esai'] ?? null) : null,
                'dijawab_pada'       => now(),
            ]
        );

        return response()->json(['ok' => true]);
    }

    public function laporKeluar(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        $this->pastikanMilikSiswa($request, $attempt);

        $data = $request->validate(['tipe' => 'required|in:keluar_fullscreen,ganti_tab']);

        if ($attempt->status === UjianAttempt::STATUS_IN_PROGRESS && !$attempt->isLocked()) {
            \App\Models\UjianPelanggaran::create(['id_attempt' => $attempt->uuid, 'id_siswa' => $attempt->id_siswa, 'tipe' => $data['tipe']]);
            $attempt->update(['dikunci' => true]);
        }

        return response()->json(['ok' => true]);
    }

    public function submit(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        $this->pastikanMilikSiswa($request, $attempt);
        abort_if($attempt->isLocked(), 403, 'Ujian terkunci — hubungi guru/panitia untuk membuka kembali.');
        abort_unless($attempt->status === UjianAttempt::STATUS_IN_PROGRESS, 422, 'Ujian ini sudah dikumpulkan.');

        DB::transaction(function () use ($attempt) {
            $attempt = UjianAttempt::where('uuid', $attempt->uuid)->lockForUpdate()->first();
            if ($attempt->status !== UjianAttempt::STATUS_IN_PROGRESS) {
                return;
            }
            // Penilaian objektif otomatis + transfer nilai (Fase 4) dipasang di sini
            // lewat UjianGrader::finalisasiObjektif() — utk saat ini cukup tutup attempt-nya.
            $attempt->update(['status' => UjianAttempt::STATUS_SUBMITTED, 'selesai_pada' => now()]);
            app(\App\Services\UjianGrader::class)->finalisasiObjektif($attempt->fresh());
        });

        return redirect()->route('ujian.siswa.hasil', [$ujian, $attempt]);
    }

    public function hasil(Request $request, Ujian $ujian, UjianAttempt $attempt)
    {
        $this->pastikanMilikSiswa($request, $attempt);

        return view('ujian.siswa.hasil', compact('ujian', 'attempt'));
    }

    private function pastikanMilikSiswa(Request $request, UjianAttempt $attempt): void
    {
        abort_unless($attempt->id_siswa === $request->user()->uuid, 403);
    }
}
