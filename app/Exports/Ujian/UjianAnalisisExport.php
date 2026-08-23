<?php

namespace App\Exports\Ujian;

use App\Models\Setting;
use App\Models\Ujian;
use App\Models\UjianJawaban;
use App\Models\UjianKelas;
use App\Services\UjianRoster;
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
 * Analisis Hasil Ujian satu kelas — meniru format Excel analisis yang sudah dipakai
 * sekolah. 2 bagian:
 *  A. Analisis Nilai — 1 kolom per SOAL (bukan per-opsi/pasangan — breakdown per-item
 *     SENGAJA HANYA di Bagian B, Bagian A dijaga ringkas utk rekap cepat). mcq/true_false
 *     tampil 1/0 (selalu semua-atau-tidak, tak ada skor parsial); mcq_complex/match TAMPIL
 *     SKOR yg didapat (float, bisa parsial di mode skor_mode='proporsional'), TAPI baris
 *     "Jumlah jawaban salah"/"Persentase Kesalahan(%)" tetap hitung berdasarkan is_benar
 *     (semua-atau-tidak) utk kolom itu, TERPISAH dari nilai yg ditampilkan; essay dapat
 *     kolom sendiri berisi skor MENTAH, dikecualikan dari kedua baris footer itu.
 *     Kolom akhir: Jumlah (sum skor MENTAH semua soal, dihitung ulang di sini — SELALU
 *     penjumlahan apa adanya, TAK terikat Pelajaran::mode_skor_ujian), Rata-rata
 *     (attempt->total_skor apa adanya — nilai INI yg dipakai baris L/TL vs KKM, tak
 *     berubah), L, TL.
 *  B. Objektif (Jawaban) — SEMUA tipe objektif (mcq/true_false/mcq_complex/match) tampil;
 *     mcq_complex/match dipecah jadi N sub-kolom (N = jumlah opsi/pasangan benar, lihat
 *     UjianSoal::itemBenarList()), baris kunci = label item benar, baris siswa = label itu
 *     KALAU item itu spesifik didapat benar, kalau tidak → '-'. Essay TETAP tak muncul di
 *     sini (tak ada representasi huruf yg masuk akal).
 * Halaman dipaksa landscape A4 dgn page break antara Bagian A & B (registerEvents()).
 * Huruf/label item diambil dari urutan KANONIK (opsi.urutan / meta['pairs'] apa adanya),
 * bukan urutan acak per-siswa, supaya "A" berarti item yang sama utk semua siswa.
 *
 * PENTING #1: baris ditambahkan dengan APPEND SEKUENSIAL ($rows[] = ...), bukan lompat index
 * (mis. $rows[10] = ...). Maatwebsite\Excel\Helpers\ArrayHelper::ensureMultipleRows()
 * MEMBUANG baris array kosong `[]` (dan otomatis "mengempiskan" gap index yg tak pernah
 * di-assign) — jadi baris kosong spacer HARUS `[null]`, dan nomor baris "penting" (utk
 * merge/styling di registerEvents) HARUS dicatat dari count($rows) tepat setelah append,
 * bukan dihitung lewat aritmatika, supaya tak pernah meleset lagi kalau urutan berubah.
 *
 * PENTING #2: class ini WAJIB implements WithStrictNullComparison — Maatwebsite/PhpSpreadsheet
 * defaultnya membandingkan tiap nilai sel dgn null pakai `==` (longgar), dan `0 == null` itu
 * TRUE di PHP, jadi angka 0 literal (skor 0, kolom "salah"/"TL"=0, dst) akan ikut DIANGGAP
 * kosong dan gagal ditulis kalau interface ini tak dipasang.
 */
class UjianAnalisisExport implements FromArray, WithTitle, WithEvents, WithStrictNullComparison
{
    private $soalSemua;
    private $soalObjektif;
    private $roster;
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

    public function __construct(private Ujian $ujian, private UjianKelas $ujianKelas)
    {
        $this->ujian->load(['soal' => fn ($q) => $q->orderBy('urutan'), 'soal.opsi', 'pelajaran']);
        $this->soalSemua = $this->ujian->soal;
        $this->soalObjektif = $this->soalSemua->where('tipe', '!=', 'essay')->values();
        $this->kkm = (float) ($this->ujian->pelajaran?->kkm ?? 0);
        $this->roster = app(UjianRoster::class)->untukKelas(collect([$this->ujianKelas]));
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

    /**
     * Bangun peta kolom utk satu daftar soal — tiap entri: soal, kolom-mulai (0-based relatif
     * thd grup), lebar (span), & itemBenarList() (null kalau soal ini TIDAK dipecah per-item
     * — span-nya selalu 1, dirender lewat jalur lama hurufKunci/letakOpsi/is_benar apa
     * adanya). $pecahPerItem=false dipakai Bagian A (Analisis Nilai tetap 1 kolom per soal,
     * termasuk utk mcq_complex/match) — breakdown per-opsi/pasangan HANYA di Bagian B.
     */
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
        /** Append satu baris, kembalikan nomor barisnya (1-based) — SATU-SATUNYA cara menulis ke $rows. */
        $tambah = function (array $row) use (&$rows): int {
            $rows[] = $row;
            return count($rows);
        };
        $kosong = fn () => $tambah([null]);

        $namaSekolah = Setting::get('nama_sekolah', 'Sekolah');
        $kelasLabel = trim(($this->ujianKelas->kelas?->tingkat ?? '') . ($this->ujianKelas->kelas?->kelas ?? ''));
        $tingkat = $this->ujianKelas->kelas?->tingkat;

        $jawabanByAttempt = UjianJawaban::whereIn('id_attempt', $this->roster->pluck('attempt.uuid')->filter())
            ->get()->groupBy('id_attempt');

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

        // KKM/Tuntas/Tidak Tuntas SENGAJA sejajar dgn kolom Jumlah/L/TL Bagian A (bukan
        // max(A,B)) — Bagian B bisa lebih lebar krn breakdown per-opsi/pasangan, tapi info
        // box ini konsepnya "ringkasan Bagian A", jadi posisinya harus nempel di situ juga,
        // tak boleh ikut terdorong jauh ke kanan oleh lebar Bagian B.
        $kolLabelInfo = $this->kolomTerakhirA + 2;
        $kolNilaiInfo = $kolLabelInfo + 2;
        $barisInfo = function (string $labelUtama, string $labelInfo, $nilaiInfo) use ($kolLabelInfo, $kolNilaiInfo) {
            $row = array_fill(0, $kolNilaiInfo, null);
            $row[0] = $labelUtama;
            $row[$kolLabelInfo - 1] = $labelInfo;
            $row[$kolNilaiInfo - 1] = $nilaiInfo;
            return $row;
        };

        $this->rowJudul = $tambah(['ANALISIS HASIL UJIAN']);
        $this->rowSub = $tambah([$namaSekolah . '  •  Diekspor: ' . now()->isoFormat('D MMMM Y, HH:mm') . ' WIB']);
        $kosong();
        $rowMapel = $tambah($barisInfo('Mata Pelajaran : ' . $this->ujian->jenisLabel() . ' - ' . ($this->ujian->pelajaran?->nama ?? '-') . ' Kelas ' . $tingkat, 'KKM', $this->kkm));
        $rowKelas = $tambah($barisInfo('Kelas : ' . $kelasLabel, 'Tuntas :', null));
        $rowTidakTuntas = $tambah($barisInfo('', 'Tidak Tuntas :', null));
        $kosong();

        // ===== Bagian A: Analisis Nilai =====
        $this->rowSeksiA = $tambah(['A. Analisis Nilai']);

        $headerA = ['No', 'Nama Siswa'];
        $headerA = array_merge($headerA, array_fill(0, $totalKolomA, null));
        foreach ($petaA as $info) {
            $headerA[2 + $info['start']] = $nomorSoal[$info['soal']->uuid];
        }
        $headerA[] = 'Jumlah'; $headerA[] = 'Rata-rata'; $headerA[] = 'L'; $headerA[] = 'TL';
        $this->rowHeaderA = $tambah($headerA);

        $footerSalah = array_fill(0, $totalKolomA, 0);
        $jumlahAttempt = 0;
        $tuntas = 0; $tidakTuntas = 0;
        $this->rowDataAwalA = 0;

        foreach ($this->roster as $idx => $barisRoster) {
            $siswa = $barisRoster['siswa'];
            $attempt = $barisRoster['attempt'];
            $jawabanBySoal = $attempt ? ($jawabanByAttempt->get($attempt->uuid, collect())->keyBy('id_soal')) : collect();

            $row = [$idx + 1, $siswa->nama];
            if ($attempt) $jumlahAttempt++;

            // Jumlah skor MENTAH (poin apa adanya, dijumlah langsung dari skor_diperoleh tiap
            // jawaban) — SELALU dihitung fresh di sini, TAK pernah dari attempt->total_skor
            // (yg nilainya tergantung Pelajaran::mode_skor_ujian, bisa sudah dinormalisasi ke
            // skala 100 utk mode 'rata_rata'). Kolom "Rata-rata" di bawah tetap pakai
            // attempt->total_skor apa adanya (dan itu jugalah yg dipakai baris L/TL vs KKM,
            // TAK berubah dari sebelumnya) — "Jumlah" murni informasi tambahan.
            $skorMentahSiswa = 0.0;

            $col = 0;
            foreach ($petaA as $info) {
                $soal = $info['soal'];
                $jw = $attempt ? $jawabanBySoal->get($soal->uuid) : null;
                if ($attempt && $jw) $skorMentahSiswa += (float) $jw->skor_diperoleh;

                if ($soal->tipe === 'essay') {
                    $row[] = $attempt ? ($jw ? (float) $jw->skor_diperoleh : 0) : '-';
                    $col++;
                    continue;
                }
                if (!$attempt) { $row[] = '-'; $col++; continue; }

                $benar = $jw?->is_benar ? 1 : 0;
                if (!$benar) $footerSalah[$col]++;
                // mcq_complex/match: tampilkan SKOR yg didapat (bisa parsial di mode
                // proporsional), bukan 1/0 — mcq/true_false tetap 1/0 apa adanya (memang
                // selalu semua-atau-tidak, tak ada skor parsial utk ditampilkan).
                $row[] = in_array($soal->tipe, ['mcq_complex', 'match'], true) ? (float) ($jw?->skor_diperoleh ?? 0) : $benar;
                $col++;
            }

            $skor = $attempt ? (float) $attempt->total_skor : 0;
            $l = $skor >= $this->kkm ? 1 : 0;
            $tl = $l ? 0 : 1;
            if ($attempt) { if ($l) $tuntas++; else $tidakTuntas++; }
            $row[] = round($skorMentahSiswa, 1); $row[] = round($skor, 1); $row[] = $l; $row[] = $tl;

            $rowNo = $tambah($row);
            if ($this->rowDataAwalA === 0) $this->rowDataAwalA = $rowNo;
            $this->rowDataAkhirA = $rowNo;
        }
        if ($this->rowDataAwalA === 0) $this->rowDataAwalA = $this->rowHeaderA + 1;

        $rows[$rowKelas - 1][$kolNilaiInfo - 1] = $tuntas;
        $rows[$rowTidakTuntas - 1][$kolNilaiInfo - 1] = $tidakTuntas;

        $footerSalahRow = ['Jumlah jawaban salah', null];
        $footerPersenRow = ['Persentase Kesalahan(%)', null];
        foreach ($footerSalah as $col => $s) {
            if ($kolomTipeA[$col] === 'essay') {
                $footerSalahRow[] = null;
                $footerPersenRow[] = null;
            } else {
                $footerSalahRow[] = $s;
                $footerPersenRow[] = $jumlahAttempt > 0 ? round($s / $jumlahAttempt * 100) : 0;
            }
        }
        $this->rowFooterSalah = $tambah($footerSalahRow);
        $this->rowFooterPersen = $tambah($footerPersenRow);

        $kosong();

        // ===== Bagian B: Objektif (Jawaban) =====
        $this->rowSeksiB = $tambah(['B. Objektif (Jawaban)']);

        $headerB = ['No', 'Nama'];
        if ($totalKolomB > 0) {
            $headerB = array_merge($headerB, array_fill(0, $totalKolomB, null));
            foreach ($petaB as $info) {
                $headerB[2 + $info['start']] = $nomorSoal[$info['soal']->uuid];
            }
        } else {
            $headerB[] = '(tidak ada soal pilihan tunggal)';
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
        foreach ($this->roster as $idx => $barisRoster) {
            $siswa = $barisRoster['siswa'];
            $attempt = $barisRoster['attempt'];
            $jawabanBySoal = $attempt ? ($jawabanByAttempt->get($attempt->uuid, collect())->keyBy('id_soal')) : collect();

            $row = [$idx + 1, $siswa->nama];
            foreach ($petaB as $info) {
                $soal = $info['soal'];
                $jw = $attempt ? $jawabanBySoal->get($soal->uuid) : null;

                if ($info['items']) {
                    foreach ($info['items'] as $item) {
                        if (!$attempt) { $row[] = '-'; continue; }
                        $row[] = ($jw && $soal->itemDipilihBenar($jw, $item)) ? $item['label'] : '-';
                    }
                } else {
                    if (!$attempt) { $row[] = '-'; continue; }
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
        $kelasLabel = trim(($this->ujianKelas->kelas?->tingkat ?? '') . ($this->ujianKelas->kelas?->kelas ?? ''));
        return 'Analisis ' . $kelasLabel;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $colA = Coordinate::stringFromColumnIndex($this->kolomTerakhirA);
                $colB = Coordinate::stringFromColumnIndex($this->kolomTerakhirB);
                $colMax = Coordinate::stringFromColumnIndex(max($this->kolomTerakhirA, $this->kolomTerakhirB));

                $sheet->mergeCells("A{$this->rowJudul}:{$colMax}{$this->rowJudul}");
                $sheet->mergeCells("A{$this->rowSub}:{$colMax}{$this->rowSub}");
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
                    $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(4.5);
                }
                $sheet->freezePane('C' . $this->rowDataAwalA);

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4);
                $sheet->setBreak('A' . $this->rowSeksiB, Worksheet::BREAK_ROW);
            },
        ];
    }
}
