<?php

namespace App\Survei;

use Illuminate\Support\Collection;

final class AnalisisGuru
{
    public const MINIMUM = 5;

    public function butir(Periode $periode, string $jenis, Collection $respons): Collection
    {
        return collect($periode->butir($jenis))->map(function (string $text, int $index) use ($periode, $jenis, $respons): array {
            $jawaban = $respons->map(fn (Respons $row) => (int) ($row->jawaban[$index] ?? 0));
            $skor = $jawaban->map(fn (int $nilai) => $periode->skor($jenis, $index, $nilai))
                ->filter(fn (?int $nilai) => $nilai !== null);

            return [
                'index' => $index,
                'text' => $text,
                'jumlah_skor' => $skor->sum(),
                'rata' => $skor->avg(),
                'n' => $skor->count(),
                'tidak' => $jawaban->filter(fn (int $nilai) => $nilai === 0)->count(),
            ];
        });
    }

    public function rekap(Periode $periode, ?string $kelasId = null, ?string $guruId = null, bool $sertakanCatatan = false): Collection
    {
        return $periode->targets()
            ->when($kelasId !== null, fn ($query) => $query->where('kelas_id', $kelasId))
            ->when($guruId !== null, fn ($query) => $query->where('guru_id', $guruId))
            ->with($sertakanCatatan ? 'respons:target_id,jawaban,komentar,kendala' : 'respons:target_id,jawaban')
            ->orderBy('guru_nama')->orderBy('kelas_nama')->orderBy('pelajaran_nama')
            ->get()
            ->groupBy('guru_id')
            ->map(function (Collection $targets) use ($periode, $sertakanCatatan): array {
                return [
                    'nama' => $targets->first()->guru_nama,
                    'peran' => $targets->groupBy('jenis')->sortKeys()->map(
                        fn (Collection $bagian, string $jenis) => $this->ringkasPeran($periode, $jenis, $bagian, $sertakanCatatan)
                    ),
                ];
            });
    }

    public function skorKelas(Collection $gurus): array
    {
        return collect(['mapel', 'wali'])->mapWithKeys(function (string $jenis) use ($gurus): array {
            $ringkasan = $gurus->pluck("peran.$jenis")->filter();
            $n = $ringkasan->sum('jawaban_dinilai');

            return [$jenis => [
                'jawaban_dinilai' => $n,
                'skor' => $n > 0 ? $ringkasan->sum('jumlah_skor') / $n : null,
            ]];
        })->all();
    }

    private function ringkasPeran(Periode $periode, string $jenis, Collection $targets, bool $sertakanCatatan): array
    {
        $butir = $targets->flatMap(fn (Target $target) => $this->butir($periode, $jenis, $target->respons)
            ->filter(fn (array $row) => $row['n'] >= self::MINIMUM)->values())
            ->groupBy('index')
            ->map(function (Collection $rows, int $index) use ($periode, $jenis): array {
                $n = $rows->sum('n');
                $jumlahSkor = $rows->sum('jumlah_skor');

                return [
                    'index' => $index,
                    'text' => $rows->first()['text'],
                    'n' => $n,
                    'jumlah_skor' => $jumlahSkor,
                    'rata' => $jumlahSkor / $n,
                    'negatif' => in_array($index, $periode->butirNegatif($jenis), true),
                ];
            });
        $jawabanDinilai = $butir->sum('n');
        $jumlahSkor = $butir->sum('jumlah_skor');
        $skor = $jawabanDinilai > 0 ? $jumlahSkor / $jawabanDinilai : null;

        return [
            'targets' => $targets,
            'jawaban_dinilai' => $jawabanDinilai,
            'jumlah_skor' => $jumlahSkor,
            'skor' => $skor,
            'nilai' => $skor !== null ? $skor * 20 : null,
            // Satu siswa dapat mengisi beberapa sasaran; ini bukan jumlah siswa unik.
            'isian' => $targets->sum(fn (Target $target) => $target->respons->count()),
            'kelas' => $targets->pluck('kelas_id')->unique()->count(),
            'butir' => $butir->sortKeys()->values(),
            'catatan' => $sertakanCatatan ? $this->catatan($periode, $jenis, $targets) : collect(),
            'kekuatan' => $butir->filter(fn (array $row) => $row['rata'] >= 4)->sortByDesc('rata')->take(3),
            'perbaikan' => $butir->filter(fn (array $row) => $row['rata'] < 3.5)->sortBy('rata')->take(3),
        ];
    }

    private function catatan(Periode $periode, string $jenis, Collection $targets): Collection
    {
        // Kelompok kecil tetap disembunyikan, meskipun digabung dengan kelas lain.
        $respons = $targets->filter(fn (Target $target) => $target->respons->count() >= self::MINIMUM)
            ->flatMap(fn (Target $target) => $target->respons);
        $pertanyaan = collect($periode->pertanyaanTerbuka($jenis));
        if ($jenis === 'mapel') {
            $pertanyaan->put('kendala_lainnya', 'Kendala lainnya');
        }

        return $pertanyaan->map(function (string $label, $index) use ($respons): array {
            $catatan = $respons->map(fn (Respons $row) => $index === 'kendala_lainnya'
                ? ($row->kendala['lainnya'] ?? null)
                : ($row->komentar[$index] ?? null))
                ->filter(fn ($teks) => is_string($teks) && trim($teks) !== '')
                ->map(fn (string $teks) => trim($teks))
                ->groupBy(fn (string $teks) => mb_strtolower(preg_replace('/\s+/u', ' ', $teks)))
                ->map(fn (Collection $sama): array => ['teks' => $sama->first(), 'jumlah' => $sama->count()])
                ->sortByDesc('jumlah')->values();

            return ['pertanyaan' => $label, 'jumlah' => $catatan->sum('jumlah'), 'daftar' => $catatan];
        })->filter(fn (array $bagian) => $bagian['jumlah'] > 0)->values();
    }
}
