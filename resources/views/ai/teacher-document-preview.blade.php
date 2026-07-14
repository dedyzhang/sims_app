{{--
    Pratinjau layar dokumen perangkat ajar. Memakai markup yang SAMA dengan export PDF
    (partial learning-document) sehingga yang dilihat guru = yang tercetak. Gaya "kertas"
    dipertahankan terang di mode gelap, persis seperti dokumen aslinya.
--}}
<div class="rpm-doc">
    <style>
        .rpm-doc {
            background: #fff;
            color: #000;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 12px;
            line-height: 1.45;
            padding: 22px 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgb(0 0 0 / .12);
        }
        .rpm-doc .kop { text-align: center; border-bottom: 3px solid #000; padding-bottom: 6px; }
        .rpm-doc .kop .nama { font-weight: 700; font-size: 15px; line-height: 1.25; }
        .rpm-doc .kop .sub { font-weight: 700; font-size: 12px; }
        .rpm-doc .kop .alamat { font-size: 10px; line-height: 1.3; }
        .rpm-doc .judul { text-align: center; font-weight: 700; font-size: 15px; margin-top: 14px; }
        .rpm-doc .subjudul { text-align: center; font-weight: 700; font-size: 13px; margin-top: 2px; }
        .rpm-doc table.identitas { margin-top: 12px; border-collapse: collapse; }
        .rpm-doc table.identitas td { padding: 1px 0; vertical-align: top; }
        .rpm-doc table.identitas td.lbl { width: 170px; }
        .rpm-doc table.identitas td.sep { width: 14px; }
        .rpm-doc table.tbl { width: 100%; border-collapse: collapse; margin-top: 14px; border: 1px solid #000; table-layout: fixed; }
        .rpm-doc table.tbl td { border: 1px solid #000; padding: 6px 8px; vertical-align: top; word-wrap: break-word; }
        .rpm-doc table.tbl td.sec { width: 17%; font-weight: 700; text-transform: uppercase; background: #f2f2f2; vertical-align: middle; text-align: center; }
        .rpm-doc table.tbl td.sub { width: 21%; font-weight: 700; }
        .rpm-doc table.tbl td.stagehead { background: #f2f2f2; font-weight: 700; }
        .rpm-doc table.tbl td.stagehead .pilar { font-weight: 400; font-style: italic; font-size: 11px; }
        .rpm-doc .cell-line { margin: 0 0 3px; }
        .rpm-doc .b { font-weight: 700; }
        .rpm-doc .check { padding-left: 16px; text-indent: -16px; margin: 0 0 3px; }
        .rpm-doc .quote { font-style: italic; margin: 0 0 3px; }
        .rpm-doc table.dpl { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .rpm-doc table.dpl td { border: none; padding: 0 6px 3px 0; vertical-align: top; font-weight: 700; }
        .rpm-doc table.ttd { width: 100%; border-collapse: collapse; margin-top: 18px; }
        .rpm-doc table.ttd td { padding: 1px 4px; vertical-align: top; }
        .rpm-doc table.ttd td.kiri { width: 45%; text-align: left; }
        .rpm-doc table.ttd td.kanan { width: 55%; text-align: center; }
        .rpm-doc .ttd-date { text-align: right; margin-top: 18px; }
        .rpm-doc .ttd-spacer { height: 48px; }
        /* Di layar lampiran tidak pindah halaman — cukup garis pemisah. */
        .rpm-doc .lampiran { margin-top: 22px; padding-top: 16px; border-top: 1px dashed #9ca3af; }
        .rpm-doc .lampiran-heading { font-weight: 700; font-size: 13px; margin-bottom: 8px; }
        .rpm-doc .opsi { padding-left: 28px; }
        .rpm-doc .nomor { padding-left: 10px; }
        .rpm-doc table.rubrik { width: 100%; border-collapse: collapse; margin: 8px 0 10px; table-layout: fixed; }
        .rpm-doc table.rubrik td { border: 1px solid #000; padding: 5px 6px; vertical-align: top; font-size: 11px; text-align: left; word-wrap: break-word; }
        .rpm-doc table.rubrik tr.head td { background: #fce4b6; font-weight: 700; }
        .rpm-doc table.rubrik tr.subhead td { background: #fce4b6; font-weight: 700; }
        /* Fallback teks polos */
        .rpm-doc .document { white-space: pre-wrap; word-wrap: break-word; }
        .rpm-doc .line { margin: 0 0 3px; }
        .rpm-doc .blank { height: 8px; }
        .rpm-doc .center { text-align: center; font-weight: 700; }
        .rpm-doc .title { text-align: center; font-weight: 700; font-size: 15px; margin: 10px 0 6px; }
        .rpm-doc .section { margin-top: 12px; padding: 4px 6px; background: #e5e7eb; border: 1px solid #6b7280; font-weight: 700; text-transform: uppercase; }
        .rpm-doc .subsection { margin-top: 8px; font-weight: 700; }
    </style>

    @if($doc['parsed'])
        @include('ai.partials.learning-document', ['doc' => $doc])
    @else
        @include('ai.partials.learning-plain', ['content' => $content])
    @endif
</div>
