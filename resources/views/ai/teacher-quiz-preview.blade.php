{{--
    Pratinjau layar dokumen soal evaluasi. Memakai struktur yang SAMA dengan export Word
    (QuizDocument + partial quiz-document), jadi yang dilihat guru = yang tercetak.
    Gaya "kertas" tetap terang di mode gelap, persis seperti dokumen aslinya.
--}}
<div class="quiz-doc">
    <style>
        .quiz-doc {
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            padding: 22px 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgb(0 0 0 / .12);
        }
        .quiz-doc .kop { text-align: center; border-bottom: 3px solid #000; padding-bottom: 6px; }
        .quiz-doc .kop .nama { font-weight: 700; font-size: 15px; line-height: 1.25; }
        .quiz-doc .kop .sub { font-weight: 700; font-size: 12px; }
        .quiz-doc .kop .alamat { font-size: 10px; line-height: 1.3; }
        .quiz-doc .judul { text-align: center; font-weight: 700; font-size: 15px; margin-top: 14px; }
        .quiz-doc .subjudul { text-align: center; font-style: italic; margin-top: 2px; }
        .quiz-doc table.identitas { margin-top: 12px; border-collapse: collapse; }
        .quiz-doc table.identitas td { padding: 1px 0; vertical-align: top; }
        .quiz-doc table.identitas td.lbl { width: 150px; }
        .quiz-doc table.identitas td.sep { width: 14px; }
        .quiz-doc .bagian { font-weight: 700; font-size: 13px; margin-top: 18px; padding-bottom: 3px; border-bottom: 1px solid #1f3864; color: #1f3864; }
        .quiz-doc .subbagian { font-weight: 700; margin-top: 12px; }
        .quiz-doc .intro { font-style: italic; margin-top: 6px; }
        .quiz-doc ol.petunjuk { margin: 6px 0 0; padding-left: 20px; }
        .quiz-doc ol.petunjuk li { margin-bottom: 2px; }
        .quiz-doc .soal { margin-top: 10px; padding-left: 18px; text-indent: -18px; }
        .quiz-doc .soal-gambar { margin: 8px 0 8px 18px; text-align: center; text-indent: 0; }
        .quiz-doc .soal-gambar img { max-width: 100%; max-height: 280px; border: 1px solid #d1d5db; border-radius: 8px; background: #fff; }
        .quiz-doc .soal-gambar-caption { font-size: 11px; color: #6b7280; margin-top: 4px; font-style: italic; }
        .quiz-doc .opsi { padding-left: 34px; text-indent: -16px; }
        .quiz-doc .garis-jawab { margin: 4px 0 2px 18px; color: #9ca3af; overflow: hidden; white-space: nowrap; }
        .quiz-doc .kunci { margin-top: 24px; padding-top: 8px; border-top: 1px dashed #9ca3af; }
        .quiz-doc table.kunci-pg { width: 100%; border-collapse: collapse; margin-top: 8px; table-layout: fixed; }
        .quiz-doc table.kunci-pg td { border: 1px solid #000; padding: 5px 8px; font-weight: 700; }
        .quiz-doc .esai-head { font-weight: 700; margin-top: 10px; }
        .quiz-doc ul.poin { margin: 4px 0 0; padding-left: 20px; }
        .quiz-doc ul.poin li { margin-bottom: 3px; }
        /* Fallback teks polos */
        .quiz-doc .document { white-space: pre-wrap; word-wrap: break-word; }
        .quiz-doc .line { margin: 0 0 3px; }
        .quiz-doc .blank { height: 8px; }
        .quiz-doc .center { text-align: center; font-weight: 700; }
        .quiz-doc .title { text-align: center; font-weight: 700; font-size: 15px; margin: 10px 0 6px; }
        .quiz-doc .section { margin-top: 12px; padding: 4px 6px; background: #e5e7eb; border: 1px solid #6b7280; font-weight: 700; text-transform: uppercase; }
        .quiz-doc .subsection { margin-top: 8px; font-weight: 700; }
    </style>

    @if($doc['parsed'])
        @include('ai.partials.quiz-document', ['doc' => $doc])
    @else
        @include('ai.partials.learning-plain', ['content' => $content])
    @endif
</div>
