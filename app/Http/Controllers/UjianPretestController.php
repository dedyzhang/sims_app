<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\Ujian;
use App\Models\UjianPretest;
use App\Models\UjianPretestPeserta;
use App\Support\SchoolLetterhead;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class UjianPretestController extends Controller
{
    public function index(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->load('pelajaran');

        // Buat pre-test otomatis jika belum ada (default is_active false, ditutup sampai dibuka oleh guru)
        $pretest = $ujian->pretest;
        if (! $pretest) {
            $pretest = UjianPretest::create([
                'id_ujian' => $ujian->uuid,
                'token' => Str::upper(Str::random(8)),
                'judul' => 'Pre-Test: ' . $ujian->judul,
                'is_active' => false,
                'durasi_menit' => $ujian->durasi_menit,
                'acak_soal' => $ujian->acak_soal,
                'acak_opsi' => $ujian->acak_opsi,
                'tampilkan_nilai' => true,
                'tampilkan_pembahasan' => false,
                'created_by' => $request->user()?->uuid,
            ]);
        }

        $pesertaList = $pretest->peserta()->latest('created_at')->get();

        // Ringkasan statistik
        $totalPeserta = $pesertaList->count();
        $pesertaSelesai = $pesertaList->where('status', 'submitted');
        $totalSelesai = $pesertaSelesai->count();
        $rataRata = $totalSelesai > 0 ? round($pesertaSelesai->avg('total_skor'), 1) : 0;
        $nilaiTertinggi = $totalSelesai > 0 ? $pesertaSelesai->max('total_skor') : 0;
        $nilaiTerendah = $totalSelesai > 0 ? $pesertaSelesai->min('total_skor') : 0;

        $joinUrl = route('pretest.public.show', ['token' => $pretest->token]);
        $qrSvg = QrCode::format('svg')->size(220)->margin(1)->generate($joinUrl);
        $qrUri = 'data:image/svg+xml;base64,' . base64_encode($qrSvg);

        return view('ujian.pretest.index', compact(
            'ujian',
            'pretest',
            'pesertaList',
            'totalPeserta',
            'totalSelesai',
            'rataRata',
            'nilaiTertinggi',
            'nilaiTerendah',
            'joinUrl',
            'qrUri'
        ));
    }

    public function update(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $pretest = $ujian->pretest;
        abort_unless($pretest, 404);

        $validated = $request->validate([
            'judul' => 'required|string|max:255',
            'durasi_menit' => 'required|integer|min:0|max:300',
            'acak_soal' => 'nullable|boolean',
            'acak_opsi' => 'nullable|boolean',
            'tampilkan_nilai' => 'nullable|boolean',
            'tampilkan_pembahasan' => 'nullable|boolean',
        ]);

        $pretest->update([
            'judul' => $validated['judul'],
            'durasi_menit' => $validated['durasi_menit'],
            'acak_soal' => $request->boolean('acak_soal'),
            'acak_opsi' => $request->boolean('acak_opsi'),
            'tampilkan_nilai' => $request->boolean('tampilkan_nilai'),
            'tampilkan_pembahasan' => $request->boolean('tampilkan_pembahasan'),
        ]);

        return back()->with('success', 'Pengaturan Pre-Test berhasil diperbarui.');
    }

    public function toggle(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $pretest = $ujian->pretest;
        abort_unless($pretest, 404);

        $pretest->update(['is_active' => ! $pretest->is_active]);

        $statusText = $pretest->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return back()->with('success', "Akses Pre-Test berhasil {$statusText}.");
    }

    public function regenerateToken(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $pretest = $ujian->pretest;
        abort_unless($pretest, 404);

        $newToken = Str::upper(Str::random(8));
        while (UjianPretest::where('token', $newToken)->exists()) {
            $newToken = Str::upper(Str::random(8));
        }

        $pretest->update(['token' => $newToken]);

        return back()->with('success', 'Link & Barcode Pre-Test berhasil diperbarui dengan token baru.');
    }

    public function pesertaDetail(Request $request, Ujian $ujian, UjianPretestPeserta $peserta)
    {
        $this->authorize('manage', $ujian);
        $pretest = $ujian->pretest;
        abort_unless($pretest && $peserta->id_pretest === $pretest->uuid, 404);

        $ujian->load(['soal.opsi']);
        $soalList = $ujian->soal()->orderBy('urutan')->with('opsi')->get();
        $jawabanBySoal = $peserta->jawaban()->with('opsi')->get()->keyBy('id_soal');

        return view('ujian.pretest.detail-peserta', compact('ujian', 'pretest', 'peserta', 'soalList', 'jawabanBySoal'));
    }

    public function destroyPeserta(Request $request, Ujian $ujian, UjianPretestPeserta $peserta)
    {
        $this->authorize('manage', $ujian);
        $pretest = $ujian->pretest;
        abort_unless($pretest && $peserta->id_pretest === $pretest->uuid, 404);

        $peserta->delete();

        return back()->with('success', "Data hasil pre-test peserta {$peserta->nama} berhasil dihapus.");
    }

    public function resetSemuaPeserta(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $pretest = $ujian->pretest;
        abort_unless($pretest, 404);

        $count = $pretest->peserta()->count();
        $pretest->peserta()->delete();

        return back()->with('success', "Seluruh data ({$count} peserta) pre-test berhasil dibersihkan.");
    }

    public function unduhAnalisis(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $pretest = $ujian->pretest;
        abort_unless($pretest, 404, 'Pre-Test belum dibuat untuk ujian ini.');

        $namaFile = 'Analisis Hasil Pre-Test - ' . $ujian->judul . '.xlsx';
        return \Maatwebsite\Excel\Facades\Excel::download(new \App\Exports\Ujian\UjianPretestAnalisisExport($ujian, $pretest), $namaFile);
    }

    public function cetakAnalisis(Request $request, Ujian $ujian)
    {
        $this->authorize('manage', $ujian);
        $ujian->load(['pelajaran', 'soal' => fn ($q) => $q->orderBy('urutan'), 'soal.opsi']);
        $pretest = $ujian->pretest;
        abort_unless($pretest, 404, 'Pre-Test belum dibuat untuk ujian ini.');

        $pesertaList = $pretest->peserta()
            ->where('status', 'submitted')
            ->orderBy('created_at')
            ->get();

        $totalPeserta = $pesertaList->count();
        $totalSkorSemua = $pesertaList->sum('total_skor');
        $rataRata = $totalPeserta > 0 ? round($totalSkorSemua / $totalPeserta, 1) : 0;
        $nilaiTertinggi = $totalPeserta > 0 ? $pesertaList->max('total_skor') : 0;
        $nilaiTerendah = $totalPeserta > 0 ? $pesertaList->min('total_skor') : 0;

        $kkm = (float) ($ujian->pelajaran?->kkm ?? 75);
        $jumlahTuntas = $pesertaList->where('total_skor', '>=', $kkm)->count();
        $jumlahTidakTuntas = $totalPeserta - $jumlahTuntas;
        $persenTuntas = $totalPeserta > 0 ? round(($jumlahTuntas / $totalPeserta) * 100, 1) : 0;

        $soalSemua = $ujian->soal;
        $soalObjektif = $soalSemua->where('tipe', '!=', 'essay')->values();

        // Ambil seluruh jawaban dari peserta yang sudah submit
        $allJawaban = \App\Models\UjianPretestJawaban::whereIn('id_peserta', $pesertaList->pluck('uuid'))->get();
        $jawabanByPeserta = $allJawaban->groupBy('id_peserta');

        $hurufKunci = function ($soal) {
            foreach ($soal->opsi as $i => $opsi) {
                if ($opsi->is_benar) return chr(65 + $i);
            }
            return '-';
        };

        $letakOpsi = function ($soal) {
            $peta = [];
            foreach ($soal->opsi as $i => $opsi) {
                $peta[$opsi->uuid] = chr(65 + $i);
            }
            return $peta;
        };

        // Peta Kolom A (1 kolom per nomor butir soal)
        $petaA = $soalSemua->map(function ($soal, $i) {
            return [
                'soal' => $soal,
                'nomor' => $i + 1,
            ];
        });

        // Peta Kolom B (Objektif - pecah sub-kolom jika mcq_complex atau match)
        $petaB = $soalObjektif->map(function ($soal, $i) {
            $items = in_array($soal->tipe, ['mcq_complex', 'match'], true) ? $soal->itemBenarList() : null;
            return [
                'soal' => $soal,
                'nomor' => $i + 1,
                'items' => $items,
            ];
        });

        // Bangun matriks Bagian A & Bagian B
        $matriksA = [];
        $footerSalahA = array_fill(0, $soalSemua->count(), 0);
        $matriksB = [];

        foreach ($pesertaList as $idx => $peserta) {
            $jawabanBySoal = $jawabanByPeserta->get($peserta->uuid, collect())->keyBy('id_soal');

            // Baris A
            $skorMentah = 0.0;
            $kolomNilai = [];
            foreach ($soalSemua as $colIdx => $soal) {
                $jw = $jawabanBySoal->get($soal->uuid);
                if ($jw) $skorMentah += (float) $jw->skor_diperoleh;

                if ($soal->tipe === 'essay') {
                    $kolomNilai[] = [
                        'tipe' => 'essay',
                        'nilai' => $jw ? (float) $jw->skor_diperoleh : 0,
                    ];
                    continue;
                }

                $benar = $jw?->is_benar ? 1 : 0;
                if (! $benar) {
                    $footerSalahA[$colIdx]++;
                }

                $kolomNilai[] = [
                    'tipe' => 'objektif',
                    'nilai' => in_array($soal->tipe, ['mcq_complex', 'match'], true) ? (float) ($jw?->skor_diperoleh ?? 0) : $benar,
                    'is_benar' => (bool) $jw?->is_benar,
                ];
            }

            $skorFinal = (float) $peserta->total_skor;
            $isLulus = $skorFinal >= $kkm;

            $matriksA[] = [
                'no' => $idx + 1,
                'peserta' => $peserta,
                'kolom' => $kolomNilai,
                'jumlah' => round($skorMentah, 1),
                'rata_rata' => round($skorFinal, 1),
                'l' => $isLulus ? 1 : 0,
                'tl' => $isLulus ? 0 : 1,
            ];

            // Baris B
            $kolomJawabanB = [];
            foreach ($petaB as $info) {
                $soal = $info['soal'];
                $jw = $jawabanBySoal->get($soal->uuid);

                if ($info['items']) {
                    foreach ($info['items'] as $item) {
                        $kolomJawabanB[] = ($jw && $soal->itemDipilihBenar($jw, $item)) ? $item['label'] : '-';
                    }
                } else {
                    $peta = $letakOpsi($soal);
                    $kolomJawabanB[] = $jw?->id_opsi_dipilih ? ($peta[$jw->id_opsi_dipilih] ?? '-') : '-';
                }
            }

            $matriksB[] = [
                'no' => $idx + 1,
                'peserta' => $peserta,
                'jawaban' => $kolomJawabanB,
            ];
        }

        // Kunci Bagian B
        $kunciB = [];
        foreach ($petaB as $info) {
            $soal = $info['soal'];
            if ($info['items']) {
                foreach ($info['items'] as $item) {
                    $kunciB[] = $item['label'];
                }
            } else {
                $kunciB[] = $hurufKunci($soal);
            }
        }

        // Hitung persentase kesalahan Bagian A
        $footerPersenA = [];
        foreach ($footerSalahA as $colIdx => $salah) {
            $soal = $soalSemua[$colIdx];
            if ($soal->tipe === 'essay') {
                $footerPersenA[] = '-';
            } else {
                $footerPersenA[] = $totalPeserta > 0 ? round(($salah / $totalPeserta) * 100) . '%' : '0%';
            }
        }

        $sekolah = Setting::get('sekolah_nama', 'Sekolah');
        $sekolahLogoUrl = Setting::get('sekolah_logo');
        $kopLogoKiri = SchoolLetterhead::logoKiriUrl();
        $kopLogoKanan = SchoolLetterhead::logoKananUrl();
        $kopTeksHtml = SchoolLetterhead::kopTeksHtml();

        return view('ujian.pretest.cetak-analisis', compact(
            'ujian',
            'pretest',
            'pesertaList',
            'totalPeserta',
            'rataRata',
            'nilaiTertinggi',
            'nilaiTerendah',
            'kkm',
            'jumlahTuntas',
            'jumlahTidakTuntas',
            'persenTuntas',
            'soalSemua',
            'soalObjektif',
            'petaA',
            'petaB',
            'matriksA',
            'footerSalahA',
            'footerPersenA',
            'kunciB',
            'matriksB',
            'sekolah',
            'sekolahLogoUrl',
            'kopLogoKiri',
            'kopLogoKanan',
            'kopTeksHtml'
        ));
    }
}
