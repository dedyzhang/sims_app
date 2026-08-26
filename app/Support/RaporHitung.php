<?php

namespace App\Support;

use App\Models\Materi;
use App\Models\NilaiFormatif;
use App\Models\NilaiPas;
use App\Models\NilaiRapor;
use App\Models\NilaiSumatif;

class RaporHitung
{
    /**
     * Peta nilai rapor akhir per siswa untuk satu penugasan (ngajar):
     * override NilaiRapor bila ada, selain itu hitung dari formatif/sumatif/PAS.
     *
     * @return array<string,int>  [id_siswa => nilai]
     */
    public static function nilaiMap($ngajar, $siswas, $idSemester, string $rumus): array
    {
        $materi = Materi::with('tujuan')->where('id_ngajar', $ngajar->uuid)
            ->where('id_semester', $idSemester)->where('aktif', true)->get();
        $tupeIds = $materi->flatMap(fn ($m) => $m->tujuan)->pluck('uuid');

        $fmt = [];
        foreach (NilaiFormatif::whereIn('id_tupe', $tupeIds)->get() as $r) { $fmt[$r->id_siswa][] = (float) $r->nilai; }
        $sum = [];
        foreach (NilaiSumatif::whereIn('id_materi', $materi->pluck('uuid'))->get() as $r) { $sum[$r->id_siswa][] = (float) $r->nilai; }
        $pas = NilaiPas::where('id_ngajar', $ngajar->uuid)->where('id_semester', $idSemester)->pluck('nilai', 'id_siswa')->toArray();
        $ov  = NilaiRapor::where('id_ngajar', $ngajar->uuid)->where('id_semester', $idSemester)->pluck('nilai', 'id_siswa')->toArray();

        $out = [];
        foreach ($siswas as $s) {
            if (isset($ov[$s->uuid]) && $ov[$s->uuid] !== null) { $out[$s->uuid] = (int) $ov[$s->uuid]; continue; }
            $h = Penilaian::hitung($fmt[$s->uuid] ?? [], $sum[$s->uuid] ?? [], isset($pas[$s->uuid]) ? (float) $pas[$s->uuid] : null, $rumus);
            $out[$s->uuid] = $h['rapor'];
        }
        return $out;
    }

    /**
     * Olahan lengkap rapor per siswa: nilai + predikat + deskripsi capaian.
     * @return array<string,array{nilai:int,predikat:string,pos:string,neg:string}>
     */
    public static function olah($ngajar, $siswas, $idSemester, string $rumus, int $kktp): array
    {
        $materi = Materi::with('tujuan')->where('id_ngajar', $ngajar->uuid)
            ->where('id_semester', $idSemester)->where('aktif', true)->get();
        $tupeAll = $materi->flatMap(fn ($m) => $m->tujuan);
        $tupeText = $tupeAll->pluck('tupe', 'uuid');

        $fmt = [];
        foreach (NilaiFormatif::whereIn('id_tupe', $tupeAll->pluck('uuid'))->get() as $r) { $fmt[$r->id_siswa][$r->id_tupe] = (float) $r->nilai; }
        $sum = [];
        foreach (NilaiSumatif::whereIn('id_materi', $materi->pluck('uuid'))->get() as $r) { $sum[$r->id_siswa][] = (float) $r->nilai; }
        $pas = NilaiPas::where('id_ngajar', $ngajar->uuid)->where('id_semester', $idSemester)->pluck('nilai', 'id_siswa')->toArray();
        $rapor = NilaiRapor::where('id_ngajar', $ngajar->uuid)->where('id_semester', $idSemester)->get()->keyBy('id_siswa');

        $out = [];
        foreach ($siswas as $s) {
            $h = Penilaian::hitung(array_values($fmt[$s->uuid] ?? []), $sum[$s->uuid] ?? [], isset($pas[$s->uuid]) ? (float) $pas[$s->uuid] : null, $rumus);
            $rf = $rapor->get($s->uuid);
            $nilai = ($rf && $rf->nilai !== null) ? (int) $rf->nilai : $h['rapor'];
            $pred = Penilaian::predikat($nilai, $kktp);

            $dPos = $dNeg = '';
            $skorTupe = $fmt[$s->uuid] ?? [];
            if (!empty($skorTupe)) {
                arsort($skorTupe); $maxTupe = array_key_first($skorTupe);
                asort($skorTupe);  $minTupe = array_key_first($skorTupe);
                $predMax = Penilaian::predikat((int) round($skorTupe[$maxTupe]), $kktp);
                $dPos = Penilaian::kalimatPositif($predMax, (string) ($tupeText[$maxTupe] ?? ''));
                $dNeg = Penilaian::kalimatNegatif((string) ($tupeText[$minTupe] ?? ''));
            }
            $out[$s->uuid] = [
                'nilai'    => $nilai,
                'predikat' => $pred,
                'pos'      => $rf?->deskripsi_positif ?? $dPos,
                'neg'      => $rf?->deskripsi_negatif ?? $dNeg,
            ];
        }
        return $out;
    }

    /**
     * Versi BULK dari olah(): hitung SEMUA $ngajars sekaligus lewat query yg di-whereIn lintas
     * ngajar (Materi/Formatif/Sumatif/PAS/Rapor masing2 SEKALI, bukan 5 query × N ngajar di
     * dalam loop). Hasil per (ngajar, siswa) IDENTIK dgn memanggil olah() satu-per-satu —
     * dikunci oleh RaporHitungBulkTest.
     *
     * KUNCI KEBENARAN: formatif digabung lintas semua ngajar di $fmtAll, jadi per ngajar WAJIB
     * di-array_intersect_key dgn tupe milik ngajar itu (di olah() ini implisit krn query-nya
     * per-ngajar) — kalau tidak, skor/deskripsi formatif bocor antar-mapel.
     *
     * @return array<string,array<string,array{nilai:int,predikat:string,pos:string,neg:string}>>  [id_ngajar => [id_siswa => olahan]]
     */
    public static function olahBanyak($ngajars, $siswas, $idSemester, string $rumus): array
    {
        $idNgajar = collect($ngajars)->pluck('uuid');

        $materiByNgajar = Materi::with('tujuan')
            ->whereIn('id_ngajar', $idNgajar)
            ->where('id_semester', $idSemester)->where('aktif', true)->get()
            ->groupBy('id_ngajar');

        $allMateri = $materiByNgajar->flatten(1);
        $allTupe = $allMateri->flatMap(fn ($m) => $m->tujuan);
        $tupeText = $allTupe->pluck('tupe', 'uuid');

        $fmtAll = [];
        foreach (NilaiFormatif::whereIn('id_tupe', $allTupe->pluck('uuid'))->get() as $r) {
            $fmtAll[$r->id_siswa][$r->id_tupe] = (float) $r->nilai;
        }
        $sumByMateri = [];
        foreach (NilaiSumatif::whereIn('id_materi', $allMateri->pluck('uuid'))->get() as $r) {
            $sumByMateri[$r->id_materi][$r->id_siswa][] = (float) $r->nilai;
        }
        $pasByNgajar = NilaiPas::whereIn('id_ngajar', $idNgajar)->where('id_semester', $idSemester)->get()->groupBy('id_ngajar');
        $raporByNgajar = NilaiRapor::whereIn('id_ngajar', $idNgajar)->where('id_semester', $idSemester)->get()->groupBy('id_ngajar');

        $out = [];
        foreach ($ngajars as $ng) {
            $kktp = $ng->kktp;
            $materi = $materiByNgajar->get($ng->uuid) ?? collect();
            $tupeIds = $materi->flatMap(fn ($m) => $m->tujuan)->pluck('uuid')->all();
            $materiIds = $materi->pluck('uuid')->all();
            $pas = ($pasByNgajar->get($ng->uuid) ?? collect())->pluck('nilai', 'id_siswa')->toArray();
            $rapor = ($raporByNgajar->get($ng->uuid) ?? collect())->keyBy('id_siswa');

            $rows = [];
            foreach ($siswas as $s) {
                // Formatif siswa ini DIBATASI ke tupe milik ngajar ini (cegah bocor antar-mapel).
                $skorTupe = array_intersect_key($fmtAll[$s->uuid] ?? [], array_flip($tupeIds));
                // Sumatif siswa ini digabung dari SEMUA materi milik ngajar ini.
                $sumSiswa = [];
                foreach ($materiIds as $mid) {
                    if (isset($sumByMateri[$mid][$s->uuid])) {
                        $sumSiswa = array_merge($sumSiswa, $sumByMateri[$mid][$s->uuid]);
                    }
                }

                $h = Penilaian::hitung(array_values($skorTupe), $sumSiswa, isset($pas[$s->uuid]) ? (float) $pas[$s->uuid] : null, $rumus);
                $rf = $rapor->get($s->uuid);
                $nilai = ($rf && $rf->nilai !== null) ? (int) $rf->nilai : $h['rapor'];
                $pred = Penilaian::predikat($nilai, $kktp);

                $dPos = $dNeg = '';
                if (!empty($skorTupe)) {
                    $tmp = $skorTupe;
                    arsort($tmp); $maxTupe = array_key_first($tmp);
                    asort($tmp);  $minTupe = array_key_first($tmp);
                    $predMax = Penilaian::predikat((int) round($tmp[$maxTupe]), $kktp);
                    $dPos = Penilaian::kalimatPositif($predMax, (string) ($tupeText[$maxTupe] ?? ''));
                    $dNeg = Penilaian::kalimatNegatif((string) ($tupeText[$minTupe] ?? ''));
                }
                $rows[$s->uuid] = [
                    'nilai'    => $nilai,
                    'predikat' => $pred,
                    'pos'      => $rf?->deskripsi_positif ?? $dPos,
                    'neg'      => $rf?->deskripsi_negatif ?? $dNeg,
                ];
            }
            $out[$ng->uuid] = $rows;
        }
        return $out;
    }
}
