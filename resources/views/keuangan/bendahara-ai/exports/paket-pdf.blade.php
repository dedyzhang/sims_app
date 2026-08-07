<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Paket Verifikasi SPP — T.A. {{ $ta }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { font-size: 8px; color: #64748b; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 5px; text-align: left; vertical-align: top; }
        th { background: #f1f5f9; font-weight: bold; }
        .num { text-align: right; font-family: monospace; }
        .footer { margin-top: 10px; font-size: 7px; color: #94a3b8; }
    </style>
</head>
<body>
    <h1>Paket Kerja Verifikasi SPP</h1>
    <p class="meta">
        Tahun Ajaran {{ $ta }}
        @if($status) · Filter: {{ $labels->labelStatus($status) }} @endif
        · Dicetak {{ now()->format('d-m-Y H:i') }}
        · {{ $rows->count() }} baris
    </p>

    <table>
        <thead>
            <tr>
                <th>No</th>
                <th>NIS</th>
                <th>Nama</th>
                <th>Kelas</th>
                <th>Bulan</th>
                <th class="num">Nominal</th>
                <th>Status</th>
                <th>Tgl Bayar</th>
                <th>Verifikator</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $i => $row)
                @php $kelas = $row->siswa?->kelas; @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row->siswa?->nis ?? '-' }}</td>
                    <td>{{ $row->siswa?->nama ?? '-' }}</td>
                    <td>{{ $kelas ? $kelas->tingkat.$kelas->kelas : '-' }}</td>
                    <td>{{ $row->label_bulan }}</td>
                    <td class="num">{{ number_format($row->nominal, 0, ',', '.') }}</td>
                    <td>{{ $labels->labelStatus($row->status) }}</td>
                    <td>{{ $row->tanggal_bayar?->format('d-m-Y') ?? '-' }}</td>
                    <td>{{ $row->verifikator?->username ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9">Tidak ada data untuk diekspor.</td></tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">Dokumen internal bendahara — bukan laporan resmi ARKAS/DJPK. Nominal dari sistem SIMS (BIGINT).</p>
</body>
</html>
