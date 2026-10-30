<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 12mm 14mm 14mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #000;
            font-size: 9.5px;
            line-height: 1.32;
        }
        /* Kop sekolah */
        .kop { text-align: center; border-bottom: 2.5px solid #000; padding-bottom: 5px; margin-bottom: 8px; }
        .kop .nama { font-weight: 700; font-size: 12px; line-height: 1.25; }
        .kop .sub { font-weight: 700; font-size: 9.5px; }
        .kop .alamat { font-size: 8px; line-height: 1.3; }
        /* Judul */
        .judul { text-align: center; font-weight: 700; font-size: 12px; margin-top: 10px; }
        .subjudul { text-align: center; font-weight: 700; font-size: 10.5px; margin-top: 2px; }
        /* Identitas */
        table.identitas { width: 100%; margin-top: 10px; border-collapse: collapse; table-layout: fixed; }
        table.identitas td { padding: 1.5px 0; vertical-align: top; font-size: 9.5px; }
        table.identitas td.lbl { width: 150px; }
        table.identitas td.sep { width: 12px; }
        /* Tabel bagian utama */
        table.tbl {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            border: 1px solid #000;
            table-layout: fixed;
        }
        table.tbl tr {
            page-break-inside: avoid;
        }
        table.tbl td {
            border: 1px solid #000;
            padding: 4.5px 6.5px;
            vertical-align: top;
            word-wrap: break-word;
            font-size: 9.5px;
        }
        col.col-sec { width: 16%; }
        col.col-sub { width: 22%; }
        col.col-content { width: 62%; }
        table.tbl td.sec {
            width: 16%;
            font-weight: 700;
            text-transform: uppercase;
            background: #f2f2f2;
            vertical-align: middle;
            text-align: center;
            font-size: 9.5px;
            line-height: 1.3;
        }
        table.tbl td.sec-cont {
            border-top: none !important;
            border-bottom: none !important;
        }
        table.tbl td.sec-last {
            border-top: none !important;
            border-bottom: 1px solid #000 !important;
        }
        table.tbl td.sub { width: 22%; font-weight: 700; }
        table.tbl td.stagehead { background: #f2f2f2; font-weight: 700; }
        table.tbl td.stagehead .pilar { font-weight: 400; font-style: italic; font-size: 8.5px; }
        .cell-line { margin: 0 0 2px; }
        .b { font-weight: 700; }
        .check { padding-left: 14px; text-indent: -14px; margin: 0 0 2px; }
        .quote { font-style: italic; margin: 0 0 2px; }
        /* DPL dua kolom */
        table.dpl { width: 100%; border-collapse: collapse; margin-top: 2px; }
        table.dpl td { border: none !important; padding: 1px 4px 1px 0; vertical-align: top; font-weight: 700; font-size: 9px; }
        /* Tanda tangan */
        table.ttd { width: 100%; border-collapse: collapse; margin-top: 16px; page-break-inside: avoid; }
        table.ttd td { padding: 1.5px 4px; vertical-align: top; font-size: 9.5px; }
        table.ttd td.kiri { width: 45%; text-align: left; }
        table.ttd td.kanan { width: 55%; text-align: center; }
        .ttd-date { text-align: right; margin-top: 14px; font-size: 9.5px; }
        .ttd-spacer { height: 46px; }
        /* Lampiran */
        .lampiran { page-break-before: always; }
        .lampiran-heading { font-weight: 700; font-size: 11px; margin-bottom: 6px; }
        .lampiran .cell-line { margin-bottom: 1.5px; line-height: 1.28; }
        .opsi { padding-left: 20px; }
        .nomor { padding-left: 6px; }
        table.rubrik { width: 100%; border-collapse: collapse; margin: 6px 0 8px; table-layout: fixed; }
        table.rubrik td, table.rubrik th { border: 1px solid #000; padding: 3.5px 5px; vertical-align: top; font-size: 8.5px; text-align: left; word-wrap: break-word; }
        table.rubrik tr.head td { background: #fce4b6; font-weight: 700; }
        table.rubrik tr.subhead td { background: #fce4b6; font-weight: 700; }
        /* Fallback teks polos */
        .document { white-space: pre-wrap; word-wrap: break-word; }
        .line { margin: 0 0 3px; }
        .blank { height: 8px; }
        .center { text-align: center; font-weight: 700; line-height: 1.35; }
        .title { text-align: center; font-weight: 700; font-size: 13px; margin: 10px 0 6px; }
        .section { margin-top: 10px; padding: 4px 6px; background: #e5e7eb; border: 1px solid #6b7280; font-weight: 700; text-transform: uppercase; }
        .subsection { margin-top: 7px; font-weight: 700; }
    </style>
</head>
<body>
@if($doc['parsed'])
    @include('ai.partials.learning-document', ['doc' => $doc])
@else
    @include('ai.partials.learning-plain', ['content' => $content])
@endif
</body>
</html>
