<div class="quiz-doc">
    <style>
        .quiz-doc { background:#f8fafc; color:#000; font-family:Arial, Helvetica, sans-serif; font-size:11.5px; line-height:1.35; padding:14px; border-radius:12px; box-shadow:0 1px 3px rgb(0 0 0 / .12); overflow-x:auto; }
        .quiz-doc .blueprint-page { min-width:1080px; max-width:1240px; margin:0 auto; background:#fff; border:1px solid #e5e7eb; padding:24px 28px; box-shadow:0 1px 2px rgb(15 23 42 / .08); }
        .quiz-doc .kop { text-align:center; border-bottom:3px double #000; padding-bottom:8px; margin-bottom:14px; }
        .quiz-doc .kop .nama { font-weight:800; font-size:15px; line-height:1.25; color:#000; text-transform:uppercase; }
        .quiz-doc .kop .sub { font-weight:700; font-size:12px; color:#000; margin-top:2px; }
        .quiz-doc .kop .alamat { font-size:10.5px; line-height:1.3; color:#333; margin-top:2px; }

        .quiz-doc .blueprint-header { text-align:center; margin-bottom:16px; }
        .quiz-doc .blueprint-header .title { font-weight:800; font-size:15px; line-height:1.25; color:#000; text-transform:uppercase; letter-spacing:0.5px; }
        .quiz-doc .blueprint-header .subtitle { font-weight:800; font-size:13px; color:#000; margin-top:3px; text-transform:uppercase; }
        .quiz-doc .blueprint-header .subsubtitle { font-weight:800; font-size:12px; color:#000; margin-top:3px; text-transform:uppercase; }

        .quiz-doc .topbar { background:#5b2d91; color:#fff; text-align:center; font-weight:800; font-size:17px; line-height:1.25; padding:14px; border-radius:4px; }
        .quiz-doc .subject { display:block; margin-top:4px; font-size:14px; }

        .quiz-doc table { border-collapse:collapse; width:100%; table-layout:fixed; }
        .quiz-doc .identity-clean { margin:10px 0 16px 0; border:none; width:auto; font-size:11.5px; }
        .quiz-doc .identity-clean td { border:none; padding:2.5px 8px; vertical-align:middle; color:#000; }
        .quiz-doc .identity-clean .id-label { font-weight:700; width:170px; }
        .quiz-doc .identity-clean .id-val { font-weight:normal; }

        .quiz-doc .identity { margin-top:10px; background:#fff8e8; border:1px solid #d89a17; }
        .quiz-doc .identity td { border:1px solid #d89a17; padding:6px 8px; vertical-align:middle; overflow-wrap:anywhere; }
        .quiz-doc .identity .label { font-weight:700; }

        .quiz-doc .competencies-box { margin-top:10px; background:#f0f7fa; border:1px solid #000; border-radius:4px; padding:8px 12px; }
        .quiz-doc .competencies-title { font-weight:700; font-size:11.5px; color:#000; margin-bottom:4px; }
        .quiz-doc .competencies-list { margin:0; padding-left:20px; font-size:11px; line-height:1.4; color:#000; }
        .quiz-doc .competencies-list li { margin-bottom:2px; }

        .quiz-doc .blueprint { margin-top:10px; border:1px solid #000; font-size:10.5px; }
        .quiz-doc .blueprint th { background:#b8d8e8; color:#000; border:1px solid #000; padding:7px 5px; text-align:center; font-weight:700; }
        .quiz-doc .blueprint-7col th { background:#5b2d91; color:#fff; border:1px solid #d89a17; }
        .quiz-doc .blueprint td { border:1px solid #000; padding:5px 6px; vertical-align:top; color:#000; overflow-wrap:anywhere; word-break:normal; }
        .quiz-doc .c-no { text-align:center; }
        .quiz-doc .c-class { text-align:center; }
        .quiz-doc .c-level { text-align:center; font-weight:600; }
        .quiz-doc .c-shape { text-align:center; }
        .quiz-doc .c-qno { text-align:center; }

        .quiz-doc .legend, .quiz-doc .note { margin-top:8px; font-size:11px; color:#000; }
        .quiz-doc .sign { margin-top:24px; width:100%; font-size:11.5px; color:#000; }
        .quiz-doc .sign td { text-align:center; padding-top:6px; }

        .quiz-doc .answer-top { background:#b8d8e8; color:#000; text-align:center; font-weight:800; font-size:14px; line-height:1.3; padding:10px; margin:28px 0 12px; border:1px solid #000; }
        .quiz-doc .section-title { color:#000; font-weight:800; padding:6px 10px; margin:14px 0 6px; background:#d8ecf8; border:1px solid #000; }
        .quiz-doc .answer-table th { background:#b8d8e8; border:1px solid #000; padding:5px 6px; font-weight:700; color:#000; }
        .quiz-doc .answer-table td { border:1px solid #000; padding:5px 6px; vertical-align:top; overflow-wrap:anywhere; color:#000; }
        .quiz-doc .answer-table .num { text-align:center; font-weight:700; }
        .quiz-doc .answer-table .key { text-align:center; font-weight:700; }

        .quiz-doc .recap th { background:#b8d8e8; border:1px solid #000; padding:6px; font-weight:700; text-align:center; color:#000; }
        .quiz-doc .recap td { border:1px solid #000; padding:5px 6px; overflow-wrap:anywhere; color:#000; }
        .quiz-doc .text-center { text-align:center; }
        .quiz-doc .text-left { text-align:left; }
        .quiz-doc .fallback { white-space:pre-wrap; }
        @media (max-width: 640px) {
            .quiz-doc { padding:10px; font-size:11px; }
            .quiz-doc .blueprint-page { min-width:980px; padding:14px; }
        }
    </style>

    <div class="blueprint-page">
        @include('ai.partials.blueprint-document', ['doc' => $doc, 'content' => $content])
    </div>
</div>
