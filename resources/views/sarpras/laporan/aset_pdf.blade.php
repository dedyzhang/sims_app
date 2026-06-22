<!DOCTYPE html>
{{-- Laporan Data Aset (PDF dompdf, landscape) --}}
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; }
        h1 { font-size: 15px; text-align: center; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { border: 1px solid #999; padding: 4px 6px; }
        th { background: #eee; text-align: left; }
        .total { text-align: right; font-weight: bold; }
    </style>
</head>
<body>
    <h1>LAPORAN DATA ASET SARANA & PRASARANA</h1>
    <table>
        <thead>
            <tr>
                <th>Kode</th><th>Nama</th><th>Kategori</th><th>Ruangan</th>
                <th>Kondisi</th><th>Status</th><th>Tgl Perolehan</th><th>Nilai (Rp)</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($aset as $a)
            <tr>
                <td>{{ $a->kode }}</td>
                <td>{{ $a->nama }}</td>
                <td>{{ $a->kategori?->nama }}</td>
                <td>{{ $a->ruangan?->kode }}</td>
                <td>{{ str_replace('_',' ',$a->kondisi) }}</td>
                <td>{{ $a->status }}</td>
                <td>{{ optional($a->tgl_perolehan)->format('d/m/Y') }}</td>
                <td style="text-align:right">{{ number_format($a->nilai_perolehan, 0, ',', '.') }}</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot>
            <tr><td colspan="7" class="total">TOTAL NILAI ASET</td><td style="text-align:right">{{ $totalRp }}</td></tr>
        </tfoot>
    </table>
    <p style="margin-top:10px; font-size:9px; color:#666">Dicetak: {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
