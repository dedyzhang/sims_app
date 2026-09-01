<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>QR Pemilihan OSIS — {{ $kelas ? 'Kelas '.$kelas->tingkat.$kelas->kelas : 'Guru' }}</title>
    <style>
        * { box-sizing: border-box; font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; color: #0f172a; }
        @page { margin: 8mm 10mm; } {{-- tinggi konten ≈281mm, konvensi sama kartu-pelajar/kartu-guru cetak-massal --}}

        .kop { text-align: center; margin-bottom: 3mm; }
        .kop .sch { font-size: 13px; font-weight: bold; }
        .kop .jdl { font-size: 11px; margin-top: 1mm; }
        .kop .info { font-size: 9.5px; color: #475569; margin-top: 1mm; }

        table.roster { width: 100%; table-layout: fixed; border-collapse: collapse; }
        table.roster th, table.roster td { border: 1px solid #cbd5e1; padding: 2mm 3mm; vertical-align: middle; }
        table.roster th { background: #f1f5f9; font-size: 9px; text-transform: uppercase; }
        .col-no  { width: 6%; text-align: center; font-weight: bold; }
        .col-nm  { width: 46%; }
        .col-tt  { width: 20%; text-align: center; font-size: 9px; color: #64748b; }
        .col-qr  { width: 28%; text-align: center; }
        .col-qr img { width: 20mm; height: 20mm; }
        .compact .col-qr img { width: 17mm; height: 17mm; }
    </style>
</head>
<body>
@foreach ($pages as $page)
<div @if (! $loop->last) style="page-break-after: always;" @endif>
    <div class="kop">
        <div class="sch">{{ $sekolah['nama'] }}@if($sekolah['npsn']) &middot; NPSN {{ $sekolah['npsn'] }}@endif</div>
        <div class="jdl">DAFTAR PEMILIH &amp; QR — {{ $pemilihan->nama }}</div>
        <div class="info">{{ $kelas ? 'Kelas '.$kelas->tingkat.$kelas->kelas : 'Guru & Karyawan' }} — Pindai QR pada baris nama Anda untuk memilih.</div>
    </div>
    <table class="roster {{ $perHalaman > 10 ? 'compact' : '' }}">
        <thead>
            <tr>
                <th class="col-no">No</th>
                <th class="col-nm">Nama</th>
                <th class="col-tt">{{ $kelas ? 'NIS' : 'NIP' }}</th>
                <th class="col-qr">QR Pemilihan</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($page as $i => $r)
            <tr>
                {{-- $perHalaman (BUKAN $page->count()) sbg pengali — halaman terakhir bisa lebih
                     pendek dari $perHalaman, pakai count()-nya sendiri akan salah nomor. --}}
                <td class="col-no">{{ $loop->parent->index * $perHalaman + $i + 1 }}</td>
                <td class="col-nm">{{ $r['nama'] }}</td>
                <td class="col-tt">{{ $r['nis'] }}</td>
                <td class="col-qr"><img src="{{ $r['qrUri'] }}"></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endforeach
</body>
</html>
