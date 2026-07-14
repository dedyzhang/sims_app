<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 14mm 16mm 16mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #000;
            font-size: 10px;
            line-height: 1.4;
        }
        /* Kop sekolah */
        .kop { text-align: center; border-bottom: 3px solid #000; padding-bottom: 6px; }
        .kop .nama { font-weight: 700; font-size: 13px; line-height: 1.25; }
        .kop .sub { font-weight: 700; font-size: 10px; }
        .kop .alamat { font-size: 8px; line-height: 1.3; }
        /* Judul */
        .judul { text-align: center; font-weight: 700; font-size: 13px; margin-top: 12px; }
        .subjudul { text-align: center; font-weight: 700; font-size: 11px; margin-top: 2px; }
        /* Identitas */
        table.identitas { margin-top: 10px; border-collapse: collapse; }
        table.identitas td { padding: 1px 0; vertical-align: top; }
        table.identitas td.lbl { width: 150px; }
        table.identitas td.sep { width: 12px; }
        /* Tabel bagian utama */
        table.tbl { width: 100%; border-collapse: collapse; margin-top: 12px; border: 1px solid #000; }
        table.tbl td { border: 1px solid #000; padding: 5px 7px; vertical-align: top; }
        table.tbl td.sec {
            width: 15%; font-weight: 700; text-transform: uppercase;
            background: #f2f2f2; vertical-align: middle; text-align: center;
        }
        table.tbl td.sub { width: 20%; font-weight: 700; }
        table.tbl td.stagehead { background: #f2f2f2; font-weight: 700; }
        table.tbl td.stagehead .pilar { font-weight: 400; font-style: italic; font-size: 9px; }
        .cell-line { margin: 0 0 2px; }
        .b { font-weight: 700; }
        .check { padding-left: 14px; text-indent: -14px; margin: 0 0 2px; }
        .quote { font-style: italic; margin: 0 0 2px; }
        /* DPL dua kolom */
        table.dpl { width: 100%; border-collapse: collapse; margin-top: 3px; }
        table.dpl td { border: none !important; padding: 0 4px 2px 0; vertical-align: top; font-weight: 700; }
        /* Tanda tangan */
        table.ttd { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.ttd td { padding: 1px 4px; vertical-align: top; }
        table.ttd td.kiri { width: 45%; text-align: left; }
        table.ttd td.kanan { width: 55%; text-align: center; }
        .ttd-date { text-align: right; margin-top: 16px; }
        .ttd-spacer { height: 52px; }
        /* Lampiran */
        .lampiran { page-break-before: always; }
        .lampiran-heading { font-weight: 700; font-size: 11px; margin-bottom: 8px; }
        .lampiran .cell-line { margin-bottom: 3px; }
        .opsi { padding-left: 26px; }
        .nomor { padding-left: 8px; }
        table.rubrik { width: 100%; border-collapse: collapse; margin: 6px 0 8px; }
        table.rubrik td, table.rubrik th { border: 1px solid #000; padding: 4px 5px; vertical-align: top; font-size: 9px; text-align: left; }
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
