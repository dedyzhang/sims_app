<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 8mm 9mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#000; font-size:8px; line-height:1.24; }
        .kop { text-align: center; border-bottom: 2px double #000; padding-bottom: 5px; margin-bottom: 7px; }
        .kop .nama { font-weight: 700; font-size: 11px; line-height: 1.2; text-transform: uppercase; color: #000; }
        .kop .sub { font-weight: 700; font-size: 9px; margin-top: 1px; color: #000; }
        .kop .alamat { font-size: 7.5px; line-height: 1.25; margin-top: 1px; color: #333; }

        .blueprint-header { text-align: center; margin-bottom: 8px; }
        .blueprint-header .title { font-weight: 700; font-size: 11px; text-transform: uppercase; color: #000; }
        .blueprint-header .subtitle { font-weight: 700; font-size: 9.5px; margin-top: 1px; text-transform: uppercase; color: #000; }
        .blueprint-header .subsubtitle { font-weight: 700; font-size: 9px; margin-top: 1px; text-transform: uppercase; color: #000; }

        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        tr { page-break-inside: avoid; }
        thead { display: table-header-group; }

        .identity-clean { margin: 6px 0 8px 0; border: none; font-size: 7.8px; }
        .identity-clean td { border: none; padding: 1.5px 4px; vertical-align: middle; color: #000; }
        .identity-clean .id-label { font-weight: 700; width: 130px; }
        .identity-clean .id-val { font-weight: normal; }

        .competencies-box { margin-top: 5px; background: #f0f7fa; border: 1px solid #000; padding: 4px 6px; }
        .competencies-title { font-weight: 700; font-size: 7.8px; color: #000; margin-bottom: 2px; }
        .competencies-list { margin: 0; padding-left: 14px; font-size: 7.4px; line-height: 1.2; color: #000; }
        .competencies-list li { margin-bottom: 1px; }

        .blueprint { margin-top: 6px; border: 1px solid #000; font-size: 7.4px; }
        .blueprint th { background: #b8d8e8; color: #000; border: 1px solid #000; padding: 4px 3px; text-align: center; font-weight: 700; font-size: 7.4px; line-height: 1.15; }
        .blueprint td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; font-size: 7.2px; line-height: 1.18; word-wrap: break-word; color: #000; }

        .c-no { text-align: center; }
        .c-class { text-align: center; }
        .c-level { text-align: center; }
        .c-shape { text-align: center; }
        .c-qno { text-align: center; }

        .legend { margin-top: 5px; font-size: 7.4px; color: #000; }
        .note { margin-top: 3px; font-size: 7.4px; color: #000; }
        .sign { margin-top: 12px; width: 100%; font-size: 7.8px; color: #000; }
        .sign td { text-align: center; padding-top: 2px; }

        .answer-page { page-break-before: always; }
        .answer-top { background: #b8d8e8; color: #000; text-align: center; font-weight: 700; font-size: 10px; line-height: 1.2; padding: 6px 8px; margin-bottom: 8px; border: 1px solid #000; }
        .section-title { color: #000; font-weight: 700; font-size: 8px; padding: 4px 6px; margin: 6px 0 3px; background: #d8ecf8; border: 1px solid #000; page-break-after: avoid; }
        .answer-table th { background: #b8d8e8; border: 1px solid #000; padding: 3px 4px; font-weight: 700; font-size: 7.4px; line-height: 1.15; color: #000; }
        .answer-table td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; font-size: 7.2px; line-height: 1.18; word-wrap: break-word; color: #000; }
        .answer-table .num { text-align: center; font-weight: 700; }
        .answer-table .key { text-align: center; font-weight: 700; }

        .recap { margin-top: 6px; }
        .recap th { background: #b8d8e8; border: 1px solid #000; padding: 4px; font-size: 7.4px; text-align: center; font-weight: 700; color: #000; }
        .recap td { border: 1px solid #000; padding: 3px 4px; font-size: 7.2px; word-wrap: break-word; color: #000; }

        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .fallback { white-space: pre-wrap; font-size: 9px; word-wrap: break-word; }
    </style>
</head>
<body>
@include('ai.partials.blueprint-document', ['doc' => $doc, 'content' => $content])
</body>
</html>
