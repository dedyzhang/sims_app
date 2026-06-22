<!DOCTYPE html>
{{-- Label aset (PDF, dompdf): QR + kode + nama. Ukuran ±80x50mm. --}}
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 4px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 0; }
        .wrap { width: 100%; }
        .qr { width: 90px; height: 90px; float: left; }
        .info { margin-left: 100px; }
        .kode { font-size: 13px; font-weight: bold; }
        .nama { font-size: 10px; }
        .sek { font-size: 8px; color: #555; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="qr">{!! $qrSvg !!}</div>
        <div class="info">
            <div class="kode">{{ $aset->kode }}</div>
            <div class="nama">{{ $aset->nama }}</div>
            <div class="sek">{{ $aset->kategori?->nama }}</div>
            <div class="sek">{{ optional($aset->tgl_perolehan)->format('d/m/Y') }}</div>
        </div>
    </div>
</body>
</html>
