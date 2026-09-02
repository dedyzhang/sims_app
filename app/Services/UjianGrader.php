<?php

namespace App\Services;

use App\Models\UjianAttempt;
use App\Models\UjianJawaban;
use App\Models\UjianSoal;
use Illuminate\Support\Facades\DB;

/**
 * Penilaian otomatis untuk tipe soal objektif (mcq/mcq_complex/true_false/match).
 * Esai TIDAK pernah dinilai di sini — selalu menunggu guru (lihat
 * UjianGradingController & selesaikanPenilaianEssai(), keduanya Fase 4).
 */
class UjianGrader
{
    /** @return array{is_benar: ?bool, skor: float} */
    public function scoreObjective(UjianSoal $soal, UjianJawaban $jawaban): array
    {
        return match ($soal->tipe) {
            'mcq', 'true_false' => $this->scoreSingleSelect($soal, $jawaban),
            'mcq_complex'        => $this->scoreMultiSelect($soal, $jawaban),
            'match'              => $this->scoreMatch($soal, $jawaban),
            default              => ['is_benar' => null, 'skor' => 0], // essay
        };
    }

    private function scoreSingleSelect(UjianSoal $soal, UjianJawaban $jawaban): array
    {
        $opsiBenar = $soal->opsi->firstWhere('is_benar', true);
        $benar = $opsiBenar && $jawaban->id_opsi_dipilih === $opsiBenar->uuid;

        return ['is_benar' => $benar, 'skor' => $benar ? $soal->poin : 0];
    }

    /**
     * is_benar SELALU berarti "100% benar" (dipakai pembahasan & Excel Analisis), TAK
     * berubah walau skor_mode='proporsional' — yang berubah cuma skor_diperoleh.
     *
     * skor_mode='proporsional': "Poin" berarti poin PER OPSI BENAR (bukan total soal) —
     * perkalian langsung poin × jumlah opsi benar yg dipilih (tanpa hukuman opsi salah
     * yg ikut dipilih). skor_mode='all_or_nothing' (default): "Poin" adalah TOTAL soal
     * apa adanya (poinEfektif() mengembalikan poin mentah utk mode ini, TANPA dikali
     * jumlah opsi) — semua-atau-tidak-sama-sekali tak punya konsep "per item".
     */
    private function scoreMultiSelect(UjianSoal $soal, UjianJawaban $jawaban): array
    {
        $benarUuids = $soal->opsi->where('is_benar', true)->pluck('uuid')->sort()->values();
        $dipilih = collect($jawaban->opsi_dipilih_multi ?? [])->sort()->values();
        $benar = $benarUuids->all() === $dipilih->all();

        if ($soal->skor_mode === 'proporsional' && $benarUuids->isNotEmpty()) {
            $benarDipilih = $benarUuids->intersect($dipilih)->count();
            $skor = round($soal->poin * $benarDipilih, 2);

            return ['is_benar' => $benar, 'skor' => $skor];
        }

        // all_or_nothing (default): himpunan opsi terpilih harus PERSIS sama dgn opsi benar.
        return ['is_benar' => $benar, 'skor' => $benar ? $soal->poinEfektif() : 0];
    }

    /** Sama semantik poin dgn scoreMultiSelect() (lihat komentar di sana) — proporsional: poin PER PASANGAN; all_or_nothing: poin TOTAL apa adanya. */
    private function scoreMatch(UjianSoal $soal, UjianJawaban $jawaban): array
    {
        $pairs = $soal->meta['pairs'] ?? [];
        $total = count($pairs);
        if ($total === 0) {
            return ['is_benar' => false, 'skor' => 0];
        }

        $jawabanPasangan = $jawaban->jawaban_pasangan ?? [];
        $benarCount = 0;
        foreach ($pairs as $p) {
            if (($jawabanPasangan[$p['left']] ?? null) === $p['right']) {
                $benarCount++;
            }
        }
        $benar = $benarCount === $total;

        if ($soal->skor_mode === 'all_or_nothing') {
            return ['is_benar' => $benar, 'skor' => $benar ? $soal->poinEfektif() : 0];
        }

        // proporsional (default): poin × jumlah pasangan benar.
        $skor = round($soal->poin * $benarCount, 2);

        return ['is_benar' => $benar, 'skor' => $skor];
    }

    /**
     * Nilai semua jawaban non-esai pada attempt, tandai butuh_penilaian_manual kalau
     * ada esai. Kalau TIDAK ada esai sama sekali, langsung final (status='dinilai')
     * dan transfer ke Nilai PTS/PAS/Sumatif otomatis lewat UjianNilaiTransfer.
     */
    public function finalisasiObjektif(UjianAttempt $attempt): void
    {
        $soalById = UjianSoal::where('id_ujian', $attempt->ujianKelas->ujian->uuid)->with('opsi')->get()->keyBy('uuid');
        $jawabanList = UjianJawaban::where('id_attempt', $attempt->uuid)->get();

        $skorObjektif = 0;

        foreach ($jawabanList as $jawaban) {
            $soal = $soalById->get($jawaban->id_soal);
            if (!$soal || $soal->tipe === 'essay') {
                $skorObjektif += (float) $jawaban->skor_diperoleh; // essay: 0 sampai dinilai guru
                continue;
            }

            $hasil = $this->scoreObjective($soal, $jawaban);
            $jawaban->update(['is_benar' => $hasil['is_benar'], 'skor_diperoleh' => $hasil['skor']]);
            $skorObjektif += $hasil['skor'];
        }

        $adaEsaiBelumDinilai = $this->adaEsaiBelumDinilai($soalById, $jawabanList);
        $update = ['skor_objektif' => $skorObjektif, 'butuh_penilaian_manual' => $adaEsaiBelumDinilai];

        if (!$adaEsaiBelumDinilai) {
            $attempt->update($update);
            $this->finalisasi($attempt->fresh(), $soalById);
            return;
        }

        $attempt->update($update);
    }

    /**
     * Dipanggil guru setelah menilai (salah satu) jawaban esai. Kalau itu ternyata
     * esai TERAKHIR yg tersisa (semua soal esai sudah terjawab & dinilai), attempt
     * difinalisasi penuh dan nilai ditransfer otomatis. Kalau masih ada esai lain yg
     * belum dinilai, attempt tetap 'submitted' menunggu sisanya.
     */
    public function selesaikanPenilaianEssai(UjianAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt) {
            $locked = UjianAttempt::where('uuid', $attempt->uuid)->lockForUpdate()->first();
            if (!$locked || $locked->status !== UjianAttempt::STATUS_SUBMITTED) {
                return;
            }

            $soalById = UjianSoal::where('id_ujian', $locked->ujianKelas->ujian->uuid)->get()->keyBy('uuid');
            $jawabanList = UjianJawaban::where('id_attempt', $locked->uuid)->get();

            if ($this->adaEsaiBelumDinilai($soalById, $jawabanList)) {
                return;
            }

            $this->finalisasi($locked, $soalById, $jawabanList);
        });
    }

    /**
     * Fallback sinkron kalau attempt lewat batas_waktu_pada — dipakai baik oleh
     * siswa sendiri (fallback saat render/poll, lihat UjianSiswaController) maupun
     * command sapuan `ujian:auto-submit` (Fase 4). selesai_pada dipatok ke
     * batas_waktu_pada (bukan now()) demi keadilan skor waktu.
     */
    public function autoSubmitKarenaWaktuHabis(UjianAttempt $attempt): void
    {
        DB::transaction(function () use ($attempt) {
            $locked = UjianAttempt::where('uuid', $attempt->uuid)->lockForUpdate()->first();
            if (!$locked || $locked->status !== UjianAttempt::STATUS_IN_PROGRESS) {
                return;
            }
            $locked->update([
                'status'       => UjianAttempt::STATUS_SUBMITTED,
                'selesai_pada' => $locked->batas_waktu_pada ?? now(),
                'auto_submit'  => true,
            ]);
            $this->finalisasiObjektif($locked->fresh());
        });
    }

    private function adaEsaiBelumDinilai($soalById, $jawabanList): bool
    {
        $soalEssai = $soalById->where('tipe', 'essay');
        if ($soalEssai->isEmpty()) {
            return false;
        }

        $jawabanEssaiBySoal = $jawabanList->filter(fn ($j) => $soalById->get($j->id_soal)?->tipe === 'essay')->keyBy('id_soal');

        foreach ($soalEssai as $soal) {
            $jawaban = $jawabanEssaiBySoal->get($soal->uuid);
            if (!$jawaban || !$jawaban->dinilai_manual) {
                return true; // belum dijawab sama sekali, ATAU sudah dijawab tapi belum dinilai guru
            }
        }

        return false;
    }

    /**
     * Normalisasi skor mentah ke skala final sesuai setting per-mapel (Pelajaran::
     * mode_skor_ujian): 'rata_rata' (default) menormalisasi ke skala 0-100 (skor ÷
     * totalPoin × 100); 'jumlah' memakai total poin apa adanya (bisa >100 kalau total
     * poin soal ujian ini >100). Dipakai baik utk skor FINAL (finalisasi(), semua soal
     * termasuk esai) maupun skor SEMENTARA (UjianAttempt::skorSementara(), esai msh 0)
     * — skala HARUS sama persis di keduanya, supaya angkanya "naik" begitu esai selesai
     * dinilai, bukan lompat skala.
     */
    public static function normalisasiSkor(float $skorMentah, int $totalPoin, string $modeSkor): float
    {
        return $modeSkor === 'jumlah'
            ? round($skorMentah, 2)
            : ($totalPoin > 0 ? round($skorMentah / $totalPoin * 100, 2) : 0);
    }

    /** Hitung total_skor final, tutup attempt, transfer nilai otomatis. */
    private function finalisasi(UjianAttempt $attempt, $soalById = null, $jawabanList = null): void
    {
        $soalById ??= UjianSoal::where('id_ujian', $attempt->ujianKelas->ujian->uuid)->get()->keyBy('uuid');
        $jawabanList ??= UjianJawaban::where('id_attempt', $attempt->uuid)->get();

        $skorTotal = $jawabanList->sum(fn ($j) => (float) $j->skor_diperoleh);
        $totalPoin = (int) $soalById->sum(fn (UjianSoal $s) => $s->poinEfektif());
        $modeSkor = $attempt->ujianKelas->ujian->pelajaran?->mode_skor_ujian ?? 'rata_rata';

        $totalSkorFinal = self::normalisasiSkor($skorTotal, $totalPoin, $modeSkor);

        $attempt->update([
            'total_skor'             => $totalSkorFinal,
            'status'                 => UjianAttempt::STATUS_DINILAI,
            'butuh_penilaian_manual' => false,
        ]);

        app(UjianNilaiTransfer::class)->transfer($attempt->fresh());
    }
}
