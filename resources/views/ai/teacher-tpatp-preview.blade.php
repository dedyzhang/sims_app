{{--
    Pratinjau layar dokumen TP dan ATP untuk Asisten Guru.
    Tampilan bernuansa kertas putih bersih yang dipertahankan tetap terang di mode gelap,
    lengkap dengan diagram alur berwarna dan tabel formal Kurikulum Merdeka.
--}}
<div class="tpatp-preview-wrapper">
    <style>
        .tpatp-preview-wrapper {
            background: #ffffff;
            color: #0f172a;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11.5px;
            line-height: 1.4;
            padding: 24px 22px;
            border-radius: 14px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            width: 100%;
            overflow-x: auto;
        }

        /* Kop Surat Resmi Sekolah dari Aplikasi SIMS */
        .tpatp-kop {
            width: 100%;
            margin-bottom: 14px;
        }
        .tpatp-kop-table {
            width: 100%;
            border-collapse: collapse;
            border: none;
            margin-bottom: 4px;
        }
        .tpatp-kop-table td {
            border: none !important;
            padding: 0 4px;
            vertical-align: middle;
        }
        .tpatp-kop-logo-cell {
            width: 75px;
            vertical-align: middle;
        }
        .tpatp-kop-logo-left {
            text-align: left;
        }
        .tpatp-kop-logo-right {
            text-align: right;
        }
        .tpatp-kop-logo {
            max-height: 65px;
            max-width: 65px;
            object-fit: contain;
            display: inline-block;
        }
        .tpatp-kop-center-cell {
            text-align: center;
            vertical-align: middle;
            padding: 0 10px;
        }
        .tpatp-kop-text {
            text-align: center;
            line-height: 1.25;
            color: #000000;
        }
        .tpatp-kop-text p,
        .tpatp-kop-text h1,
        .tpatp-kop-text h2,
        .tpatp-kop-text h3 {
            margin: 1px 0 !important;
            padding: 0 !important;
            line-height: 1.25 !important;
        }
        .tpatp-kop-line {
            font-size: 11px;
            line-height: 1.3;
        }
        .tpatp-kop-line-main {
            font-size: 14px;
            font-weight: bold;
        }
        .tpatp-kop-divider {
            border-bottom: 3px double #000000;
            margin-top: 6px;
            margin-bottom: 12px;
            width: 100%;
        }

        /* Judul Dokumen */
        .tpatp-title {
            text-align: center;
            font-weight: 800;
            font-size: 15px;
            margin: 14px 0 10px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            text-decoration: underline;
        }
        .tpatp-subheading {
            font-size: 13px;
            font-weight: 700;
            margin: 18px 0 8px;
            color: #1e293b;
        }

        /* Identitas */
        table.tpatp-identitas {
            margin: 10px 0 16px;
            border-collapse: collapse;
            font-size: 11.5px;
        }
        table.tpatp-identitas td {
            padding: 2px 0;
            vertical-align: top;
        }
        table.tpatp-identitas td.lbl {
            width: 160px;
            font-weight: 700;
        }
        table.tpatp-identitas td.sep {
            width: 14px;
            font-weight: 700;
            text-align: center;
        }
        table.tpatp-identitas td.val {
            font-weight: 600;
        }

        /* Tabel Formal TP */
        .tpatp-table-responsive {
            width: 100%;
            overflow-x: auto;
            margin-top: 8px;
        }
        table.tpatp-tbl {
            width: 100%;
            border-collapse: collapse;
            border: 1.5px solid #000000;
            table-layout: fixed;
            font-size: 11px;
        }
        table.tpatp-tbl th {
            border: 1px solid #000000;
            background: #f1f5f9;
            color: #000000;
            font-weight: 700;
            padding: 8px 5px;
            text-align: center;
            line-height: 1.25;
            font-size: 10.5px;
        }
        table.tpatp-tbl td {
            border: 1px solid #000000;
            padding: 6px 7px;
            vertical-align: top;
            line-height: 1.4;
            color: #000000;
            word-wrap: break-word;
        }
        table.tpatp-tbl td.cell-elemen {
            font-weight: 700;
            background: #f8fafc;
            vertical-align: middle;
            text-align: center;
        }
        table.tpatp-tbl td.cell-cp {
            font-size: 10.5px;
            vertical-align: top;
            text-align: justify;
        }
        table.tpatp-tbl td.cell-materi {
            font-weight: 700;
            vertical-align: middle;
            background: #ffffff;
        }
        table.tpatp-tbl td.cell-tp {
            font-size: 10.5px;
        }
        table.tpatp-tbl td.cell-code {
            font-weight: 700;
            text-align: center;
            color: #1d4ed8;
            vertical-align: middle;
        }
        table.tpatp-tbl td.cell-jp,
        table.tpatp-tbl td.cell-sem {
            text-align: center;
            vertical-align: middle;
            font-weight: 600;
        }

        /* Diagram Flowchart ATP */
        .tpatp-flowchart-wrapper {
            margin: 12px 0 18px;
            overflow-x: auto;
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 16px 14px;
        }
        .tpatp-flowchart-card {
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-width: 900px;
        }
        .tpatp-flow-row {
            position: relative;
        }
        .tpatp-flow-track {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .tpatp-flow-node {
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 8px 10px;
            border-radius: 8px;
            min-width: 125px;
            max-width: 175px;
            min-height: 60px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            font-size: 10.5px;
            line-height: 1.25;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .tpatp-flow-node:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .node-title {
            font-weight: 600;
        }
        .node-badge {
            margin-top: 4px;
            font-size: 9.5px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 4px;
        }
        .node-badge--pink {
            background: #fecdd3;
            color: #9f1239;
        }
        .node-badge--orange {
            background: #fed7aa;
            color: #9a3412;
        }
        .node-badge--green {
            background: #bbf7d0;
            color: #14532d;
        }

        /* Variasi node */
        .tpatp-node--start {
            background: #ede9fe;
            border: 1.5px solid #a78bfa;
            color: #4c1d95;
            font-weight: 700;
            border-radius: 12px;
        }
        .tpatp-node--tp-pink {
            background: #fee2e2;
            border: 1.5px solid #f87171;
            color: #881337;
        }
        .tpatp-node--trans {
            background: #ffedd5;
            border: 1.5px solid #fb923c;
            color: #9a3412;
            border-radius: 20px;
            font-weight: 600;
        }
        .tpatp-node--tp-orange {
            background: #ffedd5;
            border: 1.5px solid #fb923c;
            color: #7c2d12;
        }
        .tpatp-node--trans-orange {
            background: #fef08a;
            border: 1.5px solid #eab308;
            color: #713f12;
            border-radius: 20px;
            font-weight: 600;
        }
        .tpatp-node--tp-green {
            background: #dcfce7;
            border: 1.5px solid #4ade80;
            color: #14532d;
        }
        .tpatp-node--end-green {
            background: #86efac;
            border: 2px solid #16a34a;
            color: #052e16;
            font-weight: 700;
            border-radius: 16px;
        }
        .tpatp-flow-arrow {
            font-size: 16px;
            color: #94a3b8;
            font-weight: bold;
            user-select: none;
            flex-shrink: 0;
        }

        /* Blok Tanda Tangan */
        .tpatp-ttd-container {
            margin-top: 24px;
            display: flex;
            justify-content: flex-end;
        }
        .tpatp-ttd-box {
            width: 270px;
            text-align: center;
            font-size: 11.5px;
        }
        .tpatp-ttd-date {
            margin: 0 0 4px;
        }
        .tpatp-ttd-role {
            font-weight: 600;
            margin: 0;
        }
        .tpatp-ttd-space {
            height: 52px;
        }
        .tpatp-ttd-name {
            font-weight: 700;
            text-decoration: underline;
            margin: 0 0 2px;
        }
        .tpatp-ttd-nip {
            color: #334155;
            margin: 0;
        }

        /* Fallback teks mentah */
        .tpatp-raw-fallback {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: inherit;
            line-height: 1.5;
        }
    </style>

    @if($doc['parsed'])
        @include('ai.partials.tpatp-document', ['doc' => $doc])
    @else
        <div class="tpatp-raw-fallback">{{ $content }}</div>
    @endif
</div>
