<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Notulen Rapat &mdash; {{ $rapat->judul }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Georgia, serif; color: #000; margin: 0; }

        .page {
            position: relative;
            width: 210mm; min-height: 297mm;
            padding: 14mm 16mm;
            background: #fff;
            page-break-after: always;
        }
        .page:last-child { page-break-after: auto; }

        .kop { display: flex; align-items: center; gap: 14px; border-bottom: 5px double #000; padding-bottom: 8px; }
        .kop .logo { width: 74px; height: 74px; object-fit: contain; flex: 0 0 auto; }
        .kop .ident { flex: 1; text-align: center; }
        .kop .ident .nm { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin: 0; }
        .kop .ident p, .kop .ident h1, .kop .ident h2, .kop .ident h3, .kop .ident h4, .kop .ident h5, .kop .ident h6 { margin: 2px 0; font-size: 12.5px; line-height: 1.25; }   /* rapikan margin dari editor teks kop */
        .kop .ident ul, .kop .ident ol { margin: 2px 0; padding-left: 22px; text-align: left; display: inline-block; }

        .judul { text-align: center; font-weight: 700; font-size: 16px; letter-spacing: .5px; margin: 14px 0 4px; text-transform: uppercase; }
        .subjudul { text-align: center; font-size: 13px; margin-bottom: 16px; }

        .ident-grid { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 14px; }
        .ident-grid td { padding: 2px 0; vertical-align: top; }
        .ident-grid .l { width: 130px; } .ident-grid .s { width: 12px; }

        .sub-h { font-weight: 700; font-size: 14px; margin: 16px 0 6px; border-bottom: 1px solid #000; padding-bottom: 3px; }
        .rich-content { font-size: 13px; line-height: 1.6; }
        .rich-content p { margin: 0 0 .6em; }
        .rich-content ul, .rich-content ol { margin: 0 0 .6em 1.4em; }
        .rich-content img { max-width: 100%; }
        .empty-note { font-size: 12.5px; font-style: italic; color: #555; }

        {{-- Daftar hadir: kolom horizontal (bukan 1 nama per baris) supaya hemat tempat &
             tidak gampang terpotong ganjil antar halaman saat dicetak. --}}
        .hadir-box { border: 1px solid #3f3f3f; padding: 8px 14px; margin-top: 4px; break-inside: avoid; }
        .hadir-list { columns: 3 180px; column-gap: 20px; font-size: 12px; }
        .hadir-list .hi { display: flex; gap: 6px; padding: 3px 0; border-bottom: 1px dotted #bbb; break-inside: avoid; }
        .hadir-list .hi .no { color: #666; width: 16px; flex-shrink: 0; text-align: right; }

        .ttd { display: flex; justify-content: flex-end; margin-top: 40px; font-size: 13px; }
        .ttd .b { text-align: center; width: 240px; }
        .ttd .b .nm { font-weight: 700; margin-top: 60px; margin-bottom: 0; text-decoration: underline; }

        .doku-title { text-align: center; font-weight: 700; font-size: 15px; letter-spacing: .5px; margin: 0 0 16px; text-transform: uppercase; }
        .doku-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .doku-grid .item { border: 1px solid #999; padding: 6px; }
        .doku-grid img { width: 100%; height: 210px; object-fit: cover; display: block; }
        .doku-grid .cap { font-size: 11px; text-align: center; margin-top: 4px; color: #555; }

        .toolbar { position: sticky; top: 0; z-index: 50; background: #0f172a; color: #fff; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; font-family: system-ui, sans-serif; }
        .toolbar .muted { color: #94a3b8; font-size: 12px; }
        .toolbar a, .toolbar button { font-family: system-ui, sans-serif; text-decoration: none; border: 0; border-radius: 8px; padding: 8px 14px; font-size: 13px; font-weight: 600; cursor: pointer; }
        .toolbar a { background: transparent; color: #fff; border: 1px solid rgba(255,255,255,.3); }
        .toolbar button { background: #fff; color: #0f172a; }

        @page { size: A4; margin: 0; }
        @media screen {
            body { background: #e9edf3; }
            .page { margin: 16px auto; box-shadow: 0 6px 24px rgba(0,0,0,.16); }
        }
        @media print {
            .toolbar { display: none !important; }
            .page { margin: 0; box-shadow: none; }
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <b>Cetak Notulen Rapat</b>
            <span class="muted">&nbsp;{{ $rapat->judul }} &middot; {{ $rapat->tanggal->isoFormat('D MMM Y') }}</span>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('rapat.show', $rapat) }}">&larr; Kembali</a>
            <button onclick="window.print()">🖨 Cetak / Simpan PDF</button>
        </div>
    </div>

    <div class="page">
        <div class="kop">
            @if($kopLogoKiri)<img src="{{ $kopLogoKiri }}" class="logo" alt="Logo">@endif
            <div class="ident">
                @if($kopTeks)
                    {!! \App\Support\RichText::clean($kopTeks) !!}
                @else
                    <p class="nm">{{ $namaSekolah }}</p>
                    <p>{{ $alamatSekolah }}</p>
                @endif
            </div>
            @if($kopLogoKanan)<img src="{{ $kopLogoKanan }}" class="logo" alt="Logo">@endif
        </div>

        <div class="judul">Berita Acara / Notulen Rapat</div>
        <div class="subjudul">{{ $rapat->judul }}</div>

        <table class="ident-grid">
            <tr><td class="l">Hari / Tanggal</td><td class="s">:</td><td>{{ $rapat->tanggal->isoFormat('dddd, D MMMM Y') }}</td></tr>
            <tr><td class="l">Jumlah Guru Hadir</td><td class="s">:</td><td>{{ $rapat->guruHadir->count() }} orang</td></tr>
        </table>

        <div class="sub-h">Pokok Pembahasan</div>
        <div class="rich-content">
            @if($rapat->pokok_permasalahan)
                {!! \App\Support\RichText::clean($rapat->pokok_permasalahan) !!}
            @else
                <p class="empty-note">Tidak ada catatan.</p>
            @endif
        </div>

        <div class="sub-h">Hasil Rapat / Keputusan</div>
        <div class="rich-content">
            @if($rapat->hasil_rapat)
                {!! \App\Support\RichText::clean($rapat->hasil_rapat) !!}
            @else
                <p class="empty-note">Tidak ada catatan.</p>
            @endif
        </div>

        <div class="sub-h">Daftar Hadir ({{ $rapat->guruHadir->count() }} orang)</div>
        @if($rapat->guruHadir->isEmpty())
        <p class="empty-note">Belum ada absensi kehadiran tercatat.</p>
        @else
        <div class="hadir-box">
            <div class="hadir-list">
                @foreach($rapat->guruHadir as $i => $g)
                <div class="hi"><span class="no">{{ $i + 1 }}.</span><span>{{ $g->nama }}</span></div>
                @endforeach
            </div>
        </div>
        @endif

        <div class="ttd">
            <div class="b">
                <div>Mengetahui,</div>
                <div>Kepala Sekolah</div>
                <p class="nm">{{ $kepsekNama ?: '.....................' }}</p>
                @if($kepsekNip)<div>NIP. {{ $kepsekNip }}</div>@endif
            </div>
        </div>
    </div>

    @if($rapat->dokumentasi->isNotEmpty())
    <div class="page">
        <div class="doku-title">Dokumentasi Rapat</div>
        <div class="doku-grid">
            @foreach($rapat->dokumentasi as $d)
                @if($d->isImage())
                <div class="item">
                    <img src="{{ $d->url }}" alt="Dokumentasi">
                    <div class="cap">{{ $d->original_name }}</div>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif
</body>
</html>
