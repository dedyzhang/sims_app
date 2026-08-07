<div class="quiz-doc">
    <style>
        .quiz-doc { background:#f8fafc; color:#333; font-family:Arial, Helvetica, sans-serif; font-size:12px; line-height:1.35; padding:14px; border-radius:12px; box-shadow:0 1px 3px rgb(0 0 0 / .12); overflow-x:auto; }
        .quiz-doc .blueprint-page { min-width:1040px; max-width:1180px; margin:0 auto; background:#fff; border:1px solid #e5e7eb; padding:16px; box-shadow:0 1px 2px rgb(15 23 42 / .08); }
        .quiz-doc .topbar { background:#5b2d91; color:#fff; text-align:center; font-weight:800; font-size:18px; line-height:1.25; padding:16px; }
        .quiz-doc .subject { display:block; margin-top:4px; }
        .quiz-doc table { border-collapse:collapse; width:100%; table-layout:fixed; }
        .quiz-doc .identity { margin-top:10px; background:#fff8e8; border:1px solid #d89a17; }
        .quiz-doc .identity td { border:1px solid #d89a17; padding:7px 8px; vertical-align:middle; overflow-wrap:anywhere; }
        .quiz-doc .identity .label { width:19%; font-weight:700; }
        .quiz-doc .identity .value { width:31%; }
        .quiz-doc .blueprint { margin-top:9px; border:1px solid #d89a17; }
        .quiz-doc .blueprint th { background:#5b2d91; color:#fff; border:1px solid #d89a17; padding:8px 6px; text-align:center; font-weight:700; }
        .quiz-doc .blueprint td { border:1px solid #d89a17; padding:6px 5px; vertical-align:top; overflow-wrap:anywhere; word-break:normal; }
        .quiz-doc .blueprint tr:nth-child(even) td { background:#f3eef8; }
        .quiz-doc .c-no { width:4%; text-align:center; }
        .quiz-doc .c-element { width:15%; font-weight:700; }
        .quiz-doc .c-material { width:18%; }
        .quiz-doc .c-indicator { width:32%; }
        .quiz-doc .c-level { width:9%; text-align:center; font-weight:700; color:#0066cc; }
        .quiz-doc .c-shape { width:13%; text-align:center; font-weight:700; color:#00136e; }
        .quiz-doc .c-qno { width:9%; text-align:center; }
        .quiz-doc .legend, .quiz-doc .note { margin-top:8px; font-size:11px; }
        .quiz-doc .sign { margin-top:18px; width:100%; }
        .quiz-doc .sign td { width:50%; text-align:center; padding-top:6px; }
        .quiz-doc .answer-top { background:#2e7d32; color:#fff; text-align:center; font-weight:800; font-size:17px; line-height:1.3; padding:14px; margin:24px 0 14px; }
        .quiz-doc .section-title { color:#fff; font-weight:800; padding:8px 10px; margin:10px 0 5px; background:#1565c0; }
        .quiz-doc .section-green { background:#00574b; }
        .quiz-doc .answer-table th { background:#b7d8f1; border:1px solid #1683e8; padding:5px 6px; text-align:center; }
        .quiz-doc .answer-table td { border:1px solid #1683e8; padding:5px 6px; vertical-align:top; overflow-wrap:anywhere; }
        .quiz-doc .answer-table tr:nth-child(even) td { background:#e3f2fd; }
        .quiz-doc .recap th { background:#e3f2fd; border:1px solid #1683e8; padding:5px; }
        .quiz-doc .recap td { border:1px solid #1683e8; padding:5px; overflow-wrap:anywhere; }
        .quiz-doc .fallback { white-space:pre-wrap; }
        @media (max-width: 640px) {
            .quiz-doc { padding:10px; font-size:11px; }
            .quiz-doc .blueprint-page { min-width:980px; padding:12px; }
            .quiz-doc .topbar { font-size:15px; padding:12px; }
        }
    </style>

    <div class="blueprint-page">
        @include('ai.partials.blueprint-document', ['doc' => $doc, 'content' => $content])
    </div>
</div>
