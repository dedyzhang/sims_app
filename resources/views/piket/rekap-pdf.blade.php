<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Rekap Piket & Substitusi</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        h1 { text-align: center; font-size: 18px; margin-bottom: 5px; }
        p.subtitle { text-align: center; color: #666; margin-top: 0; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: center; }
        th { background-color: #f4f4f4; }
        td.nama { text-align: left; font-weight: bold; }
        .text-bold { font-weight: bold; }
    </style>
</head>
<body>
    <h1>Rekapitulasi Piket & Substitusi Kelas</h1>
    <p class="subtitle">Periode: {{ \Carbon\Carbon::createFromDate($tahun, $bulan, 1)->locale('id')->isoFormat('MMMM YYYY') }}</p>

    @if($rekap->isEmpty())
        <p style="text-align: center;">Tidak ada data pada periode ini.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th rowspan="2">Nama Guru</th>
                    <th colspan="5">Ketidakhadiran (Hari)</th>
                    <th rowspan="2">Jadi Pengganti<br>(Jam)</th>
                </tr>
                <tr>
                    <th>Sakit</th>
                    <th>Izin</th>
                    <th>Dinas Luar</th>
                    <th>Alpa</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($rekap as $row)
                <tr>
                    <td class="nama">{{ $row['nama'] }}</td>
                    <td>{{ $row['sakit'] ?: '-' }}</td>
                    <td>{{ $row['izin'] ?: '-' }}</td>
                    <td>{{ $row['dinas_luar'] ?: '-' }}</td>
                    <td>{{ $row['alpa'] ?: '-' }}</td>
                    <td class="text-bold">{{ $row['total_tidak_hadir'] }}</td>
                    <td class="text-bold">{{ $row['total_mengganti'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    
    <p style="text-align: right; margin-top: 30px; font-size: 11px;">Dicetak pada: {{ now()->format('d/m/Y H:i') }}</p>
</body>
</html>
