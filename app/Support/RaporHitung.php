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
}
