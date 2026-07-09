<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 7mm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; color: #0f172a; }

        .card { border: 1px solid #e2e8f0; border-radius: 14px; }

        /* Header — meniru gradient primary di web */
        .header { background-color: #1e40af; background-image: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%); color: #ffffff; padding: 11px 15px; border-radius: 13px 13px 0 0; }
        .header .logo { width: 42px; height: 42px; background: #ffffff; border-radius: 21px; text-align: center; }
        .header .logo img { width: 32px; height: 32px; margin-top: 5px; }
        .header .school { font-size: 14px; font-weight: bold; line-height: 1.15; }
        .header .sub { font-size: 8px; color: #dbeafe; margin-top: 3px; }

        .ribbon { background: #3b82f6; color: #ffffff; text-align: center; font-size: 10px; font-weight: bold; letter-spacing: 5px; padding: 4px 0; }

        /* Body */
        .body { padding: 13px 15px 10px; }
        .name { font-size: 17px; font-weight: bold; color: #1e293b; line-height: 1.1; }
        .nis-sub { font-size: 9px; font-weight: bold; color: #2563eb; margin: 2px 0 9px; }

        table.details { border-collapse: collapse; width: 100%; }
        table.details td { font-size: 9.5px; padding: 2.5px 0; vertical-align: top; }
        .label { color: #64748b; width: 92px; }
        .colon { width: 8px; color: #94a3b8; }
        .val { font-weight: bold; color: #1e293b; }

        .qr { width: 76px; height: 76px; }
        .qr-cap { font-size: 7px; color: #94a3b8; margin-top: 3px; }

        /* Footer */
        .footer { background: #f8fafc; border-top: 1px solid #eef2f7; border-radius: 0 0 13px 13px; padding: 8px 15px; }
        .valid-label { font-size: 7px; color: #94a3b8; letter-spacing: 1px; margin-bottom: 3px; }
        .valid { display: inline-block; background: #dbeafe; color: #1e40af; font-size: 8.5px; font-weight: bold; padding: 3px 10px; border-radius: 9px; }
        .sign { font-size: 8.5px; color: #475569; text-align: right; }
        .sign .nm { font-weight: bold; color: #1e293b; }
    </style>
</head>
<body>
@php
    $ttl = trim(($siswa->tempat_lahir ?: '') . ($siswa->tanggal_lahir ? ', ' . \Illuminate\Support\Carbon::parse($siswa->tanggal_lahir)->translatedFormat('d F Y') : ''));
    $ttl = trim($ttl, ', ') ?: '-';
    $jk  = $siswa->jk === 'P' ? 'Perempuan' : ($siswa->jk === 'L' ? 'Laki-laki' : '-');
@endphp
<div class="card">
    {{-- Header --}}
    <div class="header">
        <table style="width:100%;"><tr>
            <td style="width:50px; vertical-align:middle;">
                <div class="logo">@if($logoUri)<img src="{{ $logoUri }}">@endif</div>
            </td>
            <td style="vertical-align:middle; padding-left:5px;">
                <div class="school">{{ $sekolah['nama'] }}</div>
                <div class="sub">{{ $sekolah['alamat'] ?: 'Alamat sekolah belum diatur' }}@if($sekolah['npsn']) &nbsp;&bull;&nbsp; NPSN {{ $sekolah['npsn'] }}@endif</div>
            </td>
        </tr></table>
    </div>

    <div class="ribbon">KARTU TANDA PELAJAR</div>

    {{-- Body: detail (kiri) + QR (kanan) --}}
    <div class="body">
        <table style="width:100%;"><tr>
            <td style="vertical-align:top;">
                <div class="name">{{ $siswa->nama }}</div>
                <div class="nis-sub">NIS {{ $siswa->nis ?: '-' }}</div>
                <table class="details">
                    <tr><td class="label">Tempat, Tgl Lahir</td><td class="colon">:</td><td class="val">{{ $ttl }}</td></tr>
                    <tr><td class="label">Jenis Kelamin</td><td class="colon">:</td><td class="val">{{ $jk }}</td></tr>
                    <tr><td class="label">Agama</td><td class="colon">:</td><td class="val">{{ $siswa->agama ?: '-' }}</td></tr>
                    <tr><td class="label">Alamat</td><td class="colon">:</td><td class="val">{{ \Illuminate\Support\Str::limit((string) $siswa->alamat, 72) ?: '-' }}</td></tr>
                </table>
            </td>
            <td style="width:84px; text-align:center; vertical-align:top;">
                @if($qrUri)<img src="{{ $qrUri }}" class="qr">@endif
                <div class="qr-cap">{{ $siswa->nis ?: $siswa->nisn }}</div>
            </td>
        </tr></table>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <table style="width:100%;"><tr>
            <td style="vertical-align:bottom;">
                <div class="valid-label">MASA BERLAKU</div>
                <span class="valid">SAMPAI TAMAT SEKOLAH</span>
            </td>
            <td class="sign" style="vertical-align:bottom; width:135px;">
                {{ $sekolah['kota'] ?: '' }}<br>
                Kepala Sekolah<br><br>
                <span class="nm">{{ $sekolah['kepala'] ?: '________________' }}</span>
            </td>
        </tr></table>
    </div>
</div>
</body>
</html>
