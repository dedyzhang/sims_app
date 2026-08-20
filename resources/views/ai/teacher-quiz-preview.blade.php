{{--
    Pratinjau layar dokumen soal evaluasi. Memakai struktur yang SAMA dengan export Word
    (QuizDocument + partial quiz-document), jadi yang dilihat guru = yang tercetak.
    Gaya "kertas" tetap terang di mode gelap, persis seperti dokumen aslinya.
--}}
<div class="quiz-doc">
    <style>
        .quiz-doc {
            box-sizing: border-box;
            width: 100%;
            max-width: 100%;
            min-width: 0;
            overflow-wrap: anywhere;
            word-break: break-word;
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            padding: 22px 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgb(0 0 0 / .12);
            border: 1px solid #e5e7eb;
        }
        .quiz-doc *,
        .quiz-doc *::before,
        .quiz-doc *::after { box-sizing: border-box; }
        .quiz-doc .kop { text-align: center; border-bottom: 3px solid #000; padding-bottom: 6px; }
        .quiz-doc .kop .nama { font-weight: 700; font-size: 15px; line-height: 1.25; }
        .quiz-doc .kop .sub { font-weight: 700; font-size: 12px; }
        .quiz-doc .kop .alamat { font-size: 10px; line-height: 1.3; }
        .quiz-doc .judul { text-align: center; font-weight: 700; font-size: 15px; margin-top: 14px; }
        .quiz-doc .subjudul { text-align: center; font-style: italic; margin-top: 2px; }
        .quiz-doc table.identitas {
            width: 100%;
            max-width: 100%;
            margin-top: 12px;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .quiz-doc table.identitas td { padding: 1px 0; vertical-align: top; overflow-wrap: anywhere; }
        .quiz-doc table.identitas td.lbl { width: 38%; max-width: 150px; }
        .quiz-doc table.identitas td.sep { width: 14px; }
        .quiz-doc .bagian { font-weight: 800; font-size: 13px; margin-top: 18px; padding: 5px 8px; border-left: 4px solid #1f3864; background: #f1f5f9; color: #1f3864; }
        .quiz-doc .subbagian { font-weight: 700; margin-top: 12px; }
        .quiz-doc .intro { font-style: italic; margin-top: 6px; }
        .quiz-doc ol.petunjuk { margin: 6px 0 0; padding-left: 20px; }
        .quiz-doc ol.petunjuk li { margin-bottom: 2px; }
        .quiz-doc .soal {
            margin-top: 10px;
            padding-left: 2.15em;
            text-indent: -2.15em;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .quiz-doc .soal-gambar { margin: 8px 0 8px 2.15em; text-align: center; text-indent: 0; max-width: 100%; }
        .quiz-doc .soal-gambar img {
            display: block;
            margin: 0 auto;
            width: auto;
            height: auto;
            max-width: 100%;
            max-height: min(280px, 45vh);
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: #fff;
        }
        .quiz-doc .soal-gambar-caption { font-size: 11px; color: #6b7280; margin-top: 4px; font-style: italic; }
        .quiz-doc .opsi {
            margin-top: 3px;
            padding-left: 3.45em;
            text-indent: -1.55em;
            overflow-wrap: anywhere;
            word-break: break-word;
        }
        .quiz-doc .garis-jawab {
            margin: 8px 0 4px 2.15em;
            color: transparent;
            overflow: hidden;
            white-space: nowrap;
            border-bottom: 1px solid #9ca3af;
            min-height: 1.1em;
            max-width: calc(100% - 18px);
            text-indent: 0;
        }
        .quiz-doc .kunci { margin-top: 24px; padding-top: 8px; border-top: 1px dashed #9ca3af; }
        .quiz-doc table.kunci-pg {
            width: 100%;
            max-width: 100%;
            margin-top: 8px;
            border-collapse: collapse;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
        }
        .quiz-doc table.kunci-pg tbody,
        .quiz-doc table.kunci-pg tr {
            display: contents;
        }
        .quiz-doc table.kunci-pg td {
            border: 1px solid #000;
            padding: 5px 8px;
            font-weight: 700;
            overflow-wrap: anywhere;
            word-break: break-word;
            vertical-align: top;
        }
        .quiz-doc .esai-head { font-weight: 700; margin-top: 10px; }
        .quiz-doc ul.poin { margin: 4px 0 0; padding-left: 20px; }
        .quiz-doc ul.poin li { margin-bottom: 3px; overflow-wrap: anywhere; }
        /* Fallback teks polos */
        .quiz-doc .document { white-space: pre-wrap; word-wrap: break-word; overflow-wrap: anywhere; }
        .quiz-doc .line { margin: 0 0 3px; }
        .quiz-doc .blank { height: 8px; }
        .quiz-doc .center { text-align: center; font-weight: 700; }
        .quiz-doc .title { text-align: center; font-weight: 700; font-size: 15px; margin: 10px 0 6px; }
        .quiz-doc .section { margin-top: 12px; padding: 4px 6px; background: #e5e7eb; border: 1px solid #6b7280; font-weight: 700; text-transform: uppercase; }
        .quiz-doc .subsection { margin-top: 8px; font-weight: 700; }
        .quiz-doc .quiz-quality-actions { margin: 6px 0 2px 2.15em; }
        .quiz-doc .quiz-quality-trigger {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-height: 32px;
            padding: 4px 9px;
            border: 1px solid #047857;
            border-radius: 7px;
            background: #d1fae5;
            color: #047857;
            font: 600 11px/1.2 Arial, Helvetica, sans-serif;
            cursor: pointer;
        }
        .quiz-doc .quiz-quality-trigger:hover { background: #a7f3d0; }
        .quiz-doc .quiz-quality-icon { width: 14px; height: 14px; }

        /* HP portrait sempit */
        @media (max-width: 640px) {
            .quiz-doc {
                padding: 14px 12px;
                font-size: 11.5px;
                border-radius: 10px;
            }
            .quiz-doc .kop .nama { font-size: 13px; }
            .quiz-doc .judul { font-size: 13px; }
            .quiz-doc table.identitas td.lbl { width: 42%; max-width: none; }
            .quiz-doc .soal { padding-left: 2em; text-indent: -2em; }
            .quiz-doc .opsi { padding-left: 3em; text-indent: -1.45em; }
            .quiz-doc .soal-gambar { margin-left: 8px; }
            .quiz-doc .garis-jawab { margin-left: 8px; max-width: calc(100% - 8px); }
            .quiz-doc table.kunci-pg td { padding: 4px 6px; font-size: 11px; }
            .quiz-doc .quiz-quality-actions { margin-left: 8px; }
        }

        /* HP / WebView landscape: tinggi pendek — jangan kena jendela desktop pendek */
        @media (orientation: landscape) and (max-height: 560px) and (max-width: 900px) {
            .quiz-doc {
                padding: 12px 14px;
                font-size: 11px;
                line-height: 1.4;
            }
            .quiz-doc .kop .nama { font-size: 13px; }
            .quiz-doc .kop .sub { font-size: 11px; }
            .quiz-doc .judul { font-size: 13px; margin-top: 10px; }
            .quiz-doc .bagian { margin-top: 12px; font-size: 12px; }
            .quiz-doc .soal { margin-top: 8px; }
            .quiz-doc .soal-gambar img { max-height: min(180px, 40vh); }
            .quiz-doc table.identitas td.lbl { width: 32%; }
            .quiz-doc table.kunci-pg td { padding: 3px 6px; font-size: 11px; }
        }

        /* Sangat sempit: kunci PG satu kolom, urutan tetap 1..n */
        @media (max-width: 420px) {
            .quiz-doc table.kunci-pg {
                grid-template-columns: 1fr;
            }
        }
    </style>

    @if($doc['parsed'])
        @include('ai.partials.quiz-document', ['doc' => $doc, 'interactiveQuality' => (bool) ($interactiveQuality ?? false)])
    @else
        @include('ai.partials.learning-plain', ['content' => $content])
    @endif
</div>
