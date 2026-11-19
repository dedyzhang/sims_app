<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Naskah Soal Survei — {{ $periode->judul }}</title>
    <style>
        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        @page {
            size: A4 portrait;
            margin: 12mm 14mm 14mm 14mm;
        }

        body {
            font-family: "DejaVu Sans", Arial, Helvetica, sans-serif;
            color: #0f172a;
            background: #f1f5f9;
            margin: 0;
            padding: 0;
            font-size: 9pt;
            line-height: 1.4;
        }

        /* Top Action Bar (Layar Browser Saja) */
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
            gap: 12px;
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
        .btn-pdf {
            background: #dc2626;
            color: #fff;
        }
        .btn-pdf:hover { background: #b91c1c; }

        /* Lembar Kertas A4 */
        .page {
            width: 210mm;
            max-width: 100%;
            min-height: 297mm;
            margin: 20px auto;
            background: #ffffff;
            padding: 14mm 16mm;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border-radius: 3px;
        }

        /* Kop Surat */
        .kop {
            border-bottom: 3px double #000000;
            padding-bottom: 8px;
            margin-bottom: 4mm;
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
            width: 75px;
            text-align: center;
        }
        .kop .logo-cell img {
            width: 70px;
            height: 70px;
            object-fit: contain;
        }
        .kop .ident-cell {
            text-align: center;
            padding: 0 10px;
            line-height: 1.3;
        }
        .kop .ident-cell h1 {
            margin: 0;
            font-size: 13pt;
            font-weight: bold;
            line-height: 1.25;
            color: #000000;
            text-transform: uppercase;
        }
        .kop .ident-cell p {
            margin: 2px 0 0;
            font-size: 8.5pt;
            color: #1e293b;
        }

        /* Judul Dokumen */
        .judul-wrap {
            text-align: center;
            margin-bottom: 4mm;
        }
        .judul-wrap .t1 {
            font-size: 11.5pt;
            font-weight: bold;
            margin: 0;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .judul-wrap .t2 {
            font-size: 9pt;
            color: #475569;
            margin: 2px 0 0;
            font-style: italic;
        }

        /* Tabel Identitas */
        .table-info {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 4mm;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
        }
        .table-info td {
            padding: 4px 8px;
            vertical-align: top;
            border: 1px solid #e2e8f0;
        }
        .table-info .lbl {
            width: 18%;
            color: #475569;
            font-weight: 600;
        }
        .table-info .val {
            width: 32%;
            color: #0f172a;
        }

        /* Petunjuk */
        .petunjuk-box {
            border: 1px solid #94a3b8;
            background: #f8fafc;
            padding: 6px 10px;
            margin-bottom: 4mm;
            font-size: 8pt;
            border-radius: 3px;
            line-height: 1.35;
        }
        .petunjuk-box strong {
            color: #0f172a;
        }
        .skala-badge-wrap {
            margin-top: 3px;
            display: block;
        }
        .skala-badge {
            display: inline-block;
            margin-right: 12px;
            font-weight: 600;
        }

        /* Bagian Soal */
        .section-header {
            background: #e2e8f0;
            border-left: 4px solid #1e40af;
            padding: 4px 8px;
            font-size: 9pt;
            font-weight: bold;
            margin-top: 4mm;
            margin-bottom: 2mm;
            text-transform: uppercase;
            page-break-after: avoid;
        }

        /* Tabel Soal */
        table.table-soal {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 4mm;
        }
        table.table-soal th, table.table-soal td {
            border: 1px solid #94a3b8;
            padding: 4.5px 6px;
            vertical-align: middle;
        }
        table.table-soal th {
            background-color: #f1f5f9;
            font-weight: bold;
            text-align: center;
            font-size: 8pt;
        }
        table.table-soal td.c-no {
            text-align: center;
            font-weight: bold;
            width: 26px;
        }
        table.table-soal td.c-skala {
            text-align: center;
            width: 38px;
        }
        .box-cek {
            display: inline-block;
            width: 13px;
            height: 13px;
            border: 1px solid #475569;
            background: #ffffff;
            border-radius: 2px;
        }
        .badge-negatif {
            display: inline-block;
            margin-left: 6px;
            font-size: 7.5pt;
            color: #92400e;
            font-style: italic;
        }

        /* Pilihan Karakter Grid Table */
        table.table-karakter {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 4mm;
            border: 1px solid #cbd5e1;
            background: #f8fafc;
        }
        table.table-karakter td {
            border: 1px solid #e2e8f0;
            padding: 5px 10px;
            width: 50%;
            vertical-align: middle;
        }

        /* Isian Terbuka */
        .box-terbuka {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            padding: 6px 10px;
            margin-bottom: 3mm;
            font-size: 8.5pt;
            border-radius: 2px;
        }
        .box-terbuka .pertanyaan-label {
            font-weight: 600;
            margin-bottom: 4px;
        }
        .garis-isian {
            border-bottom: 1px dotted #94a3b8;
            height: 18px;
            margin-bottom: 4px;
        }

        /* Tanda Tangan */
        .ttd-wrap {
            margin-top: 6mm;
            width: 100%;
            page-break-inside: avoid;
        }
        .ttd-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            font-size: 8.5pt;
        }
        .ttd-table td {
            border: none;
            text-align: center;
            vertical-align: top;
            width: 50%;
            padding: 0 10px;
        }
        .ttd-space {
            height: 48px;
        }
        .ttd-nama {
            font-weight: bold;
            text-decoration: underline;
        }

        /* Print Media Query */
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
            .section-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            tr {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>

    @if(empty($isPdf))
    {{-- Floating Top Action Bar (Hanya tampil di browser) --}}
    <div class="topbar-wrapper no-print">
        <div class="topbar-left">
            <a href="{{ route('survei.periode', $periode) }}" class="btn btn-back">
                &larr; Kembali ke Detail Survei
            </a>
            <span class="topbar-title">Naskah Soal: {{ \Illuminate\Support\Str::limit($periode->judul, 45) }}</span>
            <span class="topbar-badge">A4 Portrait</span>
        </div>
        <div class="topbar-right">
            <button type="button" onclick="window.print()" class="btn btn-print">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"></polyline><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path><rect x="6" y="14" width="12" height="8"></rect></svg>
                Cetak Soal (Ctrl+P)
            </button>
            <a href="{{ route('survei.pertanyaan.cetak', [$periode, 'format' => 'pdf']) }}" class="btn btn-pdf" title="Unduh naskah soal dalam format PDF">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
                Unduh PDF
            </a>
        </div>
    </div>
    @endif

    <div class="page">
        {{-- Kop Surat Resmi --}}
        <div class="kop">
            <table>
                <tr>
                    @if(!empty($logoKiri))
                    <td class="logo-cell">
                        <img src="{{ $logoKiri }}" alt="Logo">
                    </td>
                    @endif
                    <td class="ident-cell">
                        @if(!empty($kopLines))
                            @foreach($kopLines as $i => $line)
                                @if($i === 0)
                                    <h1>{{ $line }}</h1>
                                @else
                                    <p>{{ $line }}</p>
                                @endif
                            @endforeach
                        @else
                            <h1>{{ $sekolah }}</h1>
                            <p>Instrumen Resmi Evaluasi Persepsi Siswa</p>
                        @endif
                    </td>
                    @if(!empty($logoKanan))
                    <td class="logo-cell">
                        <img src="{{ $logoKanan }}" alt="Logo Kanan">
                    </td>
                    @endif
                </tr>
            </table>
        </div>

        {{-- Judul Dokumen --}}
        <div class="judul-wrap">
            <h2 class="t1">KUESIONER & INSTRUMEN SOAL SURVEI PEMBELAJARAN</h2>
            <p class="t2">Evaluasi Persepsi Siswa Terhadap Proses Belajar Mengajar & Pelayanan Wali Kelas</p>
        </div>

        {{-- Informasi Periode --}}
        <table class="table-info">
            <tr>
                <td class="lbl">Nama Survei</td>
                <td class="val"><strong>{{ $periode->judul }}</strong></td>
                <td class="lbl">Jadwal Pengisian</td>
                <td class="val">{{ $periode->mulai->format('d/m/Y H:i') }} – {{ $periode->selesai->format('d/m/Y H:i') }} WIB</td>
            </tr>
            <tr>
                <td class="lbl">Sasaran Kelas</td>
                <td class="val">{{ $kelasSasaran->isEmpty() ? 'Seluruh Kelas' : $kelasSasaran->implode(', ') }}</td>
                <td class="lbl">Sifat Pengisian</td>
                <td class="val">Rahasia & Mandiri (Satu Siswa Satu Kali)</td>
            </tr>
        </table>

        {{-- Petunjuk Pengisian --}}
        <div class="petunjuk-box">
            <strong>PETUNJUK PENGERJAAN:</strong> Jawablah pernyataan di bawah ini dengan jujur sesuai pengalaman belajarmu bersama guru yang dinilai selama semester ini. Berikan tanda centang (&checkmark;) pada salah satu kolom pilihan skala berikut:
            <div class="skala-badge-wrap">
                <span class="skala-badge">1 = Tidak Pernah (TP)</span>
                <span class="skala-badge">3 = Kadang-kadang (KD)</span>
                <span class="skala-badge">5 = Selalu (SL)</span>
                <span class="skala-badge">0 = Tidak Tahu / Belum Mengalami (TT)</span>
            </div>
        </div>

        {{-- BAGIAN A: Guru Mapel --}}
        <div class="section-header">
            A. Instrumen Evaluasi Guru Mata Pelajaran ({{ count($butirMapel) }} Butir Pertanyaan)
        </div>
        <table class="table-soal">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 26px;">No</th>
                    <th rowspan="2">Aspek / Pernyataan Evaluasi Pembelajaran Guru</th>
                    <th colspan="4" style="width: 160px;">Pilihan Jawaban</th>
                </tr>
                <tr>
                    <th style="width: 40px;">1<br><small style="font-weight: normal;">(TP)</small></th>
                    <th style="width: 40px;">3<br><small style="font-weight: normal;">(KD)</small></th>
                    <th style="width: 40px;">5<br><small style="font-weight: normal;">(SL)</small></th>
                    <th style="width: 40px;">0<br><small style="font-weight: normal;">(TT)</small></th>
                </tr>
            </thead>
            <tbody>
                @foreach($butirMapel as $idx => $teks)
                <tr>
                    <td class="c-no">{{ $idx + 1 }}</td>
                    <td>
                        {{ $teks }}
                        @if(in_array((int) $idx, array_map('intval', (array) $negatifMapel)))
                            <span class="badge-negatif">(*) Butir negatif (skor dibalik)</span>
                        @endif
                    </td>
                    <td class="c-skala"><span class="box-cek"></span></td>
                    <td class="c-skala"><span class="box-cek"></span></td>
                    <td class="c-skala"><span class="box-cek"></span></td>
                    <td class="c-skala"><span class="box-cek"></span></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- BAGIAN B: Wali Kelas --}}
        <div class="section-header">
            B. Instrumen Evaluasi Wali Kelas ({{ count($butirWali) }} Butir Pertanyaan)
        </div>
        <table class="table-soal">
            <thead>
                <tr>
                    <th rowspan="2" style="width: 26px;">No</th>
                    <th rowspan="2">Aspek / Pernyataan Pembimbingan & Pelayanan Wali Kelas</th>
                    <th colspan="4" style="width: 160px;">Pilihan Jawaban</th>
                </tr>
                <tr>
                    <th style="width: 40px;">1<br><small style="font-weight: normal;">(TP)</small></th>
                    <th style="width: 40px;">3<br><small style="font-weight: normal;">(KD)</small></th>
                    <th style="width: 40px;">5<br><small style="font-weight: normal;">(SL)</small></th>
                    <th style="width: 40px;">0<br><small style="font-weight: normal;">(TT)</small></th>
                </tr>
            </thead>
            <tbody>
                @foreach($butirWali as $idx => $teks)
                <tr>
                    <td class="c-no">{{ $idx + 1 }}</td>
                    <td>
                        {{ $teks }}
                        @if(in_array((int) $idx, array_map('intval', (array) $negatifWali)))
                            <span class="badge-negatif">(*) Butir negatif (skor dibalik)</span>
                        @endif
                    </td>
                    <td class="c-skala"><span class="box-cek"></span></td>
                    <td class="c-skala"><span class="box-cek"></span></td>
                    <td class="c-skala"><span class="box-cek"></span></td>
                    <td class="c-skala"><span class="box-cek"></span></td>
                </tr>
                @endforeach
            </tbody>
        </table>

        {{-- BAGIAN C: Pilihan Karakter --}}
        @if(!empty($opsiKarakter))
        <div class="section-header">
            C. Pertanyaan Persepsi Karakter Guru (Dropdown Siswa)
        </div>
        <p style="margin: 2px 0 6px; font-size: 8.5pt; color: #334155;">
            <strong>Pertanyaan:</strong> <em>"Menurut kamu, guru ini termasuk guru yang ..."</em>
            <span style="color: #64748b;">(Siswa memilih 1 karakteristik yang paling mewakili sosok guru)</span>
        </p>
        <table class="table-karakter">
            <tbody>
                @php
                    $chunks = array_chunk($opsiKarakter, 2);
                @endphp
                @foreach($chunks as $row)
                <tr>
                    @foreach($row as $karakter)
                    <td>
                        <span class="box-cek" style="margin-right: 6px; vertical-align: middle;"></span>
                        <span>{{ $karakter }}</span>
                    </td>
                    @endforeach
                    @if(count($row) === 1)
                    <td></td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        {{-- BAGIAN D: Masukan Terbuka & Kendala --}}
        <div class="section-header">
            D. Pertanyaan Terbuka / Masukan & Kendala
        </div>
        <div class="box-terbuka">
            <div class="pertanyaan-label">1. Masukan untuk Guru Mata Pelajaran:</div>
            <p style="margin: 2px 0 4px; font-size: 8pt; color: #475569;">a. Satu hal yang sudah baik dari cara guru mengajar:</p>
            <div class="garis-isian"></div>
            <div class="garis-isian"></div>

            <p style="margin: 6px 0 4px; font-size: 8pt; color: #475569;">b. Satu hal yang perlu diperbaiki / ditingkatkan dari cara guru mengajar:</p>
            <div class="garis-isian"></div>
            <div class="garis-isian"></div>
        </div>

        <div class="box-terbuka">
            <div class="pertanyaan-label">2. Kendala dengan Wali Kelas / Pembelajaran:</div>
            <p style="margin: 2px 0 4px; font-size: 8pt; color: #475569;">Apakah terdapat kendala khusus dalam bimbingan wali kelas atau sarana pembelajaran?</p>
            <div style="margin-top: 4px;">
                <span class="box-cek" style="margin-right: 4px; vertical-align: middle;"></span> <span style="margin-right: 18px;">Tidak ada kendala</span>
                <span class="box-cek" style="margin-right: 4px; vertical-align: middle;"></span> <span>Ada kendala (Tuliskan rincian di bawah ini)</span>
            </div>
            <div class="garis-isian" style="margin-top: 6px;"></div>
        </div>

        {{-- Lembar Pengesahan --}}
        <div class="ttd-wrap">
            <table class="ttd-table">
                <tr>
                    <td>
                        <p>Mengetahui,</p>
                        <p style="font-weight: bold;">Kepala Sekolah</p>
                        <div class="ttd-space"></div>
                        <p class="ttd-nama">{{ $kepala }}</p>
                        @if(!empty($nipKepala))
                            <p style="font-size: 8pt; color: #475569;">NIP. {{ $nipKepala }}</p>
                        @endif
                    </td>
                    <td>
                        <p>{{ $kota ?? (\App\Models\Setting::get('kota', 'Tempat')) }}, {{ now()->translatedFormat('d F Y') }}</p>
                        <p style="font-weight: bold;">Tim Penjamin Mutu / Surveyor</p>
                        <div class="ttd-space"></div>
                        <p class="ttd-nama">{{ auth()->user()->name ?? 'Administrator SIMS' }}</p>
                        <p style="font-size: 8pt; color: #475569;">Petugas Pelaksana Survei</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>

</body>
</html>
