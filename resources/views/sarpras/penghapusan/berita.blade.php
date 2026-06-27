<!DOCTYPE html>
{{-- Berita Acara Penghapusan Aset (PDF dompdf) --}}
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
    <h1>BERITA ACARA PENGHAPUSAN ASET</h1>
    <div class="sub">Nomor: {{ $penghapusan->kode }}</div>

    <p>Pada hari ini telah dilakukan penghapusan aset sekolah dengan rincian sebagai berikut:</p>
    <table>
        <tr><th width="30%">Kode Aset</th><td>{{ $penghapusan->aset?->kode }}</td></tr>
        <tr><th>Nama Aset</th><td>{{ $penghapusan->aset?->nama }}</td></tr>
        <tr><th>Metode Penghapusan</th><td>{{ ucfirst($penghapusan->metode) }}</td></tr>
        <tr><th>Alasan</th><td>{{ $penghapusan->alasan }}</td></tr>
        <tr><th>Status</th><td>{{ ucfirst($penghapusan->status) }}</td></tr>
        <tr><th>Disetujui Pada</th><td>{{ optional($penghapusan->disetujui_pada)->format('d/m/Y H:i') ?? '-' }}</td></tr>
    </table>

    <table class="ttd">
        <tr>
            <td>Diajukan oleh,<br><br><br><br><b>{{ $penghapusan->pengaju?->name }}</b></td>
            <td>Disetujui oleh,<br><br><br><br><b>{{ $penghapusan->penyetuju?->name ?? '(.............)' }}</b></td>
        </tr>
    </table>
</body>
</html>
