<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 8mm 10mm; }
        * { font-family: "DejaVu Sans", sans-serif; }
        body { margin: 0; color: #0f172a; }

        table.sheet { width: 100%; border-collapse: collapse; }
        table.sheet td.slot { width: 50%; padding: 1.6mm; vertical-align: top; }

        /* Kartu ukuran ATM / CR80 = 85,6 x 54 mm */
        .mini { width: 85.6mm; height: 54mm; border: 0.75pt solid #cbd5e1; border-radius: 2.6mm; }

        .mini .hd { background-color: #1e40af; background-image: linear-gradient(135deg, #3b82f6 0%, #1e3a8a 100%); color: #ffffff; padding: 1.6mm 3mm; border-radius: 2.3mm 2.3mm 0 0; }
        .mini .hd .sch { font-size: 7.5pt; font-weight: bold; line-height: 1.1; }
        .mini .hd .np { font-size: 5pt; color: #dbeafe; margin-top: 0.4mm; }
        .mini .hd .logo { width: 8mm; height: 8mm; background: #ffffff; border-radius: 4mm; text-align: center; }
        .mini .hd .logo img { width: 6.4mm; height: 6.4mm; margin-top: 0.8mm; }

        .mini .ribbon { background: #3b82f6; color: #ffffff; text-align: center; font-size: 5.2pt; font-weight: bold; letter-spacing: 2pt; padding: 0.6mm 0; }

        .mini .bd { padding: 1.8mm 3mm 0; }
        .mini .nm { font-size: 8.5pt; font-weight: bold; color: #1e293b; line-height: 1.05; }
        .mini .nis { font-size: 5.8pt; font-weight: bold; color: #2563eb; margin: 0.4mm 0 1.2mm; }
        table.dl { border-collapse: collapse; }
        table.dl td { font-size: 5.6pt; padding: 0.35mm 0; vertical-align: top; line-height: 1.15; }
        .lb { color: #64748b; width: 15mm; }
        .cl { width: 1.6mm; color: #94a3b8; }
        .vl { font-weight: bold; color: #1e293b; }
        .qr { width: 15mm; height: 15mm; }

        .mini .ft { padding: 0 3mm; }
        .badge { display: inline-block; background: #dbeafe; color: #1e40af; font-size: 4.8pt; font-weight: bold; padding: 0.6mm 2mm; border-radius: 1.6mm; }
    </style>
</head>
<body>
@foreach($pages as $page)
<div @if(! $loop->last) style="page-break-after: always;" @endif>
    <table class="sheet">
        @foreach($page->chunk(2) as $row)
        <tr>
            @foreach($row as $c)
            @php
                $s = $c['siswa'];
                $ttl = trim(($s->tempat_lahir ?: '') . ($s->tanggal_lahir ? ', ' . \Illuminate\Support\Carbon::parse($s->tanggal_lahir)->translatedFormat('d M Y') : ''));
                $ttl = trim($ttl, ', ') ?: '-';
                $jk  = $s->jk === 'P' ? 'Perempuan' : ($s->jk === 'L' ? 'Laki-laki' : '-');
            @endphp
            <td class="slot">
                <div class="mini">
                    <div class="hd">
                        <table style="width:100%;"><tr>
                            <td style="width:9mm; vertical-align:middle;"><div class="logo">@if($logoUri)<img src="{{ $logoUri }}">@endif</div></td>
                            <td style="vertical-align:middle; padding-left:1.5mm;">
                                <div class="sch">{{ \Illuminate\Support\Str::limit($sekolah['nama'], 34) }}</div>
                                @if($sekolah['npsn'])<div class="np">NPSN {{ $sekolah['npsn'] }}</div>@endif
                            </td>
                        </tr></table>
                    </div>
                    <div class="ribbon">KARTU TANDA PELAJAR</div>
                    <div class="bd">
                        <table style="width:100%;"><tr>
                            <td style="vertical-align:top;">
                                <div class="nm">{{ \Illuminate\Support\Str::limit($s->nama, 26) }}</div>
                                <div class="nis">NIS {{ $s->nis ?: '-' }}</div>
                                <table class="dl">
                                    <tr><td class="lb">Tempat, Tgl Lahir</td><td class="cl">:</td><td class="vl">{{ \Illuminate\Support\Str::limit($ttl, 30) }}</td></tr>
                                    <tr><td class="lb">Jenis Kelamin</td><td class="cl">:</td><td class="vl">{{ $jk }}</td></tr>
                                    <tr><td class="lb">Agama</td><td class="cl">:</td><td class="vl">{{ $s->agama ?: '-' }}</td></tr>
                                    <tr><td class="lb">Alamat</td><td class="cl">:</td><td class="vl">{{ \Illuminate\Support\Str::limit((string) $s->alamat, 34) ?: '-' }}</td></tr>
                                </table>
                            </td>
                            <td style="width:16mm; text-align:center; vertical-align:top;">
                                <img src="{{ $c['qrUri'] }}" class="qr">
                            </td>
                        </tr></table>
                    </div>
                    <div class="ft"><span class="badge">SAMPAI TAMAT SEKOLAH</span></div>
                </div>
            </td>
            @endforeach
            @if($row->count() === 1)<td class="slot"></td>@endif
        </tr>
        @endforeach
    </table>
</div>
@endforeach
</body>
</html>
