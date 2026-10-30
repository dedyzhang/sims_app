<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Program Tahunan (PROTA)' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 8mm 10mm 8mm 10mm;
        }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #000000;
            font-size: 8px;
            line-height: 1.25;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        .prota-page-break {
            page-break-before: always;
        }

        /* Kop Surat Resmi SIMS */
        .kop-container {
            width: 100%;
            margin-bottom: 6px;
        }

        /* Judul Dokumen */
        .doc-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 8px;
            line-height: 1.2;
        }
        .doc-title h1 {
            font-size: 11px;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-title h2 {
            font-size: 9.5px;
            margin: 2px 0 0;
            text-transform: uppercase;
        }
        .doc-title p {
            font-size: 8.5px;
            margin: 2px 0 0;
        }

        /* Tabel Prota */
        table.prota-tbl {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 7.5px;
            margin-bottom: 8px;
        }
        table.prota-tbl thead {
            display: table-header-group;
        }
        table.prota-tbl th {
            border: 1px solid #000000;
            background: #f2f2f2;
            padding: 4px 3px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            font-size: 7.5px;
        }
        table.prota-tbl td {
            border: 1px solid #000000;
            padding: 3px 3px;
            vertical-align: top;
        }
        table.prota-tbl tr {
            page-break-inside: avoid;
        }

        /* Tanda Tangan */
        table.sig-tbl {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: avoid;
        }
        table.sig-tbl td {
            border: none;
            padding: 0;
            vertical-align: top;
            font-size: 8px;
        }
        .sig-name {
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    @php
        $primaryElemen = $prota['primary_elemen'] ?? 'Pemahaman Konsep';
        $primaryCp = $prota['primary_cp'] ?? '';
    @endphp

    @foreach(['semester_1', 'semester_2'] as $sKey)
        @if(empty($prota['semesters'][$sKey]))
            @continue
        @endif
        @php
            $sem = $prota['semesters'][$sKey];
            $semRowCount = 1; // Baris Separator Semester
            foreach ($sem['babs'] as $b) {
                $semRowCount += 1; // Header Bab
                $semRowCount += count($b['tps']); // Baris TP
                $semRowCount += 1; // Penilaian Sumatif
            }
            if (($sem['cadangan_jp'] ?? 0) > 0) {
                $semRowCount += 1; // Cadangan
            }
            $semRowCount += 1; // Total Alokasi Waktu
        @endphp

        <div class="prota-page-section {{ ! $loop->first ? 'prota-page-break' : '' }}">
            @if($loop->first)
                {{-- Kop Surat Resmi Sekolah --}}
                <div class="kop-container">
                    {!! \App\Support\SchoolLetterhead::renderHtmlForPdf() !!}
                </div>

                {{-- Judul Dokumen --}}
                <div class="doc-title">
                    <h1>PROGRAM TAHUNAN</h1>
                    <h2>{{ $prota['kelas'] ?? 'KELAS VII' }}</h2>
                    <h2>MATA PELAJARAN {{ $prota['mapel'] ?? '' }}</h2>
                    <p>TAHUN PELAJARAN {{ $prota['tahun'] ?? '' }}</p>
                </div>
            @endif

            {{-- Tabel PROTA Semester Ini --}}
            <table class="prota-tbl">
                <thead>
                    <tr>
                        <th style="width: 14%;">ELEMEN</th>
                        <th style="width: 26%;">CAPAIAN PEMBELAJARAN<br>(CP)</th>
                        <th style="width: 36%;">TUJUAN PEMBELAJARAN<br>(TP)</th>
                        <th style="width: 8%;">KODE TP</th>
                        <th style="width: 8%;">ALOKASI<br>WAKTU</th>
                        <th style="width: 8%;">KETERANGAN</th>
                    </tr>
                </thead>
                <tbody>
                    {{-- Separator Semester --}}
                    <tr style="background: #e2e8f0; font-weight: bold;">
                        <td rowspan="{{ $semRowCount }}" style="vertical-align: top; font-weight: bold; background: #fafafa;">
                            {{ $primaryElemen }}
                        </td>
                        <td rowspan="{{ $semRowCount }}" style="vertical-align: top; text-align: justify; font-size: 7.2px; line-height: 1.25; background: #fafafa;">
                            {{ $primaryCp }}
                        </td>
                        <td colspan="4" style="text-align: center; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; background: #e2e8f0;">
                            {{ $sem['label'] }}
                        </td>
                    </tr>

                    {{-- Bab & TP dalam semester ini --}}
                    @foreach($sem['babs'] as $b)
                        {{-- Header Bab --}}
                        <tr style="background: #f3d0ea; font-weight: bold;">
                            <td colspan="2" style="text-align: center; font-weight: bold; background: #f3d0ea;">
                                {{ $b['bab_title'] }}
                            </td>
                            <td style="background: #f3d0ea;"></td>
                            <td style="background: #f3d0ea;"></td>
                        </tr>

                        {{-- Butir-Butir TP --}}
                        @php $tpCount = count($b['tps']); @endphp
                        @foreach($b['tps'] as $tIdx => $t)
                            <tr>
                                <td style="text-align: justify; font-size: 7.2px;">
                                    {{ $t['tp'] }}
                                </td>
                                <td style="text-align: center; font-weight: bold; vertical-align: middle;">
                                    {{ $t['kode'] }}
                                </td>
                                @if($tIdx === 0)
                                    <td rowspan="{{ $tpCount }}" style="text-align: center; font-weight: bold; vertical-align: middle; font-size: 8px;">
                                        {{ $b['tp_alokasi_jp'] ?? max(1, $b['alokasi_jp'] - ($b['sumatif_jp'] ?? 2)) }}
                                    </td>
                                @endif
                                <td></td>
                            </tr>
                        @endforeach

                        {{-- Penilaian Sumatif --}}
                        <tr style="background: #fce7f3; font-weight: bold;">
                            <td colspan="2" style="text-align: center; font-weight: bold; background: #fce7f3;">
                                PENILAIAN SUMATIF
                            </td>
                            <td style="text-align: center; font-weight: bold; background: #fce7f3;">
                                {{ $b['sumatif_jp'] ?? $sem['sumatif_jp_per_bab'] ?? 2 }}
                            </td>
                            <td></td>
                        </tr>
                    @endforeach

                    {{-- Cadangan --}}
                    @if(($sem['cadangan_jp'] ?? 0) > 0)
                        <tr>
                            <td colspan="2" style="text-align: center; font-weight: bold;">
                                CADANGAN
                            </td>
                            <td style="text-align: center; font-weight: bold;">
                                {{ $sem['cadangan_jp'] }}
                            </td>
                            <td></td>
                        </tr>
                    @endif

                    {{-- Total Alokasi Waktu Semester --}}
                    <tr style="font-weight: bold; background: #f8fafc;">
                        <td colspan="2" style="text-align: center; font-weight: bold; text-transform: uppercase;">
                            TOTAL ALOKASI WAKTU {{ $sem['label'] }}
                        </td>
                        <td style="text-align: center; font-weight: bold; font-size: 8px; white-space: nowrap;">
                            {{ $sem['total_jp'] }} JP
                        </td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            @if($loop->last)
                {{-- Tanda Tangan Formal diletakkan di akhir Dokumen (Semester Terakhir) --}}
                @php
                    $sig = \App\Support\ProsemProtaDocument::resolveSignature($prota['signature'] ?? []);
                @endphp
                <table class="sig-tbl">
                    <tr>
                        <td style="width: 50%; text-align: left;">
                            Mengetahui,<br>
                            {{ $sig['jabatan_kepala'] }}
                            <div style="height: 48px;"></div>
                            <span class="sig-name">{{ $sig['kepala_sekolah'] }}</span><br>
                            NIK. {{ $sig['nip_kepala'] }}
                        </td>
                        <td style="width: 50%; text-align: right;">
                            {{ $sig['tempat_tanggal'] }}<br>
                            {{ $sig['jabatan_guru'] }}
                            <div style="height: 48px;"></div>
                            <span class="sig-name">{{ $sig['guru'] }}</span><br>
                            NIK. {{ $sig['nip_guru'] }}
                        </td>
                    </tr>
                </table>
            @endif
        </div>
    @endforeach
</body>
</html>
