<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 8mm 9mm; }
        body { font-family: DejaVu Sans, Arial, sans-serif; color:#333; font-size:8.4px; line-height:1.22; }
        .topbar { background:#5b2d91; color:#fff; text-align:center; font-weight:700; font-size:14px; line-height:1.2; padding:10px 10px; }
        .subject { display:block; margin-top:3px; }
        table { border-collapse:collapse; width:100%; table-layout:fixed; }
        tr { page-break-inside: avoid; }
        .identity { margin-top:7px; background:#fff8e8; border:1px solid #d89a17; }
        .identity td { border:1px solid #d89a17; padding:5px 6px; vertical-align:middle; font-size:8.6px; word-wrap:break-word; }
        .identity .label { width:18%; font-weight:700; }
        .identity .value { width:32%; }
        .blueprint { margin-top:7px; border:1px solid #d89a17; table-layout:fixed; }
        .blueprint th { background:#5b2d91; color:#fff; border:1px solid #d89a17; padding:6px 4px; text-align:center; font-weight:700; font-size:7.8px; line-height:1.18; }
        .blueprint td { border:1px solid #d89a17; padding:4px 4px; vertical-align:top; font-size:7.4px; line-height:1.18; word-wrap:break-word; }
        .blueprint tr:nth-child(even) td { background:#f3eef8; }
        .c-no { width:4%; text-align:center; }
        .c-element { width:14%; font-weight:700; }
        .c-material { width:20%; }
        .c-indicator { width:31%; }
        .c-level { width:9%; text-align:center; font-weight:700; color:#0066cc; }
        .c-shape { width:13%; text-align:center; font-weight:700; color:#00136e; }
        .c-qno { width:9%; text-align:center; }
        .legend { margin-top:6px; font-size:7.8px; }
        .note { margin-top:4px; font-size:7.8px; }
        .sign { margin-top:12px; width:100%; font-size:8.8px; }
        .sign td { width:50%; text-align:center; padding-top:3px; }
        .answer-page { page-break-before:always; }
        .answer-top { background:#2e7d32; color:#fff; text-align:center; font-weight:700; font-size:13px; line-height:1.25; padding:10px 10px; margin-bottom:12px; }
        .section-title { color:#fff; font-weight:700; font-size:9.4px; padding:5px 8px; margin:7px 0 4px; page-break-after: avoid; }
        .section-blue { background:#1565c0; }
        .section-green { background:#00574b; }
        .answer-table { table-layout:fixed; }
        .answer-table th { background:#b7d8f1; border:1px solid #1683e8; padding:4px 5px; text-align:center; font-weight:700; font-size:8px; line-height:1.18; }
        .answer-table td { border:1px solid #1683e8; padding:4px 5px; vertical-align:top; font-size:7.8px; line-height:1.2; word-wrap:break-word; }
        .answer-table tr:nth-child(even) td { background:#e3f2fd; }
        .answer-table .num { width:6%; text-align:center; font-weight:700; }
        .answer-table .key { width:12%; text-align:center; font-weight:700; color:#0b66d8; }
        .answer-table .wide { width:auto; }
        .recap { margin-top:8px; }
        .recap th { background:#e3f2fd; border:1px solid #1683e8; padding:4px; font-size:8px; }
        .recap td { border:1px solid #1683e8; padding:4px; font-size:7.8px; word-wrap:break-word; }
        .fallback { white-space:pre-wrap; font-size:10px; word-wrap:break-word; }
    </style>
</head>
<body>
@include('ai.partials.blueprint-document', ['doc' => $doc, 'content' => $content])
</body>
</html>
