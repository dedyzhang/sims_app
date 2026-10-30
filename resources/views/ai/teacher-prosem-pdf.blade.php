<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{{ $title ?? 'Program Semester (PROSEM)' }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 6mm 8mm 6mm 8mm;
        }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #000000;
            font-size: 7.2px;
            line-height: 1.2;
            background: #ffffff;
            margin: 0;
            padding: 0;
        }

        .kop-container {
            width: 100%;
            margin-bottom: 4px;
        }

        .doc-title {
            text-align: center;
            font-weight: bold;
            margin-bottom: 6px;
            line-height: 1.2;
        }
        .doc-title h1 {
            font-size: 10px;
            margin: 0;
            text-transform: uppercase;
        }
        .doc-title h2 {
            font-size: 8.5px;
            margin: 1px 0 0;
            text-transform: uppercase;
        }
        .doc-title p {
            font-size: 7.5px;
            margin: 1px 0 0;
        }

        table.prosem-tbl {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            font-size: 6.8px;
            margin-bottom: 6px;
        }
        table.prosem-tbl thead {
            display: table-header-group;
        }
        table.prosem-tbl th {
            border: 1px solid #000000;
            background: #f2f2f2;
            padding: 2.5px 1px;
            font-weight: bold;
            text-align: center;
            vertical-align: middle;
            font-size: 6.8px;
        }
        table.prosem-tbl td {
            border: 1px solid #000000;
            padding: 2px 1.5px;
            vertical-align: middle;
        }
        table.prosem-tbl tr {
            page-break-inside: avoid;
        }

        .vtext {
            transform: rotate(-90deg);
            -webkit-transform: rotate(-90deg);
            white-space: nowrap;
            display: block;
            text-align: center;
            font-size: 6.2px;
            font-weight: bold;
            letter-spacing: 0.2px;
        }

        table.sig-tbl {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            page-break-inside: avoid;
        }
        table.sig-tbl td {
            border: none;
            padding: 0;
            vertical-align: top;
            font-size: 7.5px;
        }
        .sig-name {
            font-weight: bold;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    @php
        $months = $prosem['months'] ?? [];
        $calendarEvents = $prosem['calendar_events'] ?? [];
        $blockedColMap = $prosem['blocked_col_map'] ?? [];
        $babs = $prosem['babs'] ?? [];

        $totalDataRows = 0;
        foreach ($babs as $b) {
            $totalDataRows += 1;
            $totalDataRows += count($b['tps']);
            $totalDataRows += 1;
        }
        if (($prosem['cadangan_jp'] ?? 0) > 0) {
            $totalDataRows += 1;
        }
        $totalDataRows += 1;
    @endphp

    {{-- Kop Surat Resmi Sekolah --}}
    <div class="kop-container">
        {!! \App\Support\SchoolLetterhead::renderHtmlForPdf() !!}
    </div>

    {{-- Judul Dokumen --}}
    <div class="doc-title">
        <h1>{{ $prosem['title'] ?? 'PROGRAM SEMESTER' }}</h1>
        <h2>MATA PELAJARAN {{ $prosem['mapel'] ?? '' }}</h2>
        <p>TAHUN PELAJARAN {{ $prosem['tahun'] ?? '' }}</p>
    </div>

    {{-- Tabel PROSEM 26 Kolom --}}
    <table class="prosem-tbl">
        <thead>
            <tr>
                <th rowspan="3" style="width: 28%; text-align: center;">Materi pokok/ Tujuan Pembelajaran</th>
                <th rowspan="3" style="width: 5.5%; text-align: center;">Alokasi<br>Waktu</th>
                <th colspan="26" style="background: #e2e8f0; font-weight: bold; text-align: center;">BULAN</th>
            </tr>
            <tr>
                @foreach($months as $m)
                    <th colspan="{{ $m['weeks'] }}" style="text-align: center;">{{ $m['name'] }}</th>
                @endforeach
            </tr>
            <tr style="font-size: 6.2px;">
                @foreach($months as $m)
                    @for($w = 1; $w <= $m['weeks']; $w++)
                        <th style="width: 2.55%; text-align: center;">{{ $w }}</th>
                    @endfor
                @endforeach
            </tr>
        </thead>
        <tbody>
            @php $isFirstDataRow = true; @endphp

            @foreach($babs as $b)
                {{-- Header Bab --}}
                <tr style="background: #f3d0ea; font-weight: bold;">
                    <td style="text-align: left; font-weight: bold; background: #f3d0ea;">
                        {{ $b['bab_title'] }}
                    </td>
                    <td style="text-align: center; background: #f3d0ea;"></td>

                    @php $c = 0; @endphp
                    @while($c < 26)
                        @if(isset($blockedColMap[$c]))
                            @php
                                $ev = null;
                                foreach ($calendarEvents as $cand) {
                                    if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                                        $ev = $cand;
                                        break;
                                    }
                                }
                            @endphp
                            @if($isFirstDataRow)
                                <td rowspan="{{ $totalDataRows }}" colspan="{{ $ev['col_span'] }}"
                                    style="background-color: {{ $ev['color'] }}; color: {{ $ev['text_color'] }}; text-align: center; vertical-align: middle; padding: 0;">
                                    <div class="vtext">{{ $ev['label'] }}</div>
                                </td>
                            @endif
                            @php $c += $ev['col_span']; @endphp
                        @else
                            <td style="text-align: center; background: #f3d0ea;"></td>
                            @php $c++; @endphp
                        @endif
                    @endwhile
                </tr>
                @php $isFirstDataRow = false; @endphp

                {{-- Butir-Butir TP --}}
                @php $tpCount = count($b['tps']); @endphp
                @foreach($b['tps'] as $tIdx => $t)
                    <tr>
                        <td style="text-align: justify; vertical-align: top; font-size: 6.8px;">
                            {{ $t['tp'] }}
                        </td>
                        @if($tIdx === 0)
                            <td rowspan="{{ $tpCount }}" style="text-align: center; font-weight: bold; vertical-align: middle; font-size: 7.5px;">
                                {{ $b['tp_alokasi_jp'] ?? max(1, $b['alokasi_jp'] - ($b['sumatif_jp'] ?? 2)) }}
                            </td>
                        @endif

                        @php $c = 0; @endphp
                        @while($c < 26)
                            @if(isset($blockedColMap[$c]))
                                @php
                                    $evSpan = 1;
                                    foreach ($calendarEvents as $cand) {
                                        if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                                            $evSpan = $cand['col_span'];
                                            break;
                                        }
                                    }
                                    $c += $evSpan;
                                @endphp
                            @else
                                <td style="text-align: center; font-weight: bold;">
                                    {{ $t['weeks'][$c] ?? '' }}
                                </td>
                                @php $c++; @endphp
                            @endif
                        @endwhile
                    </tr>
                @endforeach

                {{-- Penilaian Sumatif --}}
                <tr style="background: #fce7f3; font-weight: bold;">
                    <td style="text-align: left; font-weight: bold; background: #fce7f3;">
                        PENILAIAN SUMATIF
                    </td>
                    <td style="text-align: center; font-weight: bold; background: #fce7f3;">
                        {{ $prosem['sumatif_jp'] ?? 2 }}
                    </td>

                    @php $c = 0; @endphp
                    @while($c < 26)
                        @if(isset($blockedColMap[$c]))
                            @php
                                $evSpan = 1;
                                foreach ($calendarEvents as $cand) {
                                    if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                                        $evSpan = $cand['col_span'];
                                        break;
                                    }
                                }
                                $c += $evSpan;
                            @endphp
                        @else
                            <td style="text-align: center; font-weight: bold; background: {{ !empty($b['sumatif_weeks'][$c]) ? '#fce7f3' : 'transparent' }};">
                                {{ $b['sumatif_weeks'][$c] ?? '' }}
                            </td>
                            @php $c++; @endphp
                        @endif
                    @endwhile
                </tr>
            @endforeach

            {{-- Cadangan --}}
            @if(($prosem['cadangan_jp'] ?? 0) > 0)
                <tr>
                    <td style="text-align: left; font-weight: bold;">CADANGAN</td>
                    <td style="text-align: center; font-weight: bold;">{{ $prosem['cadangan_jp'] }}</td>
                    @php $c = 0; @endphp
                    @while($c < 26)
                        @if(isset($blockedColMap[$c]))
                            @php
                                $evSpan = 1;
                                foreach ($calendarEvents as $cand) {
                                    if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                                        $evSpan = $cand['col_span'];
                                        break;
                                    }
                                }
                                $c += $evSpan;
                            @endphp
                        @else
                            <td style="text-align: center;"></td>
                            @php $c++; @endphp
                        @endif
                    @endwhile
                </tr>
            @endif

            {{-- Total JP --}}
            <tr style="font-weight: bold; background: #f8fafc;">
                <td style="text-align: left; font-weight: bold; text-transform: uppercase;">
                    TOTAL JP
                </td>
                <td style="text-align: center; font-weight: bold; font-size: 7.5px; white-space: nowrap;">
                    {{ $prosem['total_jp'] }} JP
                </td>

                @php $c = 0; @endphp
                @while($c < 26)
                    @if(isset($blockedColMap[$c]))
                        @php
                            $evSpan = 1;
                            foreach ($calendarEvents as $cand) {
                                if ($c >= $cand['start_col'] && $c < ($cand['start_col'] + $cand['col_span'])) {
                                    $evSpan = $cand['col_span'];
                                    break;
                                }
                            }
                            $c += $evSpan;
                        @endphp
                    @else
                        <td style="text-align: center; background: #f8fafc;"></td>
                        @php $c++; @endphp
                    @endif
                @endwhile
            </tr>
        </tbody>
    </table>

    {{-- Tanda Tangan Formal --}}
    @php
        $sig = \App\Support\ProsemProtaDocument::resolveSignature($prosem['signature'] ?? [], $prosem['semester_type'] ?? null);
    @endphp
    <table class="sig-tbl">
        <tr>
            <td style="width: 50%; text-align: left;">
                Mengetahui,<br>
                {{ $sig['jabatan_kepala'] }}
                <div style="height: 40px;"></div>
                <span class="sig-name">{{ $sig['kepala_sekolah'] }}</span><br>
                NIK. {{ $sig['nip_kepala'] }}
            </td>
            <td style="width: 50%; text-align: right;">
                {{ $sig['tempat_tanggal'] }}<br>
                {{ $sig['jabatan_guru'] }}
                <div style="height: 40px;"></div>
                <span class="sig-name">{{ $sig['guru'] }}</span><br>
                NIK. {{ $sig['nip_guru'] }}
            </td>
        </tr>
    </table>
</body>
</html>
