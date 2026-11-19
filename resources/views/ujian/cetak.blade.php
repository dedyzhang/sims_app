<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naskah Soal — {{ $ujian->judul }}</title>
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page {
            size: A4 portrait;
            margin: 14mm 15mm 15mm 15mm;
        }

        body {
            font-family: "DejaVu Sans", "Times New Roman", Georgia, serif;
            color: #0f172a;
            background: #f1f5f9;
            margin: 0;
            padding: 0;
            font-size: 11pt;
            line-height: 1.45;
        }

        /* Top Bar Floating (Layar Saja / No Print) */
        .topbar-wrapper {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #1e293b;
            color: #fff;
            padding: 10px 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .topbar-left {
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .topbar-title {
            font-size: 13px;
            font-weight: 600;
            color: #f8fafc;
        }
        .topbar-badge {
            background: #334155;
            color: #cbd5e1;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 9999px;
            font-family: sans-serif;
        }
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-family: sans-serif;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 14px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
            transition: all 0.15s ease;
        }
        .btn-back {
            background: transparent;
            color: #94a3b8;
            border-color: #475569;
        }
        .btn-back:hover { background: #334155; color: #fff; }
        .btn-print {
            background: #2563eb;
            color: #fff;
        }
        .btn-print:hover { background: #1d4ed8; }
        .btn-word {
            background: #0284c7;
            color: #fff;
        }
        .btn-word:hover { background: #0369a1; }
        .btn-pdf {
            background: #e11d48;
            color: #fff;
        }
        .btn-pdf:hover { background: #be123c; }
        .checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-family: sans-serif;
            color: #e2e8f0;
            cursor: pointer;
            user-select: none;
            margin-right: 6px;
        }
        .checkbox-label input { cursor: pointer; }

        /* Kertas A4 Portrait */
        .page {
            width: 210mm;
            max-width: 100%;
            min-height: 297mm;
            margin: 20px auto;
            background: #ffffff;
            padding: 16mm 18mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-radius: 2px;
        }

        /* Kop Surat Sekolah */
        .kop {
            border-bottom: 3.5px double #000000;
            padding-bottom: 8px;
            margin-bottom: 5mm;
        }
        .kop table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }
        .kop td {
            vertical-align: middle;
            border: none;
        }
        .kop .logo-cell {
            width: 85px;
            text-align: center;
            vertical-align: middle;
        }
        .kop .logo-cell img {
            width: 80px;
            height: 80px;
            max-width: 80px;
            max-height: 80px;
            object-fit: contain;
        }
        .kop .ident-cell {
            text-align: center;
            padding: 0 10px;
            font-size: 9pt;
            line-height: 1.3;
        }
        .kop .ident-cell h1, .kop .ident-cell h2 {
            margin: 0;
            font-size: 13pt;
            font-weight: bold;
            line-height: 1.25;
            color: #000000;
        }
        .kop .ident-cell p {
            margin: 2px 0 0;
            line-height: 1.3;
            color: #1e293b;
        }

        /* Judul Naskah */
        .judul-wrap {
            text-align: center;
            margin-bottom: 5mm;
        }
        .judul-wrap .t1 {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .judul-wrap .t2 {
            font-size: 11.5pt;
            font-weight: bold;
            margin: 2px 0 0;
            text-transform: uppercase;
        }
        .judul-wrap .t3 {
            font-size: 9.5pt;
            font-style: italic;
            margin: 2px 0 0;
            color: #334155;
        }

        /* Tabel Identitas Ujian */
        table.tabel-identitas {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 5mm;
        }
        table.tabel-identitas td {
            padding: 2px 4px;
            vertical-align: top;
            border: none;
        }
        table.tabel-identitas td.lbl {
            width: 18%;
            font-weight: bold;
            color: #1e293b;
        }
        table.tabel-identitas td.sep {
            width: 2%;
            text-align: center;
        }
        table.tabel-identitas td.val {
            width: 30%;
            color: #0f172a;
        }

        /* Kotak Petunjuk Pengerjaan */
        .petunjuk-box {
            border: 1px solid #94a3b8;
            background-color: #f8fafc;
            padding: 7px 12px;
            border-radius: 4px;
            margin-bottom: 6mm;
            font-size: 9pt;
        }
        .petunjuk-title {
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
            font-size: 9pt;
            color: #1e293b;
        }
        .petunjuk-list {
            margin: 0;
            padding-left: 18px;
        }
        .petunjuk-list li {
            margin-bottom: 2px;
            color: #334155;
        }

        /* Judul Seksi Soal */
        .seksi-title {
            font-size: 10.5pt;
            font-weight: bold;
            border-bottom: 1.5px solid #0f172a;
            padding-bottom: 3px;
            margin-bottom: 5mm;
            text-transform: uppercase;
        }

        /* Daftar Butir Soal */
        .soal-card {
            page-break-inside: avoid;
            margin-bottom: 5mm;
        }
        table.soal-layout {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }
        table.soal-layout td {
            vertical-align: top;
            border: none;
            padding: 0;
        }
        table.soal-layout td.no-col {
            width: 24px;
            font-weight: bold;
            font-size: 10.5pt;
        }
        table.soal-layout td.content-col {
            width: auto;
        }

        .soal-teks {
            font-size: 10.5pt;
            margin-bottom: 3px;
            word-break: break-word;
        }
        .soal-teks *, .opsi-teks * {
            font-family: inherit !important;
        }
        .soal-teks p {
            margin: 0 0 4px;
        }
        .soal-poin {
            font-size: 8.5pt;
            font-style: italic;
            font-weight: normal;
            color: #64748b;
            margin-left: 6px;
        }

        /* Gambar & Rumus Matematika dalam Soal */
        .soal-teks img:not(.math-svg), .opsi-teks img:not(.math-svg), .match-content img:not(.math-svg) {
            max-width: 80%;
            max-height: 200px;
            object-fit: contain;
            display: block;
            margin: 6px 0;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
        }
        .math-svg {
            display: inline-block;
            vertical-align: middle;
            max-height: 2.2em;
        }

        /* Opsi MCQ & MCQ Kompleks */
        .opsi-container {
            margin-top: 4px;
            padding-left: 4px;
        }
        .opsi-row {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            margin-bottom: 3px;
            font-size: 10pt;
            page-break-inside: avoid;
        }
        .opsi-lbl {
            font-weight: bold;
            min-width: 20px;
            flex-shrink: 0;
        }
        .opsi-cb {
            font-weight: normal;
            font-family: monospace;
            min-width: 38px;
            flex-shrink: 0;
        }
        .opsi-teks {
            flex: 1;
        }
        .opsi-teks p {
            margin: 0;
        }

        .petunjuk-sub {
            font-size: 8.5pt;
            font-style: italic;
            color: #64748b;
            margin: 2px 0 4px;
        }

        /* Benar / Salah */
        .tf-box {
            margin-top: 5px;
            display: flex;
            gap: 25px;
            font-size: 10pt;
            font-family: sans-serif;
            font-weight: 500;
        }

        /* Mencocokkan / Match */
        table.match-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-top: 5px;
            page-break-inside: avoid;
        }
        table.match-table th, table.match-table td {
            border: 1px solid #64748b;
            padding: 5px 8px;
            vertical-align: top;
        }
        table.match-table th {
            background-color: #f1f5f9;
            text-align: center;
            font-weight: bold;
        }
        .match-num {
            font-weight: bold;
            margin-right: 4px;
        }
        .match-content {
            display: inline;
        }
        .match-content p {
            display: inline;
            margin: 0;
        }

        /* Esai: Garis Tulis Jawaban Siswa */
        .essay-box {
            margin-top: 6px;
            padding-left: 2px;
        }
        .essay-line {
            border-bottom: 1px dotted #94a3b8;
            height: 18px;
            margin-bottom: 4px;
        }

        /* Kunci Jawaban (Halaman Terpisah) */
        .kunci-wrapper {
            page-break-before: always;
            margin-top: 10mm;
            padding-top: 5mm;
            border-top: 1.5px dashed #64748b;
        }
        .kunci-header {
            text-align: center;
            margin-bottom: 6mm;
        }
        .kunci-title {
            font-size: 13pt;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        .kunci-sub {
            font-size: 10.5pt;
            font-style: italic;
            margin: 2px 0 0;
            color: #334155;
        }

        table.kunci-pg-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 5mm;
        }
        table.kunci-pg-table td {
            border: 1px solid #94a3b8;
            padding: 4px 8px;
            vertical-align: middle;
            font-weight: bold;
        }

        .kunci-seksi-title {
            font-size: 10pt;
            font-weight: bold;
            margin: 12px 0 4px;
            color: #0f172a;
        }
        .kunci-list {
            margin: 0;
            padding-left: 20px;
            font-size: 9.5pt;
        }
        .kunci-list li {
            margin-bottom: 3px;
        }

        .hidden-kunci {
            display: none !important;
        }

        /* Media Print Optimization */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: #ffffff !important;
                padding: 0 !important;
            }
            .page {
                width: 100% !important;
                max-width: none !important;
                min-height: auto !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }
            .kunci-wrapper {
                border-top: none !important;
            }
        }
    </style>
</head>
<body>

    {{-- Top Action Bar (Hanya tampil di layar browser) --}}
    @if(empty($isPdf))
    <div class="topbar-wrapper no-print">
        <div class="topbar-left">
            <a href="{{ route('ujian.edit', $ujian) }}" class="btn btn-back">
                &larr; Kembali
            </a>
            <span class="topbar-title">Naskah Soal: {{ \Illuminate\Support\Str::limit($ujian->judul, 40) }}</span>
            <span class="topbar-badge">A4 Portrait</span>
        </div>
        <div class="topbar-right">
            <label class="checkbox-label" title="Tampilkan atau sembunyikan lembar kunci jawaban">
                <input type="checkbox" id="chkKunci" {{ $includeKunci ? 'checked' : '' }} onchange="toggleKunci(this.checked)">
                Sertakan Kunci Jawaban
            </label>
            <button type="button" onclick="window.print()" class="btn btn-print">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak Naskah (Ctrl+P)
            </button>
            <a href="{{ route('ujian.soal.export', ['ujian' => $ujian, 'format' => 'docx']) }}" class="btn btn-word" title="Unduh berkas Microsoft Word (.docx) siap edit">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
                Unduh Word (.docx)
            </a>
            <a id="btnDownloadPdf" href="{{ route('ujian.soal.cetak', ['ujian' => $ujian, 'download' => 1]) }}" class="btn btn-pdf" title="Unduh berkas PDF siap cetak">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Unduh PDF
            </a>
        </div>
    </div>
    @endif

    {{-- Kertas Dokumen Naskah Ujian --}}
    <div class="page">
        {{-- Kop Surat Resmi Sekolah --}}
        <div class="kop">
            <table>
                <tr>
                    <td class="logo-cell">
                        @if(!empty($kopLogoKiri))
                            <img src="{{ $kopLogoKiri }}" alt="Logo">
                        @endif
                    </td>
                    <td class="ident-cell">
                        @if(!empty($kopTeks))
                            {!! \App\Support\RichText::cleanKop($kopTeks) !!}
                        @else
                            <h2>{{ $namaSekolah }}</h2>
                        @endif
                    </td>
                    <td class="logo-cell">
                        @if(!empty($kopLogoKanan))
                            <img src="{{ $kopLogoKanan }}" alt="Logo">
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        {{-- Judul Naskah Ujian --}}
        <div class="judul-wrap">
            <h1 class="t1">NASKAH SOAL UJIAN</h1>
            <h2 class="t2">{{ mb_strtoupper($ujian->judul) }}</h2>
            @if($ujian->paket?->nama)
                <p class="t3">{{ mb_strtoupper($ujian->paket->nama) }}</p>
            @endif
        </div>

        {{-- Identitas Ujian --}}
        @php
            $tipeCount = [];
            foreach ($soalList as $s) {
                $tipeCount[$s->typeLabel()] = ($tipeCount[$s->typeLabel()] ?? 0) + 1;
            }
            $bentukSoal = [];
            foreach ($tipeCount as $lbl => $c) {
                $bentukSoal[] = "{$lbl} ({$c})";
            }
            $bentukSoalStr = $bentukSoal !== [] ? implode(', ', $bentukSoal) : '-';
        @endphp
        <table class="tabel-identitas">
            <tr>
                <td class="lbl">Mata Pelajaran</td>
                <td class="sep">:</td>
                <td class="val">{{ $ujian->pelajaran?->nama ?? '-' }}</td>
                <td class="lbl">Hari / Tanggal</td>
                <td class="sep">:</td>
                <td class="val">{{ $tanggalUjian ?? '................................' }}</td>
            </tr>
            <tr>
                <td class="lbl">Tingkat / Kelas</td>
                <td class="sep">:</td>
                <td class="val">{{ $kelasList !== '' ? $kelasList : '-' }}</td>
                <td class="lbl">Waktu / Durasi</td>
                <td class="sep">:</td>
                <td class="val">{{ $ujian->durasi_menit }} Menit</td>
            </tr>
            <tr>
                <td class="lbl">Guru Pengampu</td>
                <td class="sep">:</td>
                <td class="val">{{ $guruPengampu }}</td>
                <td class="lbl">Bentuk Soal</td>
                <td class="sep">:</td>
                <td class="val">{{ $bentukSoalStr }}</td>
            </tr>
        </table>

        {{-- Petunjuk Pengerjaan --}}
        <div class="petunjuk-box">
            <div class="petunjuk-title">PETUNJUK PENGERJAAN :</div>
            <ol class="petunjuk-list">
                <li>Berdoalah sebelum mulai mengerjakan soal.</li>
                <li>Tulislah nama dan identitas Anda pada lembar jawaban yang telah disediakan.</li>
                <li>Bacalah setiap butir soal dengan teliti dan cermat sebelum menjawab.</li>
                <li>Dahulukan menjawab soal-soal yang Anda anggap lebih mudah.</li>
                <li>Periksa kembali seluruh lembar jawaban Anda sebelum diserahkan kepada pengawas ujian.</li>
                @if(!empty($ujian->instruksi))
                    <li><strong>Petunjuk Khusus:</strong> {{ trim(strip_tags((string) $ujian->instruksi)) }}</li>
                @endif
            </ol>
        </div>

        <div class="seksi-title">DAFTAR SOAL</div>

        {{-- Butir-Butir Soal --}}
        <div class="soal-list">
            @forelse($soalList as $index => $soal)
                <div class="soal-card">
                    <table class="soal-layout">
                        <tr>
                            <td class="no-col">{{ $index + 1 }}.</td>
                            <td class="content-col">
                                <div class="soal-teks">
                                    {!! \App\Support\RichText::clean($soal->teks_soal) !!}
                                    <span class="soal-poin">({{ $soal->poin }} Poin)</span>
                                </div>

                                {{-- Pilihan Ganda --}}
                                @if($soal->tipe === 'mcq')
                                    <div class="opsi-container">
                                        @foreach($soal->opsi as $j => $op)
                                            <div class="opsi-row">
                                                <span class="opsi-lbl">{{ chr(65 + $j) }}.</span>
                                                <div class="opsi-teks">{!! \App\Support\RichText::clean($op->teks_opsi) !!}</div>
                                            </div>
                                        @endforeach
                                    </div>

                                {{-- Pilihan Ganda Kompleks --}}
                                @elseif($soal->tipe === 'mcq_complex')
                                    <div class="petunjuk-sub">*(Pilihlah lebih dari satu jawaban yang benar)*</div>
                                    <div class="opsi-container">
                                        @foreach($soal->opsi as $j => $op)
                                            <div class="opsi-row">
                                                <span class="opsi-cb">[ &nbsp; ] {{ chr(65 + $j) }}.</span>
                                                <div class="opsi-teks">{!! \App\Support\RichText::clean($op->teks_opsi) !!}</div>
                                            </div>
                                        @endforeach
                                    </div>

                                {{-- Benar / Salah --}}
                                @elseif($soal->tipe === 'true_false')
                                    <div class="petunjuk-sub">*(Tentukan apakah pernyataan berikut Benar atau Salah)*</div>
                                    <div class="tf-box">
                                        <span>[ &nbsp; ] Benar</span>
                                        <span>[ &nbsp; ] Salah</span>
                                    </div>

                                {{-- Mencocokkan --}}
                                @elseif($soal->tipe === 'match')
                                    <div class="petunjuk-sub">*(Jodohkan pernyataan Kolom Kiri dengan jawaban yang sesuai di Kolom Kanan)*</div>
                                    @php $pairs = $soal->meta['pairs'] ?? []; @endphp
                                    @if(!empty($pairs))
                                        <table class="match-table">
                                            <thead>
                                                <tr>
                                                    <th width="50%">Kolom Kiri (Pernyataan)</th>
                                                    <th width="50%">Kolom Kanan (Pilihan Jawaban)</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($pairs as $pj => $pair)
                                                    <tr>
                                                        <td>
                                                            <span class="match-num">{{ $pj + 1 }}.</span>
                                                            <div class="match-content">{!! \App\Support\RichText::clean($pair['left'] ?? $pair['kiri'] ?? '') !!}</div>
                                                        </td>
                                                        <td>
                                                            <span class="match-num">{{ chr(65 + $pj) }}.</span>
                                                            <div class="match-content">{!! \App\Support\RichText::clean($pair['right'] ?? $pair['kanan'] ?? '') !!}</div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif

                                {{-- Esai --}}
                                @elseif($soal->tipe === 'essay')
                                    <div class="essay-box">
                                        <div class="essay-line"></div>
                                        <div class="essay-line"></div>
                                        <div class="essay-line"></div>
                                        <div class="essay-line"></div>
                                    </div>
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            @empty
                <p style="text-align: center; font-style: italic; color: #64748b; padding: 20px 0;">Belum ada butir soal dalam ujian ini.</p>
            @endforelse
        </div>

        {{-- Bagian Kunci Jawaban (Halaman Terpisah) --}}
        @php
            $pgList = [];
            $kompleksList = [];
            $tfList = [];
            $matchList = [];
            $esaiList = [];

            foreach ($soalList as $idx => $s) {
                $no = $idx + 1;
                if ($s->tipe === 'mcq') {
                    $kunciHuruf = [];
                    foreach ($s->opsi as $j => $op) {
                        if ($op->is_benar) {
                            $kunciHuruf[] = chr(65 + $j);
                        }
                    }
                    $pgList[] = ['number' => $no, 'answer' => implode(', ', $kunciHuruf)];
                } elseif ($s->tipe === 'mcq_complex') {
                    $kunciHuruf = [];
                    foreach ($s->opsi as $j => $op) {
                        if ($op->is_benar) {
                            $kunciHuruf[] = chr(65 + $j) . ' (' . \App\Support\RichText::toPlainText($op->teks_opsi) . ')';
                        }
                    }
                    $kompleksList[] = ['number' => $no, 'answer' => implode('; ', $kunciHuruf)];
                } elseif ($s->tipe === 'true_false') {
                    $jawaban = [];
                    foreach ($s->opsi as $op) {
                        if ($op->is_benar) {
                            $jawaban[] = \App\Support\RichText::toPlainText($op->teks_opsi);
                        }
                    }
                    $tfList[] = ['number' => $no, 'answer' => implode(', ', $jawaban) ?: 'Benar'];
                } elseif ($s->tipe === 'match') {
                    $pairs = $s->meta['pairs'] ?? [];
                    $pairAnswers = [];
                    foreach ($pairs as $pj => $pair) {
                        $pairAnswers[] = ($pj + 1) . ' -> ' . chr(65 + $pj);
                    }
                    $matchList[] = ['number' => $no, 'answer' => implode(', ', $pairAnswers)];
                } elseif ($s->tipe === 'essay') {
                    $penjelasan = trim((string) $s->penjelasan);
                    $esaiList[] = ['number' => $no, 'rubrik' => $penjelasan !== '' ? $penjelasan : 'Jawaban dievaluasi sesuai kelengkapan dan ketepatan konsep.'];
                }
            }
        @endphp

        <div id="section-kunci" class="kunci-wrapper {{ $includeKunci ? '' : 'hidden-kunci' }}">
            <div class="kunci-header">
                <h2 class="kunci-title">KUNCI JAWABAN &amp; PEDOMAN PENILAIAN</h2>
                <p class="kunci-sub">{{ mb_strtoupper($ujian->judul) }}</p>
            </div>

            {{-- 1. Kunci Pilihan Ganda (Tabel 2 Kolom) --}}
            @if(!empty($pgList))
                <div class="kunci-seksi-title">A. Pilihan Ganda</div>
                @php
                    $half = (int) ceil(count($pgList) / 2);
                    $left = array_slice($pgList, 0, $half);
                    $right = array_slice($pgList, $half);
                @endphp
                <table class="kunci-pg-table">
                    @for($i = 0; $i < $half; $i++)
                        <tr>
                            <td width="50%">
                                @if(isset($left[$i]))
                                    No. {{ $left[$i]['number'] }} : {{ $left[$i]['answer'] }}
                                @endif
                            </td>
                            <td width="50%">
                                @if(isset($right[$i]))
                                    No. {{ $right[$i]['number'] }} : {{ $right[$i]['answer'] }}
                                @endif
                            </td>
                        </tr>
                    @endfor
                </table>
            @endif

            {{-- 2. Kunci Pilihan Ganda Kompleks --}}
            @if(!empty($kompleksList))
                <div class="kunci-seksi-title">B. Pilihan Ganda Kompleks</div>
                <ul class="kunci-list">
                    @foreach($kompleksList as $item)
                        <li><strong>No. {{ $item['number'] }}:</strong> {{ $item['answer'] }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- 3. Kunci Benar / Salah --}}
            @if(!empty($tfList))
                <div class="kunci-seksi-title">C. Benar / Salah</div>
                <ul class="kunci-list">
                    @foreach($tfList as $item)
                        <li><strong>No. {{ $item['number'] }}:</strong> {{ $item['answer'] }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- 4. Kunci Mencocokkan --}}
            @if(!empty($matchList))
                <div class="kunci-seksi-title">D. Mencocokkan</div>
                <ul class="kunci-list">
                    @foreach($matchList as $item)
                        <li><strong>No. {{ $item['number'] }}:</strong> Pasangan Benar: {{ $item['answer'] }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- 5. Pedoman Penilaian Esai --}}
            @if(!empty($esaiList))
                <div class="kunci-seksi-title">E. Esai &amp; Pedoman Penilaian</div>
                <ul class="kunci-list">
                    @foreach($esaiList as $item)
                        <li><strong>Soal No. {{ $item['number'] }}:</strong> {{ $item['rubrik'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    @if(empty($isPdf))
    <script>
        function toggleKunci(show) {
            const el = document.getElementById('section-kunci');
            if (el) {
                if (show) {
                    el.classList.remove('hidden-kunci');
                } else {
                    el.classList.add('hidden-kunci');
                }
            }
            const pdfBtn = document.getElementById('btnDownloadPdf');
            if (pdfBtn) {
                pdfBtn.href = "{{ route('ujian.soal.cetak', $ujian) }}?download=1&kunci=" + (show ? '1' : '0');
            }
        }
    </script>
    @endif
</body>
</html>
