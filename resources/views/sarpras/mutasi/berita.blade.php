<!DOCTYPE html>
{{-- Berita Acara Mutasi Aset (PDF dompdf) --}}
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { text-align: center; font-size: 16px; margin-bottom: 2px; }
        .sub { text-align: center; font-size: 11px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        td, th { padding: 6px 8px; border: 1px solid #999; text-align: left; }
        .ttd { margin-top: 50px; width: 100%; }
        .ttd td { border: none; text-align: center; }
    </style>
</head>
<body>
    <h1>BERITA ACARA MUTASI ASET</h1>
    <div class="sub">Tanggal: {{ $mutasi->tgl_mutasi->format('d/m/Y') }}</div>

    <p>Telah dilakukan perpindahan/mutasi aset sekolah dengan rincian:</p>
    <table>
        <tr><th width="30%">Kode Aset</th><td>{{ $mutasi->aset?->kode }}</td></tr>
        <tr><th>Nama Aset</th><td>{{ $mutasi->aset?->nama }}</td></tr>
        <tr><th>Ruangan Asal</th><td>{{ $mutasi->ruanganAsal?->kode }} {{ $mutasi->ruanganAsal?->nama }}</td></tr>
        <tr><th>Ruangan Tujuan</th><td>{{ $mutasi->ruanganTujuan?->kode }} {{ $mutasi->ruanganTujuan?->nama }}</td></tr>
        <tr><th>Alasan</th><td>{{ $mutasi->alasan ?? '-' }}</td></tr>
    </table>

    <table class="ttd">
        <tr>
            <td>Pelaksana,<br><br><br><br><b>{{ $mutasi->pelaksana?->name ?? '(.............)' }}</b></td>
            <td>Mengetahui,<br><br><br><br><b>Waka Sarpras</b></td>
        </tr>
    </table>
</body>
</html>
