<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak QR — {{ $ruangan->nama }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Segoe UI", system-ui, -apple-system, sans-serif; color: #1e293b; margin: 0; }

        .page {
            position: relative;
            width: 210mm; min-height: 297mm;
            padding: 16mm 18mm;
            background: #fff;
            display: flex;
            flex-direction: column;
        }

        .kop { display: flex; align-items: center; gap: 12px; border-bottom: 2px solid #1e293b; padding-bottom: 10px; margin-bottom: 6mm; }
        .kop .logo { width: 52px; height: 52px; object-fit: contain; flex: 0 0 auto; }
        .kop .ident { flex: 1; text-align: center; }
        .kop .ident .nm { font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin: 0; }
        .kop .ident p, .kop .ident h1, .kop .ident h2, .kop .ident h3, .kop .ident h4, .kop .ident h5, .kop .ident h6 { margin: 1px 0; font-size: 10.5px; line-height: 1.2; }

        .hero { text-align: center; margin: 4mm 0 5mm; }
        .hero .eyebrow { font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #7ba088; margin: 0 0 4px; }
        .hero h1 { font-size: 30px; font-weight: 800; margin: 0; letter-spacing: .3px; color: #0f172a; }
        .hero .sub { font-size: 13px; color: #64748b; margin-top: 4px; }

        .main-row { display: flex; align-items: center; gap: 14mm; margin: 3mm 0 6mm; }
        .qr-wrap { flex: 0 0 auto; }
        .qr-box { border: 3px solid #0f172a; border-radius: 20px; padding: 14px; background: #fff; display: inline-block; }
        .qr-box canvas { display: block; }
        .badges { flex: 1; display: flex; flex-direction: column; gap: 8px; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 999px; font-size: 12px; font-weight: 700; width: fit-content; }
        .badge.ruangan { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .badge.periode { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }
        .badge.jenis { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }

        .cols { display: flex; gap: 10mm; margin-bottom: 5mm; }
        .col { flex: 1; }
        .col-title { display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 800; text-transform: uppercase; letter-spacing: .8px; margin: 0 0 3mm; padding-bottom: 2mm; border-bottom: 2px solid; }
        .col.siswa .col-title { color: #047857; border-color: #a7f3d0; }
        .col.guru .col-title { color: #4338ca; border-color: #c7d2fe; }
        .col-title .ic { width: 22px; height: 22px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; color: #fff; flex: 0 0 auto; }
        .col.siswa .col-title .ic { background: #059669; }
        .col.guru .col-title .ic { background: #4f46e5; }

        .step { display: flex; gap: 9px; align-items: flex-start; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 8px 10px; margin-bottom: 6px; }
        .step .num { flex: 0 0 auto; width: 22px; height: 22px; border-radius: 50%; color: #fff; font-weight: 800; font-size: 11.5px; display: flex; align-items: center; justify-content: center; }
        .col.siswa .step .num { background: #059669; }
        .col.guru .step .num { background: #4f46e5; }
        .step .txt { font-size: 11.5px; line-height: 1.4; color: #334155; padding-top: 2px; }
        .step .txt b { color: #0f172a; }

        .catatan { background: #fffbeb; border: 1px solid #fde68a; border-radius: 14px; padding: 10px 14px; font-size: 11.5px; color: #78350f; line-height: 1.5; margin-bottom: 4mm; }
        .catatan b { color: #92400e; }

        .footer-note { margin-top: auto; padding-top: 6mm; text-align: center; font-size: 10.5px; color: #94a3b8; border-top: 1px dashed #cbd5e1; }
        .footer-note b { color: #64748b; }

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
            <b>Cetak QR Ruangan</b>
            <span class="muted">&nbsp;{{ $ruangan->nama }} — {{ $paket->nama }}</span>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('ujian.paket.ruangan.show', [$paket, $ruangan]) }}">&larr; Kembali</a>
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

        <div class="hero">
            <p class="eyebrow">{{ $paket->jenisLabel() }} &bull; {{ $paket->nama }}</p>
            <h1>{{ $ruangan->nama }}</h1>
            <p class="sub">Scan QR di bawah ini untuk absen (siswa) atau masuk pemantauan (guru/pengawas)</p>
        </div>

        <div class="main-row">
            <div class="qr-wrap">
                <div class="qr-box">
                    <canvas id="qrCanvas"></canvas>
                </div>
            </div>
            <div class="badges">
                <span class="badge ruangan">🚪 {{ $ruangan->nama }}{{ $ruangan->kapasitas ? ' · ' . $ruangan->kapasitas . ' kursi' : '' }}</span>
                @if($paket->tanggal_mulai)
                <span class="badge periode">📅 {{ $paket->tanggal_mulai->translatedFormat('d M Y') }}@if($paket->tanggal_selesai) – {{ $paket->tanggal_selesai->translatedFormat('d M Y') }}@endif</span>
                @endif
                <span class="badge jenis">📋 {{ $paket->jenisLabel() }}</span>
            </div>
        </div>

        <div class="cols">
            <div class="col siswa">
                <p class="col-title"><span class="ic">🧑‍🎓</span> Untuk Siswa</p>
                <div class="step"><span class="num">1</span><span class="txt">Datang ke <b>{{ $ruangan->nama }}</b> sesuai jadwal ujianmu.</span></div>
                <div class="step"><span class="num">2</span><span class="txt">Buka aplikasi/website sekolah di HP, lalu <b>masuk (login)</b> dengan akun kamu.</span></div>
                <div class="step"><span class="num">3</span><span class="txt">Arahkan kamera HP ke kode QR ini sampai terbaca.</span></div>
                <div class="step"><span class="num">4</span><span class="txt">Kehadiranmu <b>otomatis tercatat</b> begitu QR berhasil dipindai.</span></div>
            </div>
            <div class="col guru">
                <p class="col-title"><span class="ic">🧑‍🏫</span> Untuk Guru / Pengawas</p>
                <div class="step"><span class="num">1</span><span class="txt">Datang ke ruangan ini saat ujian sedang berlangsung.</span></div>
                <div class="step"><span class="num">2</span><span class="txt">Masuk (login) dengan akun guru Anda.</span></div>
                <div class="step"><span class="num">3</span><span class="txt">Scan kode QR yang sama — Anda akan <b>langsung diarahkan</b> ke halaman pemantauan ruangan.</span></div>
                <div class="step"><span class="num">4</span><span class="txt">Guru <b>mana pun boleh mengawasi</b>, asal ruangan ini ada ujian dijadwalkan pada hari itu.</span></div>
            </div>
        </div>

        <div class="catatan">
            <b>Catatan:</b> QR ini berlaku tetap sepanjang periode <b>{{ $paket->nama }}</b> — tidak perlu dicetak ulang tiap hari ujian, cukup tempel permanen di ruangan ini.
        </div>

        <div class="footer-note">
            <b>{{ $ruangan->nama }}</b> &bull; {{ $paket->nama }} &nbsp;&bull;&nbsp; Dicetak {{ now()->isoFormat('D MMMM Y, HH:mm') }}
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        new QRious({ element: document.getElementById('qrCanvas'), value: @js($urlScan), size: 260, level: 'H', background: '#fff', foreground: '#0f172a' });
    </script>
</body>
</html>
