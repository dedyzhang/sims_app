<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cetak QR Absensi</title>
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

        .hero { text-align: center; margin: 4mm 0 6mm; }
        .hero .eyebrow { font-size: 12px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: #7ba088; margin: 0 0 4px; }
        .hero h1 { font-size: 30px; font-weight: 800; margin: 0; letter-spacing: .3px; color: #0f172a; }
        .hero .sub { font-size: 13px; color: #64748b; margin-top: 4px; }

        .qr-wrap { display: flex; justify-content: center; margin: 4mm 0; }
        .qr-box { border: 3px solid #0f172a; border-radius: 20px; padding: 14px; background: #fff; }
        .qr-box canvas { display: block; }

        .badge-row { display: flex; justify-content: center; margin: 4mm 0 6mm; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 16px; border-radius: 999px; font-size: 12px; font-weight: 700; }
        .badge.harian { background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; }
        .badge.tetap { background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; }

        .steps-title { text-align: center; font-size: 14px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: #0f172a; margin: 0 0 4mm; }
        .steps { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px 16px; }
        .step { display: flex; gap: 10px; align-items: flex-start; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 14px; padding: 10px 12px; }
        .step .num { flex: 0 0 auto; width: 26px; height: 26px; border-radius: 50%; background: #7ba088; color: #fff; font-weight: 800; font-size: 13px; display: flex; align-items: center; justify-content: center; }
        .step .txt { font-size: 12px; line-height: 1.4; color: #334155; padding-top: 2px; }
        .step .txt b { color: #0f172a; }

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
            <b>Cetak QR Absensi</b>
            <span class="muted">&nbsp;{{ $mode === 'tetap' ? 'Mode: Satu QR Tetap' : 'Mode: Ganti Setiap Hari' }}</span>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('qr.absensi') }}">&larr; Kembali</a>
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
            <p class="eyebrow">Absensi Digital</p>
            <h1>Scan QR Untuk Absen</h1>
            <p class="sub">Tempelkan halaman ini di pintu masuk / titik absen sekolah</p>
        </div>

        <div class="qr-wrap">
            <div class="qr-box">
                <canvas id="qrCanvas"></canvas>
            </div>
        </div>

        <div class="badge-row">
            @if($mode === 'tetap')
            <span class="badge tetap">📌 QR Tetap — Berlaku Permanen</span>
            @else
            <span class="badge harian">🔄 Berlaku Hanya {{ \Carbon\Carbon::parse($tanggal)->isoFormat('dddd, D MMMM Y') }}</span>
            @endif
        </div>

        <p class="steps-title">Cara Melakukan Absen</p>
        <div class="steps">
            <div class="step"><span class="num">1</span><span class="txt">Buka aplikasi/website sekolah di HP, lalu <b>masuk (login)</b> dengan akun kamu.</span></div>
            <div class="step"><span class="num">2</span><span class="txt">Pilih menu <b>"Absen QR"</b> pada aplikasi.</span></div>
            <div class="step"><span class="num">3</span><span class="txt">Izinkan aplikasi mengakses <b>lokasi & kamera</b> HP kamu saat diminta.</span></div>
            <div class="step"><span class="num">4</span><span class="txt">Tekan tombol <b>Scan</b>, lalu arahkan kamera HP ke kode QR ini sampai terbaca.</span></div>
            <div class="step"><span class="num">5</span><span class="txt">Tunggu sebentar hingga muncul keterangan <b>"Absen berhasil"</b> di layar HP.</span></div>
            <div class="step"><span class="num">6</span><span class="txt">Absen hanya berhasil bila kamu berada <b>di lokasi sekolah</b> saat memindai.</span></div>
        </div>

        <div class="footer-note">
            @if($mode === 'tetap')
            <b>QR ini berlaku tetap</b> — tidak perlu dicetak ulang tiap hari, kecuali admin membuatnya ulang.
            @else
            <b>QR ini otomatis berganti setiap hari</b> — cetak/tampilkan halaman ini kembali setiap pagi sebelum jam masuk.
            @endif
            &nbsp;&bull;&nbsp; Dicetak {{ now()->isoFormat('D MMMM Y, HH:mm') }}
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/qrious@4.0.2/dist/qrious.min.js"></script>
    <script>
        new QRious({ element: document.getElementById('qrCanvas'), value: @js($token), size: 300, level: 'H', background:'#fff', foreground:'#0f172a' });
    </script>
</body>
</html>
