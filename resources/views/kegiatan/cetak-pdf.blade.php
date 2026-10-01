<!DOCTYPE html>
<html>
<head>
    <title>Daftar Hadir - {{ $kegiatan->nama_kegiatan }}</title>
    <style>
        body { font-family: "Times New Roman", Georgia, serif; font-size: 12px; }
        .kop { margin-bottom: 20px; border-bottom: 4px double #000; padding-bottom: 6px; }
        .kop-table { width: 100%; border: none; margin: 0; }
        .kop-table td { border: none; padding: 0; vertical-align: middle; }
        .kop .logo { width: 65px; height: 65px; object-fit: contain; }
        .kop .ident { text-align: center; }
        .kop .ident .nm { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin: 0; }
        .kop .ident .ad { font-size: 12px; margin: 2px 0 0; }
        .kop .ident p, .kop .ident h1, .kop .ident h2, .kop .ident h3, .kop .ident h4, .kop .ident h5, .kop .ident h6 { margin: 2px 0; line-height: 1.25; }
        
        table.data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.data-table th, table.data-table td { border: 1px solid #000; padding: 6px; }
        table.info-table { width: 100%; border-collapse: collapse; margin-top: 10px; border: none; }
        table.info-table td { border: none; padding: 4px; }
        .ttd-kiri { text-align: left; vertical-align: top; height: 30px; }
        .ttd-kanan { text-align: right; vertical-align: top; height: 30px; }
        .ttd-box { float: right; margin-top: 40px; text-align: center; width: 250px; line-height: 1.5; }
    </style>
</head>
<body>
    <div class="kop">
        <table class="kop-table">
            <tr>
                <td width="70" style="text-align: left;">
                    @if($kopLogoKiri)<img src="{{ $kopLogoKiri }}" class="logo" alt="">@endif
                </td>
                <td class="ident">
                    @if(trim((string) $kopTeks) !== '')
                        {!! $kopTeks !!}
                    @else
                        <p class="nm">{{ $sekolah['nama'] }}</p>
                        @if($sekolah['alamat'])<p class="ad">{{ $sekolah['alamat'] }}</p>@endif
                        <p class="ad">{{ trim(implode(', ', array_filter([$sekolah['kota'], $sekolah['provinsi']]))) }}@if($sekolah['telp']) &middot; Telp. {{ $sekolah['telp'] }}@endif @if($sekolah['npsn']) &middot; NPSN {{ $sekolah['npsn'] }}@endif</p>
                    @endif
                </td>
                <td width="70" style="text-align: right;">
                    @if($kopLogoKanan)<img src="{{ $kopLogoKanan }}" class="logo" alt="">@endif
                </td>
            </tr>
        </table>
    </div>

    <div style="text-align: center; margin-bottom: 20px;">
        <h3 style="margin: 0; font-size: 14px;">DAFTAR HADIR NARASUMBER & PESERTA</h3>
    </div>

    <table class="info-table">
        <tr>
            <td width="120">Acara</td>
            <td>: {{ $kegiatan->nama_kegiatan }}</td>
        </tr>
        <tr>
            <td>Tema</td>
            <td>: {{ $kegiatan->tema ?? '-' }}</td>
        </tr>
        <tr>
            <td>Hari / Tanggal</td>
            <td>: {{ $kegiatan->tanggal_mulai->translatedFormat('l / d F Y') }}</td>
        </tr>
        <tr>
            <td>Waktu</td>
            <td>: {{ $kegiatan->waktu_mulai ?? '07:30' }} s/d selesai</td>
        </tr>
        <tr>
            <td>Tempat</td>
            <td>: {{ $kegiatan->tempat ?? '-' }}</td>
        </tr>
    </table>

    <table class="data-table" style="margin-top: 20px;">
        <thead>
            <tr style="background: #eef;">
                <th width="30">NO</th>
                <th>NAMA PESERTA</th>
                <th>INSTANSI</th>
                <th width="150">TANDA TANGAN</th>
                <th>KETERANGAN</th>
            </tr>
        </thead>
        <tbody>
            @foreach($kegiatan->pesertas as $i => $peserta)
            <tr>
                <td style="text-align: center;">{{ $i + 1 }}</td>
                <td>{{ $peserta->biodata['nama'] ?? '-' }}</td>
                <td>{{ $peserta->biodata['instansi'] ?? '-' }}</td>
                <td class="{{ ($i % 2 == 0) ? 'ttd-kiri' : 'ttd-kanan' }}">
                    {{ $i + 1 }}. 
                    @if($peserta->status_kehadiran == 'hadir')
                        <i>(Hadir Digital)</i>
                    @endif
                </td>
                <td>{{ $peserta->status_kehadiran == 'hadir' ? $peserta->waktu_hadir->format('H:i') : '' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="ttd-box">
        {{ $sekolah['kota'] ?? 'Tanjungpinang' }}, {{ $kegiatan->tanggal_mulai->translatedFormat('d F Y') }}<br>
        Mengetahui,<br>
        Kepala Sekolah<br>
        <br><br><br><br>
        <strong>{{ $sekolah['kepala'] }}</strong>
        @if(!empty($sekolah['nip']))
        <br>NIP. {{ $sekolah['nip'] }}
        @endif
    </div>
</body>
</html>
