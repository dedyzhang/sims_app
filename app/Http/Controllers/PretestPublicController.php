<?php

namespace App\Http\Controllers;

use App\Models\UjianPretest;
use App\Models\UjianPretestJawaban;
use App\Models\UjianPretestPeserta;
use App\Models\UjianSoal;
use App\Services\UjianGrader;
use App\Support\RichText;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PretestPublicController extends Controller
{
    private function resolvePretest(string $token): UjianPretest
    {
        $pretest = UjianPretest::with(['ujian.pelajaran'])->where('token', Str::upper($token))->first();
        abort_unless($pretest, 404, 'Pre-Test tidak ditemukan atau link sudah tidak berlaku.');

        return $pretest;
    }

    private function resolvePeserta(Request $request, UjianPretest $pretest): ?UjianPretestPeserta
    {
        $guestToken = $request->query('p') ?? $request->input('guest_token') ?? $request->session()->get('pretest_guest_' . $pretest->token);

        if (! $guestToken) {
            return null;
        }

        return UjianPretestPeserta::where('id_pretest', $pretest->uuid)
            ->where('guest_token', $guestToken)
            ->first();
    }

    public function show(Request $request, string $token)
    {
        $pretest = $this->resolvePretest($token);

        if (! $pretest->is_active) {
            return view('ujian.pretest.tutup', compact('pretest'));
        }

        $peserta = $this->resolvePeserta($request, $pretest);
        if ($peserta) {
            if ($peserta->status === 'submitted') {
                return redirect()->route('pretest.public.selesai', ['token' => $token, 'p' => $peserta->guest_token]);
            }

            return redirect()->route('pretest.public.kerjakan', ['token' => $token, 'p' => $peserta->guest_token]);
        }

        return view('ujian.pretest.welcome', compact('pretest'));
    }

    public function mulai(Request $request, string $token)
    {
        $pretest = $this->resolvePretest($token);
        abort_unless($pretest->is_active, 403, 'Pre-Test sedang dinonaktifkan.');

        $data = $request->validate([
            'nama' => 'required|string|max:100',
            'identitas' => 'required|string|max:100',
            'nomor_kontak' => 'nullable|string|max:30',
        ]);

        $ujian = $pretest->ujian;
        $soalList = $ujian->soal()->with('opsi')->orderBy('urutan')->get();
        abort_if($soalList->isEmpty(), 422, 'Ujian ini belum memiliki butir soal.');

        // Acak soal
        $urutanSoal = $pretest->acak_soal
            ? $soalList->pluck('uuid')->shuffle()->values()->all()
            : $soalList->pluck('uuid')->all();

        // Acak opsi per butir soal
        $urutanOpsi = [];
        foreach ($soalList as $s) {
            if ($s->tipe === 'match') {
                $jumlahPasangan = count($s->meta['pairs'] ?? []);
                $idx = range(0, max(0, $jumlahPasangan - 1));
                $urutanOpsi[$s->uuid] = $pretest->acak_opsi ? collect($idx)->shuffle()->values()->all() : $idx;
            } elseif ($s->butuhOpsi()) {
                $ids = $s->opsi->pluck('uuid');
                $urutanOpsi[$s->uuid] = $pretest->acak_opsi ? $ids->shuffle()->values()->all() : $ids->all();
            }
        }

        $now = now();
        $batasWaktu = $pretest->durasi_menit > 0 ? $now->copy()->addMinutes($pretest->durasi_menit) : null;

        $peserta = UjianPretestPeserta::create([
            'id_pretest' => $pretest->uuid,
            'guest_token' => Str::random(32),
            'nama' => trim($data['nama']),
            'identitas' => trim($data['identitas']),
            'nomor_kontak' => $data['nomor_kontak'] ?? null,
            'urutan_soal' => $urutanSoal,
            'urutan_opsi' => $urutanOpsi,
            'mulai_pada' => $now,
            'batas_waktu_pada' => $batasWaktu,
            'status' => 'in_progress',
            'total_soal' => $soalList->count(),
        ]);

        $request->session()->put('pretest_guest_' . $pretest->token, $peserta->guest_token);

        return redirect()->route('pretest.public.kerjakan', ['token' => $token, 'p' => $peserta->guest_token]);
    }

    public function kerjakan(Request $request, string $token)
    {
        $pretest = $this->resolvePretest($token);
        $peserta = $this->resolvePeserta($request, $pretest);
        abort_unless($peserta, 403, 'Sesi pengerjaan tidak valid. Silakan isi identitas terlebih dahulu.');

        if ($peserta->status === 'submitted') {
            return redirect()->route('pretest.public.selesai', ['token' => $token, 'p' => $peserta->guest_token]);
        }

        if ($peserta->isExpired()) {
            $this->finalisasiPenilaian($pretest, $peserta);

            return redirect()->route('pretest.public.selesai', ['token' => $token, 'p' => $peserta->guest_token]);
        }

        $ujian = $pretest->ujian;
        $soalById = $ujian->soal()->with('opsi')->get()->keyBy('uuid');
        $urutan = collect($peserta->urutan_soal)->map(fn ($id) => $soalById->get($id))->filter()->values();

        $jawabanTersimpan = UjianPretestJawaban::where('id_peserta', $peserta->uuid)
            ->get()
            ->keyBy('id_soal');

        // Susun soal tampil sesuai urutan simpanan & bersihkan konten
        $soalTampil = $urutan->map(function (UjianSoal $s) use ($peserta) {
            $item = [
                'uuid' => $s->uuid,
                'tipe' => $s->tipe,
                'teks_soal' => RichText::clean($s->teks_soal),
                'poin' => $s->poinEfektif(),
            ];

            if ($s->tipe === 'match') {
                $pairs = $s->meta['pairs'] ?? [];
                $urutanIdx = $peserta->urutan_opsi[$s->uuid] ?? array_keys($pairs);
                $item['kiri'] = collect($pairs)->pluck('left')->map(fn ($t) => RichText::clean($t))->all();
                $item['kanan_acak'] = collect($urutanIdx)->map(fn ($i) => RichText::clean($pairs[$i]['right'] ?? ''))->all();
            } elseif ($s->butuhOpsi()) {
                $opsiById = $s->opsi->keyBy('uuid');
                $urutanOpsiUuid = $peserta->urutan_opsi[$s->uuid] ?? $s->opsi->pluck('uuid')->all();
                $item['opsi'] = collect($urutanOpsiUuid)->map(fn ($id) => [
                    'uuid' => $id,
                    'teks' => $opsiById->get($id)?->teks_opsi,
                ])
                ->filter(fn ($o) => $o['teks'] !== null)
                ->map(fn ($o) => ['uuid' => $o['uuid'], 'teks' => RichText::clean($o['teks'])])
                ->values()->all();
            }

            return $item;
        });

        return view('ujian.pretest.kerjakan', compact('pretest', 'peserta', 'soalTampil', 'jawabanTersimpan'));
    }

    public function jawab(Request $request, string $token)
    {
        $pretest = $this->resolvePretest($token);
        $peserta = $this->resolvePeserta($request, $pretest);
        abort_unless($peserta, 403, 'Sesi pengerjaan tidak valid.');

        if ($peserta->status === 'submitted' || $peserta->isExpired()) {
            return response()->json(['ok' => false, 'message' => 'Waktu pengerjaan sudah berakhir.'], 422);
        }

        // Dukung baik single jawaban maupun batch sync
        if ($request->has('jawaban') && is_array($request->input('jawaban'))) {
            $daftarJawaban = $request->input('jawaban');
            foreach ($daftarJawaban as $item) {
                if (empty($item['id_soal'])) {
                    continue;
                }
                $this->simpanSatuJawaban($peserta, $item);
            }
        } elseif ($request->has('id_soal')) {
            $this->simpanSatuJawaban($peserta, $request->all());
        }

        return response()->json(['ok' => true]);
    }

    private function simpanSatuJawaban(UjianPretestPeserta $peserta, array $data): void
    {
        $soal = UjianSoal::where('uuid', $data['id_soal'] ?? '')->first();
        if (! $soal) {
            return;
        }

        UjianPretestJawaban::updateOrCreate(
            ['id_peserta' => $peserta->uuid, 'id_soal' => $soal->uuid],
            [
                'id_opsi_dipilih' => in_array($soal->tipe, ['mcq', 'true_false'], true) ? ($data['id_opsi_dipilih'] ?? null) : null,
                'opsi_dipilih_multi' => $soal->tipe === 'mcq_complex' ? ($data['opsi_dipilih_multi'] ?? []) : null,
                'jawaban_pasangan' => $soal->tipe === 'match' ? ($data['jawaban_pasangan'] ?? []) : null,
                'jawaban_esai' => $soal->tipe === 'essay' ? ($data['jawaban_esai'] ?? null) : null,
                'dijawab_pada' => now(),
            ]
        );
    }

    public function kumpul(Request $request, string $token)
    {
        $pretest = $this->resolvePretest($token);
        $peserta = $this->resolvePeserta($request, $pretest);
        abort_unless($peserta, 403, 'Sesi pengerjaan tidak valid.');

        if ($peserta->status !== 'submitted') {
            $this->finalisasiPenilaian($pretest, $peserta);
        }

        $redirectUrl = route('pretest.public.selesai', ['token' => $token, 'p' => $peserta->guest_token]);

        if ($request->wantsJson()) {
            return response()->json(['redirect' => $redirectUrl]);
        }

        return redirect($redirectUrl);
    }

    private function finalisasiPenilaian(UjianPretest $pretest, UjianPretestPeserta $peserta): void
    {
        $ujian = $pretest->ujian;
        $soalList = $ujian->soal()->with('opsi')->get();
        $jawabanList = UjianPretestJawaban::where('id_peserta', $peserta->uuid)->get()->keyBy('id_soal');

        $grader = app(UjianGrader::class);
        $totalSkorMentah = 0;
        $jumlahBenar = 0;
        $jumlahSalah = 0;

        foreach ($soalList as $soal) {
            $jawaban = $jawabanList->get($soal->uuid);

            if (! $jawaban) {
                $jumlahSalah++;
                continue;
            }

            if ($soal->tipe === 'essay') {
                // Esai pretest dihitung jika dinilai manual, default 0
                continue;
            }

            $hasil = $grader->scoreObjective($soal, $jawaban);
            $jawaban->update([
                'is_benar' => $hasil['is_benar'],
                'skor_diperoleh' => $hasil['skor'],
            ]);

            $totalSkorMentah += $hasil['skor'];

            if ($hasil['is_benar']) {
                $jumlahBenar++;
            } else {
                $jumlahSalah++;
            }
        }

        $totalPoin = (int) $soalList->sum(fn ($s) => $s->poinEfektif());
        $modeSkor = $ujian->mode_skor ?? 'rata_rata';
        $totalSkorFinal = UjianGrader::normalisasiSkor($totalSkorMentah, $totalPoin, $modeSkor);

        $peserta->update([
            'status' => 'submitted',
            'selesai_pada' => now(),
            'skor_objektif' => $totalSkorMentah,
            'total_skor' => $totalSkorFinal,
            'jumlah_benar' => $jumlahBenar,
            'jumlah_salah' => $jumlahSalah,
        ]);
    }

    public function selesai(Request $request, string $token)
    {
        $pretest = $this->resolvePretest($token);
        $peserta = $this->resolvePeserta($request, $pretest);
        abort_unless($peserta, 404, 'Data peserta tidak ditemukan.');

        $ujian = $pretest->ujian->load(['pelajaran', 'soal' => fn ($q) => $q->orderBy('urutan'), 'soal.opsi']);
        $jawabanBySoal = $peserta->jawaban()->with('opsi')->get()->keyBy('id_soal');

        return view('ujian.pretest.selesai', compact('pretest', 'peserta', 'ujian', 'jawabanBySoal'));
    }
}
