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
        .subjudul { text-align: center; font-style: italic; margin-top: 2px; }
        /* Identitas */
        table.identitas { margin-top: 10px; border-collapse: collapse; }
        table.identitas td { padding: 1px 0; vertical-align: top; }
        table.identitas td.lbl { width: 140px; }
        table.identitas td.sep { width: 12px; }
        /* Bagian soal */
        .bagian { font-weight: 700; font-size: 11px; margin-top: 14px; padding-bottom: 3px; border-bottom: 1px solid #1f3864; color: #1f3864; }
        .subbagian { font-weight: 700; margin-top: 10px; }
        .intro { font-style: italic; margin-top: 5px; }
        ol.petunjuk { margin: 5px 0 0; padding-left: 18px; }
        ol.petunjuk li { margin-bottom: 2px; }
        .soal { margin-top: 8px; padding-left: 16px; text-indent: -16px; }
        .soal-gambar { margin: 6px 0 6px 16px; text-align: center; text-indent: 0; }
        .soal-gambar img { max-width: 70%; max-height: 220px; border: 1px solid #999; }
        .soal-gambar-caption { font-size: 8px; color: #555; margin-top: 3px; font-style: italic; }
        .opsi { padding-left: 30px; text-indent: -14px; }
        .garis-jawab { margin: 3px 0 2px 16px; color: #9ca3af; }
        /* Kunci jawaban: halaman terpisah, sama seperti dokumen acuan */
        .kunci { page-break-before: always; }
        table.kunci-pg { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.kunci-pg td { border: 1px solid #000; padding: 4px 6px; font-weight: 700; width: 100%; }
        .esai-head { font-weight: 700; margin-top: 8px; }
        ul.poin { margin: 3px 0 0; padding-left: 18px; }
        ul.poin li { margin-bottom: 2px; }
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
    @include('ai.partials.quiz-document', ['doc' => $doc])
@else
    @include('ai.partials.learning-plain', ['content' => $content])
@endif
</body>
</html>
