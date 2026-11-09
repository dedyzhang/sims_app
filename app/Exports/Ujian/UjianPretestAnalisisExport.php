<?php

namespace App\Exports\Ujian;

use App\Models\Setting;
use App\Models\Ujian;
use App\Models\UjianPretest;
use App\Models\UjianPretestJawaban;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Analisis Hasil Pre-Test — meniru format Excel UjianAnalisisExport real ujian.
 * 2 bagian:
 *  A. Analisis Nilai — 1 kolom per soal, 1/0/skor parsial, Jumlah, Rata-rata, L, TL,
 *     footer "Jumlah jawaban salah" & "Persentase Kesalahan(%)".
 *  B. Objektif (Jawaban) — Kunci vs Huruf jawaban peserta (A, B, C... / -).
 */
class UjianPretestAnalisisExport implements FromArray, WithTitle, WithEvents, WithStrictNullComparison
{
    private Collection $soalSemua;
    private Collection $soalObjektif;
    private Collection $pesertaList;
    private float $kkm;

    private int $rowJudul = 0;
    private int $rowSub = 0;
    private int $rowSeksiA = 0;
    private int $rowHeaderA = 0;
    private int $rowDataAwalA = 0;
    private int $rowDataAkhirA = 0;
    private int $rowFooterSalah = 0;
    private int $rowFooterPersen = 0;
    private int $rowSeksiB = 0;
    private int $rowHeaderB = 0;
    private int $rowKunciB = 0;
    private int $rowDataAwalB = 0;
    private int $rowDataAkhirB = 0;
    private int $kolomTerakhirA = 5;
    private int $kolomTerakhirB = 2;

    public function __construct(private Ujian $ujian, private UjianPretest $pretest)
    {
        $this->ujian->load(['soal' => fn ($q) => $q->orderBy('urutan'), 'soal.opsi', 'pelajaran']);
        $this->soalSemua = $this->ujian->soal;
        $this->soalObjektif = $this->soalSemua->where('tipe', '!=', 'essay')->values();
        $this->kkm = (float) ($this->ujian->pelajaran?->kkm ?? 75);
        $this->pesertaList = $this->pretest->peserta()
            ->where('status', 'submitted')
            ->orderBy('created_at')
            ->get();
    }

    private function hurufOpsi(int $index): string
    {
        return chr(65 + $index);
    }

    private function letakOpsi(object $soal): array
    {
        $peta = [];
        foreach ($soal->opsi as $i => $opsi) {
            $peta[$opsi->uuid] = $this->hurufOpsi($i);
        }
        return $peta;
    }

    private function hurufKunci(object $soal): string
    {
        foreach ($soal->opsi as $i => $opsi) {
            if ($opsi->is_benar) return $this->hurufOpsi($i);
        }
        return '-';
    }

    private function bangunPetaKolom(Collection $daftarSoal, bool $pecahPerItem = true): array
    {
        $peta = [];
        $kolom = 0;
        foreach ($daftarSoal as $soal) {
            $items = ($pecahPerItem && in_array($soal->tipe, ['mcq_complex', 'match'], true)) ? $soal->itemBenarList() : null;
            $span = $items ? max($items->count(), 1) : 1;
            $peta[] = ['soal' => $soal, 'start' => $kolom, 'span' => $span, 'items' => $items];
            $kolom += $span;
        }
        return ['peta' => $peta, 'total' => $kolom];
    }

    public function array(): array
    {
        $rows = [];
        $tambah = function (array $row) use (&$rows): int {
            $rows[] = $row;
            return count($rows);
        };
        $kosong = fn () => $tambah([null]);

        $namaSekolah = Setting::get('nama_sekolah', 'Sekolah');

        $jawabanByPeserta = UjianPretestJawaban::whereIn('id_peserta', $this->pesertaList->pluck('uuid'))
            ->get()
            ->groupBy('id_peserta');

        $nomorSoal = $this->soalSemua->values()->mapWithKeys(fn ($s, $i) => [$s->uuid => $i + 1]);

        ['peta' => $petaA, 'total' => $totalKolomA] = $this->bangunPetaKolom($this->soalSemua, pecahPerItem: false);
        ['peta' => $petaB, 'total' => $totalKolomB] = $this->bangunPetaKolom($this->soalObjektif, pecahPerItem: true);

        $kolomTipeA = [];
        foreach ($petaA as $info) {
            for ($k = 0; $k < $info['span']; $k++) {
                $kolomTipeA[] = $info['soal']->tipe === 'essay' ? 'essay' : 'objektif';
            }
        }

        $this->kolomTerakhirA = 2 + $totalKolomA + 4; // +4: Jumlah, Rata-rata, L, TL
        $this->kolomTerakhirB = 2 + max($totalKolomB, 1);

        $kolLabelInfo = $this->kolomTerakhirA - 1;
        $kolNilaiInfo = $kolLabelInfo + 1;
        $barisInfo = function (string $labelUtama, string $labelInfo, $nilaiInfo) use ($kolLabelInfo, $kolNilaiInfo) {
            $row = array_fill(0, $kolNilaiInfo, null);
            $row[0] = $labelUtama;
            $row[$kolLabelInfo - 1] = $labelInfo;
            $row[$kolNilaiInfo - 1] = $nilaiInfo;
            return $row;
        };

        $this->rowJudul = $tambah(['ANALISIS HASIL UJI COBA PRE-TEST']);
        $this->rowSub = $tambah([$namaSekolah . '  •  Diekspor: ' . now()->isoFormat('D MMMM Y, HH:mm') . ' WIB']);
        $kosong();

        $mapelLabel = $this->ujian->jenisLabel() . ' - ' . ($this->ujian->pelajaran?->nama ?? '-');
        $rowMapel = $tambah($barisInfo('Mata Pelajaran : ' . $mapelLabel, 'KKM', $this->kkm));
        $rowJudulPretest = $tambah($barisInfo('Pre-Test : ' . ($this->pretest->judul ?? $this->ujian->judul), 'Tuntas :', null));
        $rowTidakTuntas = $tambah($barisInfo('Total Peserta Selesai : ' . $this->pesertaList->count() . ' orang', 'Tidak Tuntas :', null));
        $kosong();

        // ===== Bagian A: Analisis Nilai =====
        $this->rowSeksiA = $tambah(['A. Analisis Nilai']);

        $headerA = ['No', 'Nama Peserta'];
        $headerA = array_merge($headerA, array_fill(0, $totalKolomA, null));
        foreach ($petaA as $info) {
            $headerA[2 + $info['start']] = $nomorSoal[$info['soal']->uuid];
        }
        $headerA[] = 'Jumlah'; $headerA[] = 'Rata-rata'; $headerA[] = 'L'; $headerA[] = 'TL';
        $this->rowHeaderA = $tambah($headerA);

        $footerSalah = array_fill(0, $totalKolomA, 0);
        $totalPeserta = $this->pesertaList->count();
        $tuntas = 0; $tidakTuntas = 0;
        $this->rowDataAwalA = 0;

        foreach ($this->pesertaList as $idx => $peserta) {
            $jawabanBySoal = $jawabanByPeserta->get($peserta->uuid, collect())->keyBy('id_soal');
            $row = [$idx + 1, $peserta->nama . ($peserta->identitas ? ' (' . $peserta->identitas . ')' : '')];

            $skorMentahPeserta = 0.0;
            $col = 0;
            foreach ($petaA as $info) {
                $soal = $info['soal'];
                $jw = $jawabanBySoal->get($soal->uuid);
                if ($jw) $skorMentahPeserta += (float) $jw->skor_diperoleh;

                if ($soal->tipe === 'essay') {
                    $row[] = $jw ? (float) $jw->skor_diperoleh : 0;
                    $col++;
                    continue;
                }

                $benar = $jw?->is_benar ? 1 : 0;
                if (!$benar) $footerSalah[$col]++;

                $row[] = in_array($soal->tipe, ['mcq_complex', 'match'], true) ? (float) ($jw?->skor_diperoleh ?? 0) : $benar;
                $col++;
            }

            $skor = (float) $peserta->total_skor;
            $l = $skor >= $this->kkm ? 1 : 0;
            $tl = $l ? 0 : 1;
            if ($l) $tuntas++; else $tidakTuntas++;

            $row[] = round($skorMentahPeserta, 1);
            $row[] = round($skor, 1);
            $row[] = $l;
            $row[] = $tl;

            $rowNo = $tambah($row);
            if ($this->rowDataAwalA === 0) $this->rowDataAwalA = $rowNo;
            $this->rowDataAkhirA = $rowNo;
        }

        if ($this->rowDataAwalA === 0) $this->rowDataAwalA = $this->rowHeaderA + 1;

        $rows[$rowJudulPretest - 1][$kolNilaiInfo - 1] = $tuntas;
        $rows[$rowTidakTuntas - 1][$kolNilaiInfo - 1] = $tidakTuntas;

        $footerSalahRow = ['Jumlah jawaban salah', null];
        $footerPersenRow = ['Persentase Kesalahan(%)', null];
        foreach ($footerSalah as $col => $s) {
            if ($kolomTipeA[$col] === 'essay') {
                $footerSalahRow[] = null;
                $footerPersenRow[] = null;
            } else {
                $footerSalahRow[] = $s;
                $footerPersenRow[] = $totalPeserta > 0 ? round($s / $totalPeserta * 100) : 0;
            }
        }
        $this->rowFooterSalah = $tambah($footerSalahRow);
        $this->rowFooterPersen = $tambah($footerPersenRow);

        $kosong();

        // ===== Bagian B: Objektif (Jawaban) =====
        $this->rowSeksiB = $tambah(['B. Objektif (Jawaban)']);

        $headerB = ['No', 'Nama Peserta'];
        if ($totalKolomB > 0) {
            $headerB = array_merge($headerB, array_fill(0, $totalKolomB, null));
            foreach ($petaB as $info) {
                $headerB[2 + $info['start']] = $nomorSoal[$info['soal']->uuid];
            }
        } else {
            $headerB[] = '(tidak ada soal pilihan objektif)';
        }
        $this->rowHeaderB = $tambah($headerB);

        $kunciRow = [null, 'Kunci'];
        foreach ($petaB as $info) {
            $soal = $info['soal'];
            if ($info['items']) {
                foreach ($info['items'] as $item) { $kunciRow[] = $item['label']; }
            } else {
                $kunciRow[] = $this->hurufKunci($soal);
            }
        }
        $this->rowKunciB = $tambah($kunciRow);

        $this->rowDataAwalB = 0;
        foreach ($this->pesertaList as $idx => $peserta) {
            $jawabanBySoal = $jawabanByPeserta->get($peserta->uuid, collect())->keyBy('id_soal');
            $row = [$idx + 1, $peserta->nama . ($peserta->identitas ? ' (' . $peserta->identitas . ')' : '')];

            foreach ($petaB as $info) {
                $soal = $info['soal'];
                $jw = $jawabanBySoal->get($soal->uuid);

                if ($info['items']) {
                    foreach ($info['items'] as $item) {
                        $row[] = ($jw && $soal->itemDipilihBenar($jw, $item)) ? $item['label'] : '-';
                    }
                } else {
                    $petaOpsi = $this->letakOpsi($soal);
                    $row[] = $jw?->id_opsi_dipilih ? ($petaOpsi[$jw->id_opsi_dipilih] ?? '-') : '-';
                }
            }
            $rowNo = $tambah($row);
            if ($this->rowDataAwalB === 0) $this->rowDataAwalB = $rowNo;
            $this->rowDataAkhirB = $rowNo;
        }

        if ($this->rowDataAwalB === 0) $this->rowDataAwalB = $this->rowKunciB + 1;

        if ($this->soalSemua->where('tipe', 'essay')->count() > 0 || $this->soalSemua->whereIn('tipe', ['mcq_complex', 'match'])->count() > 0) {
            $kosong();
            $tambah(['Ket: kolom soal esai di Analisis Nilai berisi skor mentah (bukan 1/0) dan tidak dihitung di baris Jumlah jawaban salah/Persentase Kesalahan; soal esai tidak muncul di Objektif (Jawaban). Soal pilihan ganda kompleks & mencocokkan di Analisis Nilai tetap 1 kolom (benar/salah keseluruhan) — breakdown per-opsi/pasangan benar HANYA muncul di Objektif (Jawaban).']);
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Analisis Pre-Test';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colA = Coordinate::stringFromColumnIndex($this->kolomTerakhirA);
                $colB = Coordinate::stringFromColumnIndex($this->kolomTerakhirB);

                $sheet->mergeCells("A{$this->rowJudul}:{$colA}{$this->rowJudul}");
                $sheet->mergeCells("A{$this->rowSub}:{$colA}{$this->rowSub}");
                $sheet->getStyle("A{$this->rowJudul}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1E293B']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getStyle("A{$this->rowSub}")->applyFromArray([
                    'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '64748B']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $sheet->getStyle("A{$this->rowSeksiA}")->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E293B']]]);
                $sheet->getStyle("A{$this->rowSeksiB}")->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E293B']]]);

                foreach ([$this->rowHeaderA => $colA, $this->rowHeaderB => $colB] as $row => $lastCol) {
                    $sheet->getStyle("A{$row}:{$lastCol}{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '4338CA']]],
                    ]);
                }

                $sheet->getStyle("A{$this->rowKunciB}:{$colB}{$this->rowKunciB}")->applyFromArray([
                    'font' => ['bold' => true, 'italic' => true, 'color' => ['rgb' => '1E293B']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E7FF']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'C7D2FE']]],
                ]);

                for ($row = $this->rowDataAwalA; $row <= $this->rowDataAkhirA; $row++) {
                    $sheet->getStyle("A{$row}:{$colA}{$row}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $sheet->getStyle("B{$row}")->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]]);
                }

                for ($row = $this->rowDataAwalB; $row <= $this->rowDataAkhirB; $row++) {
                    $sheet->getStyle("A{$row}:{$colB}{$row}")->applyFromArray([
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']]],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    ]);
                    $sheet->getStyle("B{$row}")->applyFromArray(['alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT]]);
                }

                $sheet->getStyle("A{$this->rowFooterSalah}:{$colA}{$this->rowFooterPersen}")->applyFromArray([
                    'font' => ['bold' => true, 'size' => 9],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FEF3C7']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FDE68A']]],
                ]);

                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(26);
                foreach (range(3, max($this->kolomTerakhirA, $this->kolomTerakhirB)) as $c) {
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(5);
                }
                $sheet->freezePane('C' . $this->rowDataAwalA);

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToPage(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->setBreak('A' . $this->rowSeksiB, Worksheet::BREAK_ROW);
            },
        ];
    }
}
