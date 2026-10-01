<!DOCTYPE html>
<html>
<head>
    <title>Cetak QR - {{ $kegiatan->nama_kegiatan }}</title>
    @if(isset($sekolahLogoUrl) && $sekolahLogoUrl)<link rel="icon" href="{{ $sekolahLogoUrl }}" type="image/png">@endif
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Georgia, serif; margin: 0; }
        .kop { display: flex; align-items: center; gap: 12px; border-bottom: 4px double #000; padding-bottom: 6px; margin-bottom: 15px; }
        .kop .logo { width: 65px; height: 65px; object-fit: contain; flex: 0 0 auto; }
        .kop .ident { flex: 1; text-align: center; }
        .kop .ident .nm { font-size: 20px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin: 0; }
        .kop .ident .ad { font-size: 12px; margin: 2px 0 0; }
        .kop .ident p, .kop .ident h1, .kop .ident h2, .kop .ident h3, .kop .ident h4, .kop .ident h5, .kop .ident h6 { margin: 2px 0; line-height: 1.25; }
        .judul-kegiatan { text-align: center; margin-bottom: 15px; }
        .judul-kegiatan h3 { margin: 0 0 4px 0; font-size: 16px; text-transform: uppercase; }
        .judul-kegiatan p { margin: 0; font-size: 13px; }
        .grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; font-family: sans-serif; }
        .card { 
            border: 1px dashed #000; padding: 15px; text-align: center; page-break-inside: avoid; 
            display: flex; flex-direction: column; justify-content: space-between; min-height: 255px;
        }
        .card-header { margin-bottom: 10px; }
        .card h4 { margin: 0 0 4px 0; font-size: 14px; line-height: 1.25; }
        .card .instansi { font-size: 12px; color: #555; margin: 0; line-height: 1.2; }
        .card-body { display: flex; flex-direction: column; align-items: center; justify-content: flex-end; }
        .card img { width: 140px; height: 140px; margin-bottom: 5px; }
        .card .token { font-size: 11px; margin: 0; font-family: monospace; font-weight: bold; letter-spacing: 1px; }
        .page { page-break-after: always; padding-bottom: 1px; }
        .page:last-child { page-break-after: auto; }
    </style>
</head>
<body>
    @foreach($kegiatan->pesertas->chunk(12) as $chunk)
    <div class="page">
        <div class="kop">
            @if($kopLogoKiri)<img src="{{ $kopLogoKiri }}" class="logo" alt="">@else<span class="logo"></span>@endif
            <div class="ident">
                @if(trim((string) $kopTeks) !== '')
                    {!! $kopTeks !!}
                @else
                    <p class="nm">{{ $sekolah['nama'] }}</p>
                    @if($sekolah['alamat'])<p class="ad">{{ $sekolah['alamat'] }}</p>@endif
                    <p class="ad">{{ trim(implode(', ', array_filter([$sekolah['kota'], $sekolah['provinsi']]))) }}@if($sekolah['telp']) &middot; Telp. {{ $sekolah['telp'] }}@endif @if($sekolah['npsn']) &middot; NPSN {{ $sekolah['npsn'] }}@endif</p>
                @endif
            </div>
            @if($kopLogoKanan)<img src="{{ $kopLogoKanan }}" class="logo" alt="">@else<span class="logo"></span>@endif
        </div>

        <div class="judul-kegiatan">
            <h3>KARTU QR CODE PESERTA KEGIATAN</h3>
            <p><strong>{{ $kegiatan->nama_kegiatan }}</strong></p>
            <p>{{ $sekolah['nama'] }} &middot; {{ $kegiatan->tanggal_mulai->translatedFormat('d F Y') }}</p>
        </div>

        <div class="grid">
            @foreach($chunk as $peserta)
            <div class="card">
                <div class="card-header">
                    <h4>{{ Str::limit($peserta->biodata['nama'] ?? '-', 45) }}</h4>
                    <p class="instansi">{{ $peserta->biodata['instansi'] ?? '-' }}</p>
                </div>
                <div class="card-body">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data={{ urlencode(route('kegiatan.hadir', $peserta->qr_token)) }}" alt="QR Code">
                    <p class="token">{{ $peserta->qr_token }}</p>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endforeach
    <script>window.print();</script>
</body>
</html>
