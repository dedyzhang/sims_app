<?php

namespace App\Exports\Cetak;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCharts;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title as ChartTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Sheet 2 "Statistik" — kartu ringkasan (KPI), 4 tabel pivot (usia/agama/JK/kelas × jenis kelamin,
 * gaya nested-row sesuai contoh FL), tabel data grafik, dan 4 chart native Excel (pie + 3 column).
 * Ditulis manual via AfterSheet (bukan FromCollection) krn pivot bertingkat + chart butuh kontrol
 * cell penuh — pola ini baru di codebase, belum ada contoh export lain yg pakai native chart.
 */
class SiswaStatistikSheet implements FromArray, WithCharts, WithColumnWidths, WithTitle, WithEvents
{
    /** Urutan resmi 6 agama yg diakui — selalu ditampilkan semua walau 0 siswa (konsisten dgn rekap Dapodik). */
    private const AGAMA_URUT = ['Islam', 'Kristen Protestan', 'Katolik', 'Hindu', 'Buddha', 'Konghucu'];

    private const WARNA_L = 'B4C6E7'; // biru muda — sama dgn versi tabel sebelumnya, konsisten
    private const WARNA_P = 'E6B8B7'; // merah muda
    private const INDIGO = '4F46E5';
    private const INDIGO_GELAP = '4338CA';

    /** Baris pertama tabel "Total JK" (Laki-laki) — dicatat drawJkTotalTable(), dipakai drawCharts() sbg sumber pie chart. */
    private int $jkTotalRowAwal = 0;

    /** Baris header 3 mini-tabel data-grafik (Usia/Agama/Kelas berbagi baris header yg SAMA,
     *  walau tinggi datanya beda) — dicatat drawDataGrafik(), dipakai drawCharts(). */
    private int $dataGrafikHeaderRow = 0;

    private array $ages = [];
    private array $religions = [];
    private array $classes = [];
    private array $statsUsia = ['L' => [], 'P' => []];
    private array $statsAgama = ['L' => [], 'P' => []];
    private array $statsKelas = ['L' => [], 'P' => []];
    private array $totalGender = ['L' => 0, 'P' => 0];

    public function __construct(private string $idKelas)
    {
        $this->hitungStatistik(SiswaExport::query($idKelas)->get());
    }

    private function hitungStatistik(Collection $siswas): void
    {
        foreach ($siswas as $siswa) {
            $jk = $siswa->jk === 'P' ? 'P' : 'L';
            $this->totalGender[$jk]++;

            $age = $siswa->tanggal_lahir ? (string) Carbon::parse($siswa->tanggal_lahir)->age : null;
            if ($age !== null) {
                if (! in_array($age, $this->ages, true)) $this->ages[] = $age;
                $this->statsUsia[$jk][$age] = ($this->statsUsia[$jk][$age] ?? 0) + 1;
            }

            // Agama — nilai asli dr DB (sudah proper-case: "Kristen Protestan" dst), JANGAN
            // lowercase+ucfirst (merusak nama 2 kata jadi "Kristen protestan").
            $agama = $siswa->agama ?: 'Tidak Diketahui';
            if (! in_array($agama, $this->religions, true)) $this->religions[] = $agama;
            $this->statsAgama[$jk][$agama] = ($this->statsAgama[$jk][$agama] ?? 0) + 1;

            $kelasNama = $siswa->kelas ? "{$siswa->kelas->tingkat}{$siswa->kelas->kelas}" : 'Tidak Ada Kelas';
            if (! in_array($kelasNama, $this->classes, true)) $this->classes[] = $kelasNama;
            $this->statsKelas[$jk][$kelasNama] = ($this->statsKelas[$jk][$kelasNama] ?? 0) + 1;
        }

        sort($this->ages);
        sort($this->classes);

        // Agama: SELALU 6 agama resmi berurutan (walau 0, mis. Hindu) — konsisten rekap Dapodik.
        // Nilai lain tak terduga (mis. "Tidak Diketahui") ditambahkan di akhir, urut abjad.
        $lain = array_values(array_diff($this->religions, self::AGAMA_URUT));
        sort($lain);
        $this->religions = array_merge(self::AGAMA_URUT, $lain);

        foreach ($this->ages as $a) {
            $this->statsUsia['L'][$a] ??= 0;
            $this->statsUsia['P'][$a] ??= 0;
        }
        foreach ($this->religions as $a) {
            $this->statsAgama['L'][$a] ??= 0;
            $this->statsAgama['P'][$a] ??= 0;
        }
        foreach ($this->classes as $k) {
            $this->statsKelas['L'][$k] ??= 0;
            $this->statsKelas['P'][$k] ??= 0;
        }
    }

    /** Sheet kosong — semua isi ditulis manual di AfterSheet (lihat class doc). */
    public function array(): array
    {
        return [[]];
    }

    public function title(): string
    {
        return 'Statistik';
    }

    /**
     * WithCharts murni PENANDA — Maatwebsite hanya menyalakan Writer::setIncludeCharts(true) kalau
     * sheet `instanceof WithCharts` (lihat WriterFactory::includesCharts()). Chart SEBENARNYA tetap
     * ditambahkan manual via $sheet->addChart() di AfterSheet (lihat drawCharts()), BUKAN lewat
     * return value method ini — Sheet::close() memanggil charts() SEBELUM AfterSheet ter-dispatch,
     * jadi di titik itu baris tabel/KPI blm dihitung & referensi cell chart blm bisa dibangun.
     */
    public function charts(): array
    {
        return [];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 14, 'B' => 11, 'C' => 9, 'D' => 10, 'E' => 9,
            'F' => 3,
            'G' => 14, 'H' => 18, 'I' => 9, 'J' => 10, 'K' => 9,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $this->draw($event->sheet->getDelegate());
            },
        ];
    }

    private function draw(Worksheet $sheet): void
    {
        $namaSekolah = Setting::get('nama_sekolah', 'Sekolah');
        $labelFilter = SiswaExport::labelFilter($this->idKelas);
        $grandTotal = $this->totalGender['L'] + $this->totalGender['P'];

        $sheet->setCellValue('A1', 'STATISTIK DATA SISWA');
        $sheet->mergeCells('A1:K1');
        $sheet->setCellValue('A2', "{$namaSekolah}  •  {$labelFilter}  •  Diekspor: " . now()->isoFormat('D MMMM Y, HH:mm') . ' WIB');
        $sheet->mergeCells('A2:K2');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 16, 'color' => ['rgb' => '1E293B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['italic' => true, 'size' => 10, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(28);
        $sheet->getRowDimension(2)->setRowHeight(18);

        $row = $this->drawKpiCards($sheet, 4, $grandTotal);
        $row += 1;

        $usiaGroups = $this->groupJkOuter($this->statsUsia, $this->ages);
        $r1End = $this->drawPivot($sheet, 'A', $row, '📊 Berdasarkan Usia', 'Usia', $usiaGroups, $grandTotal);

        $agamaGroups = $this->groupJkOuter($this->statsAgama, $this->religions);
        $r2End = $this->drawPivot($sheet, 'G', $row, '🙏 Berdasarkan Agama', 'Agama', $agamaGroups, $grandTotal);

        $r3End = $this->drawJkTotalTable($sheet, 'A', $r1End + 2, $grandTotal);

        $kelasGroups = $this->groupKategoriOuter($this->statsKelas, $this->classes);
        $r4End = $this->drawPivot($sheet, 'G', $r2End + 2, '🏫 Berdasarkan Kelas', 'JK', $kelasGroups, $grandTotal, kategoriOuter: true);

        $rowChart = max($r3End, $r4End) + 3;
        $rowChart = $this->drawDataGrafik($sheet, $rowChart);
        $this->drawCharts($sheet, $rowChart + 2, $grandTotal);
    }

    // ─────────────────────── KPI Cards ───────────────────────

    private function drawKpiCards(Worksheet $sheet, int $row, int $grandTotal): int
    {
        $jumlahKelas = count(array_filter($this->classes, fn ($k) => $k !== 'Tidak Ada Kelas'));

        $cards = [
            ['col' => 'A', 'label' => '👥 TOTAL SISWA', 'nilai' => $grandTotal, 'warna' => self::INDIGO],
            ['col' => 'D', 'label' => '👦 LAKI-LAKI', 'nilai' => $this->totalGender['L'], 'warna' => '2563EB'],
            ['col' => 'G', 'label' => '👧 PEREMPUAN', 'nilai' => $this->totalGender['P'], 'warna' => 'DB2777'],
            ['col' => 'J', 'label' => '🏫 JUMLAH KELAS', 'nilai' => $jumlahKelas, 'warna' => 'D97706'],
        ];

        foreach ($cards as $card) {
            $colIdx = Coordinate::columnIndexFromString($card['col']);
            $colAkhir = Coordinate::stringFromColumnIndex($colIdx + 1);

            $sheet->setCellValue("{$card['col']}{$row}", $card['label']);
            $sheet->mergeCells("{$card['col']}{$row}:{$colAkhir}{$row}");
            $sheet->setCellValue("{$card['col']}" . ($row + 1), $card['nilai']);
            $sheet->mergeCells("{$card['col']}" . ($row + 1) . ":{$colAkhir}" . ($row + 1));

            $sheet->getStyle("{$card['col']}{$row}:{$colAkhir}" . ($row + 1))->applyFromArray([
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $card['warna']]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("{$card['col']}{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            ]);
            $sheet->getStyle("{$card['col']}" . ($row + 1))->applyFromArray([
                'font' => ['bold' => true, 'size' => 24, 'color' => ['rgb' => 'FFFFFF']],
            ]);
        }
        $sheet->getRowDimension($row)->setRowHeight(20);
        $sheet->getRowDimension($row + 1)->setRowHeight(34);

        return $row + 1;
    }

    // ─────────────────────── Pengelompokan data (utk tabel nested-row) ───────────────────────

    /**
     * OUTER = jenis kelamin (Laki-laki, Perempuan), INNER = kategori (usia/agama), urutan inner
     * sesuai $urutanKategori. $stats: ['L'=>[kategori=>count], 'P'=>[...]].
     */
    private function groupJkOuter(array $stats, array $urutanKategori): array
    {
        $out = [];
        foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $kode => $label) {
            $rows = [];
            foreach ($urutanKategori as $kat) {
                $rows[] = [$kat, $stats[$kode][$kat] ?? 0];
            }
            // Grup TAK BOLEH 0-baris: kalau kosong, drawPivot() gagal maju baris → grup berikutnya
            // ($row tak bertambah) NIMPA cell grup ini, dan rentang SUM subtotal jadi terbalik.
            if (empty($rows)) {
                $rows[] = ['(tidak ada data)', 0];
            }
            $out[] = ['label' => $label, 'rows' => $rows, 'subtotal' => array_sum($stats[$kode] ?? [])];
        }

        return $out;
    }

    /** OUTER = kategori (kelas), INNER = jenis kelamin. $stats: ['L'=>[kategori=>count], 'P'=>[...]]. */
    private function groupKategoriOuter(array $stats, array $urutanKategori): array
    {
        $out = [];
        foreach ($urutanKategori as $kat) {
            $rows = [
                ['Laki-laki', $stats['L'][$kat] ?? 0],
                ['Perempuan', $stats['P'][$kat] ?? 0],
            ];
            $out[] = ['label' => $kat, 'rows' => $rows, 'subtotal' => ($stats['L'][$kat] ?? 0) + ($stats['P'][$kat] ?? 0)];
        }
        // $urutanKategori bisa kosong (mis. tak ada siswa berkelas sama sekali) — drawPivot() butuh
        // MINIMAL 1 grup supaya perhitungan dataFirstRow/dataLastRow tak terbalik.
        if (empty($out)) {
            $out[] = ['label' => '(tidak ada data)', 'rows' => [['Laki-laki', 0], ['Perempuan', 0]], 'subtotal' => 0];
        }

        return $out;
    }

    // ─────────────────────── Gambar tabel pivot nested-row ───────────────────────

    /**
     * Gambar tabel pivot 5-kolom (label-outer | label-inner | jumlah | subtotal | total keseluruhan).
     * $kategoriOuter=false: outer=JK (warnai sel outer biru/pink). $kategoriOuter=true: outer=kategori
     * (mis. kelas), inner=JK (warnai sel INNER biru/pink krn JK ada di situ).
     */
    private function drawPivot(Worksheet $sheet, string $col, int $row, string $judul, string $headerInner, array $groups, int $grandTotal, bool $kategoriOuter = false): int
    {
        $colIdx = Coordinate::columnIndexFromString($col);
        [$cOuter, $cInner, $cJumlah, $cSubtotal, $cTotal] = array_map(
            fn ($i) => Coordinate::stringFromColumnIndex($colIdx + $i), [0, 1, 2, 3, 4]
        );

        $sheet->setCellValue("{$cOuter}{$row}", $judul);
        $sheet->mergeCells("{$cOuter}{$row}:{$cTotal}{$row}");
        $sheet->getStyle("{$cOuter}{$row}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '334155']]]);
        $row++;

        $headerRow = $row;
        $headerOuter = $kategoriOuter ? 'Kelas' : 'Jenis Kelamin';
        foreach ([$cOuter => $headerOuter, $cInner => $headerInner, $cJumlah => 'Jumlah', $cSubtotal => 'Subtotal', $cTotal => 'Total'] as $c => $label) {
            $sheet->setCellValue("{$c}{$headerRow}", $label);
        }
        $sheet->getStyle("{$cOuter}{$headerRow}:{$cTotal}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::INDIGO]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => self::INDIGO_GELAP]]],
        ]);
        $row++;

        $dataFirstRow = $row;
        foreach ($groups as $group) {
            $groupFirstRow = $row;

            foreach ($group['rows'] as [$innerLabel, $count]) {
                $sheet->setCellValue("{$cInner}{$row}", $innerLabel);
                $sheet->setCellValue("{$cJumlah}{$row}", $count);

                $warnaJk = $kategoriOuter ? $this->warnaUntukJk($innerLabel) : null;
                if ($warnaJk) {
                    $sheet->getStyle("{$cInner}{$row}:{$cJumlah}{$row}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $warnaJk]],
                    ]);
                }
                $row++;
            }
            $groupLastRow = $row - 1;

            $sheet->setCellValue("{$cOuter}{$groupFirstRow}", $group['label']);
            if ($groupLastRow > $groupFirstRow) {
                $sheet->mergeCells("{$cOuter}{$groupFirstRow}:{$cOuter}{$groupLastRow}");
            }
            $sheet->setCellValue("{$cSubtotal}{$groupFirstRow}", "=SUM({$cJumlah}{$groupFirstRow}:{$cJumlah}{$groupLastRow})");
            if ($groupLastRow > $groupFirstRow) {
                $sheet->mergeCells("{$cSubtotal}{$groupFirstRow}:{$cSubtotal}{$groupLastRow}");
            }
            $sheet->getStyle("{$cOuter}{$groupFirstRow}")->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);
            $sheet->getStyle("{$cSubtotal}{$groupFirstRow}")->applyFromArray([
                'font' => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ]);

            if (! $kategoriOuter) {
                $warna = $this->warnaUntukJk($group['label']);
                if ($warna) {
                    $sheet->getStyle("{$cOuter}{$groupFirstRow}:{$cOuter}{$groupLastRow}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $warna]],
                    ]);
                }
            }
        }
        $dataLastRow = $row - 1;

        $sheet->setCellValue("{$cTotal}{$dataFirstRow}", $grandTotal);
        if ($dataLastRow > $dataFirstRow) {
            $sheet->mergeCells("{$cTotal}{$dataFirstRow}:{$cTotal}{$dataLastRow}");
        }
        $sheet->getStyle("{$cTotal}{$dataFirstRow}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 12],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getStyle("{$cOuter}{$dataFirstRow}:{$cTotal}{$dataLastRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
        ]);

        return $dataLastRow;
    }

    private function drawJkTotalTable(Worksheet $sheet, string $col, int $row, int $grandTotal): int
    {
        $colIdx = Coordinate::columnIndexFromString($col);
        $cLabel = $col;
        $cJumlah = Coordinate::stringFromColumnIndex($colIdx + 1);

        $sheet->setCellValue("{$cLabel}{$row}", '⚖️ Berdasarkan Jenis Kelamin');
        $sheet->mergeCells("{$cLabel}{$row}:{$cJumlah}{$row}");
        $sheet->getStyle("{$cLabel}{$row}")->applyFromArray(['font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '334155']]]);
        $row++;

        $lakiRow = $row;
        $this->jkTotalRowAwal = $lakiRow; // dicatat utk sumber data pie chart di drawCharts()
        $sheet->setCellValue("{$cLabel}{$lakiRow}", 'Laki-laki');
        $sheet->setCellValue("{$cJumlah}{$lakiRow}", $this->totalGender['L']);
        $sheet->getStyle("{$cLabel}{$lakiRow}:{$cJumlah}{$lakiRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::WARNA_L]],
        ]);

        $pRow = $row + 1;
        $sheet->setCellValue("{$cLabel}{$pRow}", 'Perempuan');
        $sheet->setCellValue("{$cJumlah}{$pRow}", $this->totalGender['P']);
        $sheet->getStyle("{$cLabel}{$pRow}:{$cJumlah}{$pRow}")->applyFromArray([
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => self::WARNA_P]],
        ]);

        $totalRow = $row + 2;
        $sheet->setCellValue("{$cLabel}{$totalRow}", 'Total');
        $sheet->setCellValue("{$cJumlah}{$totalRow}", "=SUM({$cJumlah}{$lakiRow}:{$cJumlah}{$pRow})");
        $sheet->getStyle("{$cLabel}{$totalRow}:{$cJumlah}{$totalRow}")->applyFromArray(['font' => ['bold' => true]]);

        $sheet->getStyle("{$cLabel}{$lakiRow}:{$cJumlah}{$totalRow}")->applyFromArray([
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);

        unset($grandTotal);

        return $totalRow;
    }

    private function warnaUntukJk(string $label): ?string
    {
        return match ($label) {
            'Laki-laki' => self::WARNA_L,
            'Perempuan' => self::WARNA_P,
            default => null,
        };
    }

    // ─────────────────────── Data grafik (flat, sumber chart) ───────────────────────

    /** Tabel flat [Kategori|Laki-laki|Perempuan] — sumber data 3 column chart. Return baris terakhir. */
    private function drawDataGrafik(Worksheet $sheet, int $row): int
    {
        $sheet->setCellValue('A' . $row, 'Data Sumber Grafik');
        $sheet->mergeCells('A' . $row . ':K' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true, 'italic' => true, 'size' => 9, 'color' => ['rgb' => '94A3B8']]]);
        $row++;

        // Ketiga mini-tabel BERBAGI baris header yg sama ($row), tapi TINGGI datanya beda (jumlah
        // usia ≠ jumlah agama ≠ jumlah kelas) — simpan header row eksplisit, JANGAN back-compute
        // dari last-row (last-row hanya benar utk tabel TERTINGGI, biasanya Kelas).
        $this->dataGrafikHeaderRow = $row;

        // Return baris TERTINGGI dr ketiganya (bukan cuma yg terakhir digambar/Kelas) — jumlah
        // usia/agama/kelas bisa beda2, mis. difilter 1 kelas: Kelas cuma 1 baris tapi Agama tetap
        // 6-7 baris (selalu 6 agama resmi). Kalau salah asumsi tabel Kelas paling tinggi, section
        // "Grafik Ringkasan" di bawahnya bisa nimpa tabel data yg belum selesai ditulis.
        $rUsia = $this->tabelDatarKategoriJk($sheet, 'A', $row, 'Usia', $this->ages, $this->statsUsia);
        $rAgama = $this->tabelDatarKategoriJk($sheet, 'E', $row, 'Agama', $this->religions, $this->statsAgama);
        $rKelas = $this->tabelDatarKategoriJk($sheet, 'I', $row, 'Kelas', $this->classes, $this->statsKelas);

        return max($rUsia, $rAgama, $rKelas);
    }

    private function tabelDatarKategoriJk(Worksheet $sheet, string $col, int $row, string $labelKategori, array $urutan, array $stats): int
    {
        $colIdx = Coordinate::columnIndexFromString($col);
        $c1 = $col;
        $c2 = Coordinate::stringFromColumnIndex($colIdx + 1);
        $c3 = Coordinate::stringFromColumnIndex($colIdx + 2);

        $sheet->setCellValue("{$c1}{$row}", $labelKategori);
        $sheet->setCellValue("{$c2}{$row}", 'Laki-laki');
        $sheet->setCellValue("{$c3}{$row}", 'Perempuan');
        $sheet->getStyle("{$c1}{$row}:{$c3}{$row}")->applyFromArray([
            'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '475569']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
        ]);
        // Minimal 1 baris data walau $urutan kosong (mis. tak ada siswa sama sekali) — kalau
        // dibiarkan 0 baris, rentang chart jadi terbalik (mis. $A$10:$A$9) & tabel lain di
        // bawahnya salah posisi (lihat max() di drawDataGrafik).
        $daftar = empty($urutan) ? ['(tidak ada)'] : $urutan;
        $r = $row + 1;
        foreach ($daftar as $kat) {
            $sheet->setCellValue("{$c1}{$r}", $kat);
            $sheet->setCellValue("{$c2}{$r}", $stats['L'][$kat] ?? 0);
            $sheet->setCellValue("{$c3}{$r}", $stats['P'][$kat] ?? 0);
            $r++;
        }

        return $r - 1;
    }

    // ─────────────────────── Chart native ───────────────────────

    private function drawCharts(Worksheet $sheet, int $row, int $grandTotal): void
    {
        unset($grandTotal);
        $sheetName = $sheet->getTitle();
        $ageCount = max(count($this->ages), 1);
        $religionCount = max(count($this->religions), 1);
        $classCount = max(count($this->classes), 1);

        $headerRow = $this->dataGrafikHeaderRow;
        $dataUsiaFirst = $headerRow + 1;
        $dataUsiaLast = $dataUsiaFirst + $ageCount - 1;
        $dataAgamaFirst = $dataUsiaFirst;
        $dataAgamaLast = $dataAgamaFirst + $religionCount - 1;
        $dataKelasFirst = $dataUsiaFirst;
        $dataKelasLast = $dataKelasFirst + $classCount - 1;

        $sheet->setCellValue('A' . $row, '📈 Grafik Ringkasan');
        $sheet->mergeCells('A' . $row . ':K' . $row);
        $sheet->getStyle('A' . $row)->applyFromArray(['font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '334155']]]);
        $chartTop = $row + 1;
        $chartHeight = 17;

        // Chart 1: Pie Jenis Kelamin — sumber: tabel "Total JK" (A/B, sudah ditulis drawJkTotalTable).
        $this->tambahPieChart(
            $sheet, 'chart_jk', 'Distribusi Jenis Kelamin', $sheetName,
            "\$A\$" . ($this->jkTotalRowAwal) . ':$A$' . ($this->jkTotalRowAwal + 1),
            "\$B\$" . ($this->jkTotalRowAwal) . ':$B$' . ($this->jkTotalRowAwal + 1),
            'A' . $chartTop, 'E' . ($chartTop + $chartHeight)
        );

        // Chart 2: Column Usia.
        $this->tambahColumnChart(
            $sheet, 'chart_usia', 'Distribusi Usia', $sheetName,
            "\$B\${$headerRow}", "\$C\${$headerRow}",
            "\$A\${$dataUsiaFirst}:\$A\${$dataUsiaLast}",
            "\$B\${$dataUsiaFirst}:\$B\${$dataUsiaLast}",
            "\$C\${$dataUsiaFirst}:\$C\${$dataUsiaLast}",
            'G' . $chartTop, 'K' . ($chartTop + $chartHeight)
        );

        $chartTop2 = $chartTop + $chartHeight + 1;

        // Chart 3: Column Agama (data ada di kolom E-G, baris sama dgn Usia).
        $this->tambahColumnChart(
            $sheet, 'chart_agama', 'Distribusi Agama', $sheetName,
            "\$F\${$headerRow}", "\$G\${$headerRow}",
            "\$E\${$dataAgamaFirst}:\$E\${$dataAgamaLast}",
            "\$F\${$dataAgamaFirst}:\$F\${$dataAgamaLast}",
            "\$G\${$dataAgamaFirst}:\$G\${$dataAgamaLast}",
            'A' . $chartTop2, 'E' . ($chartTop2 + $chartHeight)
        );

        // Chart 4: Column Kelas (data ada di kolom I-K).
        $this->tambahColumnChart(
            $sheet, 'chart_kelas', 'Distribusi per Kelas', $sheetName,
            "\$J\${$headerRow}", "\$K\${$headerRow}",
            "\$I\${$dataKelasFirst}:\$I\${$dataKelasLast}",
            "\$J\${$dataKelasFirst}:\$J\${$dataKelasLast}",
            "\$K\${$dataKelasFirst}:\$K\${$dataKelasLast}",
            'G' . $chartTop2, 'K' . ($chartTop2 + $chartHeight)
        );
    }

    private function tambahPieChart(Worksheet $sheet, string $id, string $judul, string $sheetName, string $catRange, string $valRange, string $topLeft, string $bottomRight): void
    {
        $labels = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!{$catRange}", null, 2)];
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!{$catRange}", null, 2)];
        $values = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetName}'!{$valRange}", null, 2)];

        $series = new DataSeries(DataSeries::TYPE_PIECHART, null, range(0, count($values) - 1), $labels, $categories, $values);
        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_RIGHT, null, false);
        $title = new ChartTitle($judul);
        $chart = new Chart($id, $title, $legend, $plotArea);
        $chart->setTopLeftPosition($topLeft);
        $chart->setBottomRightPosition($bottomRight);
        $sheet->addChart($chart);
    }

    private function tambahColumnChart(Worksheet $sheet, string $id, string $judul, string $sheetName, string $label1, string $label2, string $catRange, string $val1Range, string $val2Range, string $topLeft, string $bottomRight): void
    {
        $labels = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!{$label1}", null, 1),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!{$label2}", null, 1),
        ];
        $categories = [new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'{$sheetName}'!{$catRange}", null, $this->rentangJumlah($catRange))];
        $values = [
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetName}'!{$val1Range}", null, $this->rentangJumlah($val1Range)),
            new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'{$sheetName}'!{$val2Range}", null, $this->rentangJumlah($val2Range)),
        ];

        $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, range(0, count($values) - 1), $labels, $categories, $values);
        $series->setPlotDirection(DataSeries::DIRECTION_COL);
        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $title = new ChartTitle($judul);
        $yAxis = new ChartTitle('Jumlah Siswa');
        $chart = new Chart($id, $title, $legend, $plotArea, true, DataSeries::EMPTY_AS_GAP, null, $yAxis);
        $chart->setTopLeftPosition($topLeft);
        $chart->setBottomRightPosition($bottomRight);
        $sheet->addChart($chart);
    }

    /** Hitung jumlah baris dari rentang "$A$5:$A$9" → 5. Dipakai sbg pointCount DataSeriesValues. */
    private function rentangJumlah(string $range): int
    {
        if (! str_contains($range, ':')) {
            return 1;
        }
        [$dari, $sampai] = explode(':', $range);
        preg_match('/\d+/', $dari, $m1);
        preg_match('/\d+/', $sampai, $m2);

        return max(1, ((int) $m2[0]) - ((int) $m1[0]) + 1);
    }
}
