<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 7mm 10mm 7mm;
        }
        body {
            font-family: DejaVu Sans, Arial, Helvetica, sans-serif;
            color: #000000;
            font-size: 8px;
            line-height: 1.3;
        }

        /* Kop Surat Resmi Sekolah (PDF Landscape DomPDF) */
        .tpatp-kop {
            width: 100%;
            margin-bottom: 6px;
        }
        table.tpatp-kop-table {
            width: 100%;
            border-collapse: collapse;
            border: none !important;
            margin-bottom: 3px;
        }
        table.tpatp-kop-table td {
            border: none !important;
            padding: 0 4px;
            vertical-align: middle;
        }
        table.tpatp-kop-table td.tpatp-kop-logo-cell {
            width: 65px;
            vertical-align: middle;
        }
        table.tpatp-kop-table td.tpatp-kop-logo-left {
            text-align: left;
        }
        table.tpatp-kop-table td.tpatp-kop-logo-right {
            text-align: right;
        }
        .tpatp-kop-logo {
            max-height: 52px;
            max-width: 52px;
        }
        table.tpatp-kop-table td.tpatp-kop-center-cell {
            text-align: center;
            vertical-align: middle;
            padding: 0 6px;
        }
        .tpatp-kop-text {
            text-align: center;
            line-height: 1.25;
            color: #000000;
        }
        .tpatp-kop-text p,
        .tpatp-kop-text h1,
        .tpatp-kop-text h2,
        .tpatp-kop-text h3 {
            margin: 1px 0 !important;
            padding: 0 !important;
            line-height: 1.25 !important;
        }
        .tpatp-kop-line {
            font-size: 8.5px;
            line-height: 1.25;
        }
        .tpatp-kop-line-main {
            font-size: 11.5px;
            font-weight: bold;
        }
        .tpatp-kop-divider {
            border-bottom: 3px double #000000;
            margin-top: 3px;
            margin-bottom: 6px;
            width: 100%;
        }

        /* Judul Dokumen */
        .tpatp-title {
            text-align: center;
            font-weight: 700;
            font-size: 11.5px;
            margin: 4px 0;
            letter-spacing: 0.02em;
            text-decoration: underline;
        }
        .tpatp-subheading {
            font-size: 9.5px;
            font-weight: 700;
            margin: 6px 0 4px;
        }

        /* Identitas */
        table.tpatp-identitas {
            margin: 4px 0 6px;
            border-collapse: collapse;
            font-size: 8.2px;
        }
        table.tpatp-identitas td {
            padding: 1px 0;
            vertical-align: top;
        }
        table.tpatp-identitas td.lbl {
            width: 140px;
            font-weight: 700;
        }
        table.tpatp-identitas td.sep {
            width: 10px;
            text-align: center;
        }
        table.tpatp-identitas td.val {
            font-weight: 600;
        }

        /* Tabel Formal TP */
        table.tpatp-tbl {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000000;
            table-layout: fixed;
            font-size: 7.6px;
            margin-bottom: 6px;
        }
        table.tpatp-tbl thead {
            display: table-header-group;
        }
        table.tpatp-tbl th {
            border: 1px solid #000000;
            background: #f2f2f2;
            color: #000000;
            font-weight: 700;
            padding: 4px 3px;
            text-align: center;
            line-height: 1.2;
            font-size: 7.8px;
        }
        table.tpatp-tbl td {
            border: 1px solid #000000;
            padding: 3px 3.5px;
            vertical-align: top;
            line-height: 1.22;
            word-wrap: break-word;
        }
        table.tpatp-tbl tr {
            page-break-inside: avoid;
        }
        table.tpatp-tbl td.cell-elemen {
            font-weight: 700;
            background: #fafafa;
            vertical-align: middle;
            text-align: center;
        }
        table.tpatp-tbl td.cell-cp {
            font-size: 7.3px;
            vertical-align: top;
            text-align: justify;
        }
        table.tpatp-tbl td.cell-materi {
            font-weight: 700;
            vertical-align: middle;
        }
        table.tpatp-tbl td.cell-tp {
            font-size: 7.3px;
        }
        table.tpatp-tbl td.cell-code {
            font-weight: 700;
            text-align: center;
            color: #1e3a8a;
            vertical-align: middle;
        }
        table.tpatp-tbl td.cell-jp,
        table.tpatp-tbl td.cell-sem {
            text-align: center;
            vertical-align: middle;
            font-weight: 700;
            white-space: nowrap;
        }

        .tpatp-chunk-header {
            font-weight: 700;
            font-size: 10px;
            margin-bottom: 6px;
            text-decoration: underline;
        }
        .tpatp-chunk-grade {
            font-weight: 600;
            font-size: 9px;
            margin-left: 4px;
        }

        /* Flowchart Table untuk DomPDF (Tahan Banting & Tanpa Flexbox) */
        table.pdf-flowchart-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px;
            margin: 6px 0 10px;
        }
        table.pdf-flowchart-table td.flow-box {
            text-align: center;
            vertical-align: middle;
            padding: 6px 5px;
            font-size: 7.5px;
            line-height: 1.2;
            border-radius: 6px;
        }
        table.pdf-flowchart-table td.flow-arrow {
            text-align: center;
            vertical-align: middle;
            width: 16px;
            font-size: 11px;
            color: #64748b;
            font-weight: bold;
        }
        .box-start {
            background: #ede9fe;
            border: 1px solid #a78bfa;
            color: #4c1d95;
            font-weight: 700;
        }
        .box-pink {
            background: #fee2e2;
            border: 1px solid #f87171;
            color: #881337;
        }
        .box-orange {
            background: #ffedd5;
            border: 1px solid #fb923c;
            color: #7c2d12;
        }
        .box-green {
            background: #dcfce7;
            border: 1px solid #4ade80;
            color: #14532d;
        }
        .box-trans {
            background: #fed7aa;
            border: 1px solid #f97316;
            color: #9a3412;
            border-radius: 12px;
            font-weight: 700;
        }
        .box-end {
            background: #bbf7d0;
            border: 1.5px solid #16a34a;
            color: #052e16;
            font-weight: 700;
            border-radius: 12px;
        }
        .box-code {
            display: block;
            margin-top: 2px;
            font-weight: 700;
            font-size: 7px;
        }

        /* Tanda Tangan */
        .tpatp-ttd-container {
            margin-top: 8px;
            width: 100%;
            page-break-inside: avoid;
        }
        table.tpatp-ttd-tbl {
            width: 100%;
            border-collapse: collapse;
            border: none !important;
        }
        table.tpatp-ttd-tbl td {
            border: none !important;
        }
        table.tpatp-ttd-tbl td.spacer {
            width: 70%;
        }
        table.tpatp-ttd-tbl td.sign-cell {
            width: 30%;
            text-align: center;
            font-size: 8px;
        }
        .tpatp-ttd-space {
            height: 28px;
        }

        .tpatp-page-break {
            page-break-before: always;
        }
        .text-center { text-align: center; }
        .font-semibold { font-weight: 600; }
        .font-bold { font-weight: 700; }
    </style>
</head>
<body>
@php
    $identity = $doc['identity'] ?? [];
    $mapel = $identity['mapel'] ?? '-';
    $faseKelas = $identity['fase_kelas'] ?? 'D/VII-IX';
    $tahun = $identity['tahun'] ?? '-';
    $sig = $doc['signature'] ?? [];
    $currentUser = auth()->user();
    $defaultGuruNama = $currentUser?->guru?->nama ?: ($currentUser?->name ?: 'Guru Mata Pelajaran');
    $defaultGuruNik = $currentUser?->guru?->nik ?: ($currentUser?->guru?->nip ?: ($currentUser?->username ?: ''));
    $sigName = !empty($sig['name']) && !str_contains($sig['name'], '......') ? $sig['name'] : $defaultGuruNama;
    $sigNip = !empty($sig['nip']) && !str_contains($sig['nip'], '......') ? $sig['nip'] : $defaultGuruNik;
    $hasTp = !empty($doc['has_tp']) || !empty($doc['tp_table']['rows']);
    $hasAtp = !empty($doc['has_atp']) || !empty($doc['atp_diagram']['nodes']);

    $kop = $kop ?? ($doc['kop'] ?? \App\Support\SchoolLetterhead::lines());
    $kopTeksHtml = $kopTeksHtml ?? ($doc['kop_html'] ?? \App\Support\SchoolLetterhead::kopTeksHtml());
    $kopLogoKiri = $kopLogoKiri ?? ($doc['kop_logo_kiri_data_uri'] ?? \App\Support\SchoolLetterhead::logoKiriDataUri());
    $kopLogoKanan = $kopLogoKanan ?? ($doc['kop_logo_kanan_data_uri'] ?? \App\Support\SchoolLetterhead::logoKananDataUri());
@endphp

@if($doc['parsed'])
    {{-- ==================== BAGIAN TUJUAN PEMBELAJARAN (TP) ==================== --}}
    @if($hasTp)
    @php
        $pdfChunks = $doc['tp_table']['pdf_chunks'] ?? \App\Support\TpAtpDocument::chunkElementsForPdf($doc['tp_table']['elements'] ?? []);
        $totalChunks = count($pdfChunks);
    @endphp

    @if(!empty($pdfChunks))
        @foreach($pdfChunks as $chunkIndex => $chunk)
            @php
                $chunkElements = $chunk['elements'] ?? $chunk;
                $chunkGradeLabel = $chunk['grade_label'] ?? null;
            @endphp
            <div class="tpatp-page-tp {{ $chunkIndex > 0 ? 'tpatp-page-break' : '' }}">
                @if($chunkIndex === 0)
                    <div class="tpatp-kop">
                        <table class="tpatp-kop-table">
                            <tr>
                                <td class="tpatp-kop-logo-cell tpatp-kop-logo-left">
                                    @if(!empty($kopLogoKiri))
                                        <img src="{{ $kopLogoKiri }}" alt="Logo Tut Wuri" class="tpatp-kop-logo">
                                    @endif
                                </td>
                                <td class="tpatp-kop-center-cell">
                                    <div class="tpatp-kop-text">
                                        @if(!empty($kopTeksHtml))
                                            {!! $kopTeksHtml !!}
                                        @elseif(!empty($kop))
                                            @foreach($kop as $idx => $line)
                                                <div class="tpatp-kop-line {{ $idx === 0 ? 'tpatp-kop-line-main' : '' }}">{{ $line }}</div>
                                            @endforeach
                                        @endif
                                    </div>
                                </td>
                                <td class="tpatp-kop-logo-cell tpatp-kop-logo-right">
                                    @if(!empty($kopLogoKanan))
                                        <img src="{{ $kopLogoKanan }}" alt="Logo Sekolah" class="tpatp-kop-logo">
                                    @endif
                                </td>
                            </tr>
                        </table>
                        <div class="tpatp-kop-divider"></div>
                    </div>

                    <div class="tpatp-title">{{ $doc['title_tp'] ?: 'TUJUAN PEMBELAJARAN (TP)' }}</div>

                    <table class="tpatp-identitas">
                        <tr>
                            <td class="lbl">MATA PELAJARAN</td>
                            <td class="sep">:</td>
                            <td class="val">{{ $mapel }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">FASE / KELAS</td>
                            <td class="sep">:</td>
                            <td class="val">{{ $faseKelas }}</td>
                        </tr>
                        <tr>
                            <td class="lbl">TAHUN PELAJARAN</td>
                            <td class="sep">:</td>
                            <td class="val">{{ $tahun }}</td>
                        </tr>
                    </table>

                    @if(!empty($chunkGradeLabel) && $totalChunks > 1)
                        <div class="tpatp-subheading">{{ $chunkGradeLabel }}</div>
                    @endif
                @else
                    <div class="tpatp-chunk-header">
                        <span class="tpatp-chunk-title">{{ $doc['title_tp'] ?: 'TUJUAN PEMBELAJARAN (TP)' }} — Lanjutan</span>
                        @if(!empty($chunkGradeLabel))
                            <span class="tpatp-chunk-grade">({{ $chunkGradeLabel }})</span>
                        @endif
                    </div>
                @endif

                <table class="tpatp-tbl">
                    <thead>
                        <tr>
                            <th style="width: 11%;">ELEMEN</th>
                            <th style="width: 24%;">CAPAIAN PEMBELAJARAN</th>
                            <th style="width: 16%;">LINGKUP MATERI</th>
                            <th style="width: 29%;">TUJUAN PEMBELAJARAN (TP)</th>
                            <th style="width: 7%;">KODE TP</th>
                            <th style="width: 7%;">ALOKASI WAKTU (JP)</th>
                            <th style="width: 6%;">SEMESTER</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($chunkElements as $el)
                            @php $elFirst = true; @endphp
                            @foreach($el['materi_groups'] as $mat)
                                @php $matFirst = true; @endphp
                                @foreach($mat['tps'] as $tpItem)
                                    <tr>
                                        @if($elFirst)
                                            <td class="cell-elemen" rowspan="{{ $el['rowspan'] }}">{{ $el['nama'] }}</td>
                                            <td class="cell-cp" rowspan="{{ $el['rowspan'] }}">{{ $el['cp'] }}</td>
                                            @php $elFirst = false; @endphp
                                        @endif

                                        @if($matFirst)
                                            <td class="cell-materi" rowspan="{{ $mat['rowspan'] }}">{{ $mat['nama'] }}</td>
                                        @endif

                                        <td class="cell-tp">{{ $tpItem['tp'] }}</td>
                                        <td class="cell-code">{{ $tpItem['kode'] }}</td>

                                        @if($matFirst)
                                            <td class="cell-jp" rowspan="{{ $mat['rowspan'] }}">{{ $mat['jp'] }}</td>
                                            <td class="cell-sem" rowspan="{{ $mat['rowspan'] }}">{{ $mat['semester'] }}</td>
                                            @php $matFirst = false; @endphp
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        @endforeach
                    </tbody>
                </table>

                @if($chunkIndex === $totalChunks - 1)
                    <div class="tpatp-ttd-container">
                        <table class="tpatp-ttd-tbl">
                            <tr>
                                <td class="spacer"></td>
                                <td class="sign-cell">
                                    <div class="tpatp-ttd-date">{{ $sig['place_date'] ?: 'Tanjungpinang, 17 Juli 2026' }}</div>
                                    <div class="tpatp-ttd-role">{{ $sig['role'] ?: 'Guru Mata Pelajaran' }}</div>
                                    <div class="tpatp-ttd-space"></div>
                                    <div class="tpatp-ttd-name" style="font-weight: bold; text-decoration: underline;">{{ $sigName }}</div>
                                    <div class="tpatp-ttd-nip">{{ !empty($sigNip) ? 'NIK. ' . $sigNip : 'NIK. -' }}</div>
                                </td>
                            </tr>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    @elseif(!empty($doc['tp_table']['rows']))
        <div class="tpatp-page-tp">
            <div class="tpatp-kop">
                <table class="tpatp-kop-table">
                    <tr>
                        <td class="tpatp-kop-logo-cell tpatp-kop-logo-left">
                            @if(!empty($kopLogoKiri))
                                <img src="{{ $kopLogoKiri }}" alt="Logo Tut Wuri" class="tpatp-kop-logo">
                            @endif
                        </td>
                        <td class="tpatp-kop-center-cell">
                            <div class="tpatp-kop-text">
                                @if(!empty($kopTeksHtml))
                                    {!! $kopTeksHtml !!}
                                @elseif(!empty($kop))
                                    @foreach($kop as $idx => $line)
                                        <div class="tpatp-kop-line {{ $idx === 0 ? 'tpatp-kop-line-main' : '' }}">{{ $line }}</div>
                                    @endforeach
                                @endif
                            </div>
                        </td>
                        <td class="tpatp-kop-logo-cell tpatp-kop-logo-right">
                            @if(!empty($kopLogoKanan))
                                <img src="{{ $kopLogoKanan }}" alt="Logo Sekolah" class="tpatp-kop-logo">
                            @endif
                        </td>
                    </tr>
                </table>
                <div class="tpatp-kop-divider"></div>
            </div>

            <div class="tpatp-title">{{ $doc['title_tp'] ?: 'TUJUAN PEMBELAJARAN (TP)' }}</div>

            <table class="tpatp-identitas">
                <tr>
                    <td class="lbl">MATA PELAJARAN</td>
                    <td class="sep">:</td>
                    <td class="val">{{ $mapel }}</td>
                </tr>
                <tr>
                    <td class="lbl">FASE / KELAS</td>
                    <td class="sep">:</td>
                    <td class="val">{{ $faseKelas }}</td>
                </tr>
                <tr>
                    <td class="lbl">TAHUN PELAJARAN</td>
                    <td class="sep">:</td>
                    <td class="val">{{ $tahun }}</td>
                </tr>
            </table>

            <table class="tpatp-tbl">
                <thead>
                    <tr>
                        <th style="width: 11%;">ELEMEN</th>
                        <th style="width: 24%;">CAPAIAN PEMBELAJARAN</th>
                        <th style="width: 16%;">LINGKUP MATERI</th>
                        <th style="width: 29%;">TUJUAN PEMBELAJARAN (TP)</th>
                        <th style="width: 7%;">KODE TP</th>
                        <th style="width: 7%;">ALOKASI WAKTU (JP)</th>
                        <th style="width: 6%;">SEMESTER</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($doc['tp_table']['rows'] as $r)
                        <tr>
                            <td class="cell-elemen">{{ $r['elemen'] }}</td>
                            <td class="cell-cp">{{ $r['cp'] }}</td>
                            <td class="cell-materi">{{ $r['lingkup_materi'] }}</td>
                            <td class="cell-tp">{{ $r['tp'] }}</td>
                            <td class="cell-code">{{ $r['kode_tp'] }}</td>
                            <td class="cell-jp">{{ $r['jp'] }}</td>
                            <td class="cell-sem">{{ $r['semester'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="tpatp-ttd-container">
                <table class="tpatp-ttd-tbl">
                    <tr>
                        <td class="spacer"></td>
                        <td class="sign-cell">
                            <div class="tpatp-ttd-date">{{ $sig['place_date'] ?: 'Tanjungpinang, 17 Juli 2026' }}</div>
                            <div class="tpatp-ttd-role">{{ $sig['role'] ?: 'Guru Mata Pelajaran' }}</div>
                            <div class="tpatp-ttd-space"></div>
                            <div class="tpatp-ttd-name" style="font-weight: bold; text-decoration: underline;">{{ $sigName }}</div>
                            <div class="tpatp-ttd-nip">{{ !empty($sigNip) ? 'NIK. ' . $sigNip : 'NIK. -' }}</div>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    @endif
    @endif

    {{-- ==================== HALAMAN 2: ALUR TUJUAN PEMBELAJARAN (ATP) ==================== --}}
    @if($hasAtp)
    <div class="tpatp-page-atp {{ $hasTp ? 'tpatp-page-break' : '' }}">
        <div class="tpatp-kop">
            <table class="tpatp-kop-table">
                <tr>
                    <td class="tpatp-kop-logo-cell tpatp-kop-logo-left">
                        @if(!empty($kopLogoKiri))
                            <img src="{{ $kopLogoKiri }}" alt="Logo Tut Wuri" class="tpatp-kop-logo">
                        @endif
                    </td>
                    <td class="tpatp-kop-center-cell">
                        <div class="tpatp-kop-text">
                            @if(!empty($kopTeksHtml))
                                {!! $kopTeksHtml !!}
                            @elseif(!empty($kop))
                                @foreach($kop as $idx => $line)
                                    <div class="tpatp-kop-line {{ $idx === 0 ? 'tpatp-kop-line-main' : '' }}">{{ $line }}</div>
                                @endforeach
                            @endif
                        </div>
                    </td>
                    <td class="tpatp-kop-logo-cell tpatp-kop-logo-right">
                        @if(!empty($kopLogoKanan))
                            <img src="{{ $kopLogoKanan }}" alt="Logo Sekolah" class="tpatp-kop-logo">
                        @endif
                    </td>
                </tr>
            </table>
            <div class="tpatp-kop-divider"></div>
        </div>

        <div class="tpatp-title">{{ $doc['title_atp'] ?: 'ALUR TUJUAN PEMBELAJARAN (ATP)' }}</div>

        <table class="tpatp-identitas">
            <tr>
                <td class="lbl">MATA PELAJARAN</td>
                <td class="sep">:</td>
                <td class="val">{{ $mapel }}</td>
            </tr>
            <tr>
                <td class="lbl">FASE / KELAS</td>
                <td class="sep">:</td>
                <td class="val">{{ $faseKelas }}</td>
            </tr>
            <tr>
                <td class="lbl">TAHUN PELAJARAN</td>
                <td class="sep">:</td>
                <td class="val">{{ $tahun }}</td>
            </tr>
        </table>

        {{-- FLOWCHART RESMI (TABLE BASED UTK DOMPDF DENGAN PEMBAGIAN BARIS PROPORSIONAL) --}}
        @php
            $byGrade = $doc['atp_diagram']['by_grade'] ?? [7 => [], 8 => [], 9 => []];
            $allFlowRows = [];

            foreach ([7, 8, 9] as $g) {
                $gNodes = $byGrade[$g] ?? [];
                if (empty($gNodes)) continue;

                $cnt = count($gNodes);
                if ($cnt <= 6) {
                    $allFlowRows[] = ['grade' => $g, 'is_subrow' => false, 'nodes' => $gNodes];
                } else {
                    $gGanjil = [];
                    $gGenap = [];
                    $curS = 'Ganjil';
                    foreach ($gNodes as $n) {
                        $s = (string) ($n['semester'] ?? '');
                        $j = (string) ($n['judul'] ?? '');
                        if (stripos($s, 'Genap') !== false || stripos($j, 'Semester Genap') !== false) {
                            $curS = 'Genap';
                        }
                        if ($curS === 'Genap') {
                            $gGenap[] = $n;
                        } else {
                            $gGanjil[] = $n;
                        }
                    }

                    $grps = [];
                    if (!empty($gGanjil)) $grps[] = $gGanjil;
                    if (!empty($gGenap)) $grps[] = $gGenap;
                    if (empty($grps)) $grps[] = [$gNodes];

                    foreach ($grps as $grpIdx => $grp) {
                        $allFlowRows[] = ['grade' => $g, 'is_subrow' => ($grpIdx > 0), 'nodes' => $grp];
                    }
                }
            }
        @endphp

        @foreach($allFlowRows as $rIdx => $rowItem)
            @php
                $g = $rowItem['grade'];
                $rNodes = $rowItem['nodes'];
            @endphp
            <table class="pdf-flowchart-table" style="{{ count($rNodes) === 1 ? 'width: 50%; margin: 6px auto;' : (count($rNodes) === 2 ? 'width: 70%; margin: 6px auto;' : 'width: 100%;') }}">
                <tr>
                    @foreach($rNodes as $nodeIdx => $node)
                        @php
                            $type = $node['type'] ?? 'tp';
                            $cls = match(true) {
                                $type === 'start' => 'box-start',
                                $type === 'transition' => 'box-trans',
                                $type === 'end' => 'box-end',
                                $g === 7 => 'box-pink',
                                $g === 8 => 'box-orange',
                                default => 'box-green',
                            };
                            $materi = trim((string) ($node['materi'] ?? ''));
                            $tpDesc = trim((string) ($node['tp_desc'] ?? ''));
                            $judul = trim((string) ($node['judul'] ?? ''));
                            $code = trim((string) ($node['kode'] ?? ''));
                            if ($judul === '-' || $judul === '–') $judul = '';
                            if ($materi === '-' || $materi === '–') $materi = '';
                            if ($tpDesc === '-' || $tpDesc === '–') $tpDesc = '';
                            if ($code === '-' || $code === '–') $code = '';

                            if ($type === 'start' && $judul === '') {
                                $semName = !empty($node['semester']) ? $node['semester'] : 'Ganjil';
                                $judul = "Awal fase D Kelas {$g} Semester {$semName}";
                            } elseif ($type === 'transition' && $judul === '') {
                                $nextG = $g < 9 ? $g + 1 : 9;
                                $judul = "Capaian Pembelajaran Akhir Fase D Kelas {$g} menuju pembelajaran fase D kelas {$nextG} Semester Ganjil";
                            } elseif ($type === 'end' && $judul === '') {
                                $judul = "Capaian Pembelajaran Akhir Fase D";
                            }

                            $dispMateri = $materi !== '' ? $materi : $judul;
                            if ($dispMateri === '' || $dispMateri === '-') {
                                $dispMateri = $tpDesc !== '' ? $tpDesc : ($code !== '' ? "Tujuan Pembelajaran {$code}" : 'Tujuan Pembelajaran');
                            }
                            $dispTp = ($tpDesc !== '' && $tpDesc !== '-' && $tpDesc !== $dispMateri) ? $tpDesc : '';
                        @endphp
                        <td class="flow-box {{ $cls }}">
                            @if($type === 'start' || $type === 'transition' || $type === 'end')
                                <span style="font-weight: bold;">{{ $judul }}</span>
                            @else
                                <span class="box-materi">Materi: {{ $dispMateri }}</span>
                                @if($dispTp !== '')
                                    <span class="box-desc">{{ Str::limit($dispTp, 80, '...') }}</span>
                                @endif
                                @if(!empty($code))
                                    <span class="box-code">({{ $code }})</span>
                                @endif
                            @endif
                        </td>
                        @if($nodeIdx < count($rNodes) - 1)
                            <td class="flow-arrow">➔</td>
                        @endif
                    @endforeach
                </tr>
            </table>
            @if($rIdx < count($allFlowRows) - 1)
                @php $nextR = $allFlowRows[$rIdx + 1]; @endphp
                @if($nextR['grade'] === $g && $nextR['is_subrow'])
                    <div style="text-align: center; color: #64748b; font-size: 11px; font-weight: bold; margin: 1px 0 2px;">↓</div>
                @else
                    <div style="height: 6px;"></div>
                @endif
            @endif
        @endforeach

        @if(!empty($doc['atp_diagram']['summary_rows']))
        <div style="margin-top: 10px;">
            <div class="tpatp-subheading">Ringkasan Alur Tujuan Pembelajaran</div>
            <table class="tpatp-tbl">
                <thead>
                    <tr>
                        <th style="width: 4%;">NO</th>
                        <th style="width: 6%;">KELAS</th>
                        <th style="width: 8%;">SEMESTER</th>
                        <th style="width: 20%;">LINGKUP MATERI</th>
                        <th style="width: 8%;">KODE TP</th>
                        <th style="width: 48%;">TUJUAN PEMBELAJARAN (TP)</th>
                        <th style="width: 6%;">JP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($doc['atp_diagram']['summary_rows'] as $sr)
                    <tr>
                        <td class="text-center">{{ $sr['no'] }}</td>
                        <td class="text-center">{{ $sr['kelas'] }}</td>
                        <td class="text-center">{{ $sr['semester'] }}</td>
                        <td class="font-semibold">{{ $sr['lingkup_materi'] }}</td>
                        <td class="text-center font-bold">{{ $sr['kode_tp'] }}</td>
                        <td>{{ $sr['tp'] }}</td>
                        <td class="text-center">{{ $sr['jp'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="tpatp-ttd-container">
            <table class="tpatp-ttd-tbl">
                <tr>
                    <td class="spacer"></td>
                    <td class="sign-cell">
                        <div class="tpatp-ttd-date">{{ $sig['place_date'] ?: 'Tanjungpinang, 17 Juli 2026' }}</div>
                        <div class="tpatp-ttd-role">{{ $sig['role'] ?: 'Guru Mata Pelajaran' }}</div>
                        <div class="tpatp-ttd-space"></div>
                        <div class="tpatp-ttd-name" style="font-weight: bold; text-decoration: underline;">{{ $sigName }}</div>
                        <div class="tpatp-ttd-nip">{{ !empty($sigNip) ? 'NIK. ' . $sigNip : 'NIK. -' }}</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
    @endif
@else
    <pre style="white-space: pre-wrap; font-size: 9px;">{{ $content }}</pre>
@endif
</body>
</html>
