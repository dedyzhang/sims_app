<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Daftar Hadir Pemilihan OSIS — {{ $judulKelompok }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Georgia, serif; color: #000; margin: 0; font-size: 12.5px; }

        {{-- dompdf TIDAK menghormati box-sizing:border-box utk width — width di sini HARUS
             lebar KONTEN saja (170mm), bukan 210mm penuh (lihat catatan sama di beritaAcaraCetak). --}}
        .page { width: 170mm; padding: 14mm 20mm; background: #fff; }

        .kop { border-bottom: 3px double #000; padding-bottom: 8px; margin-bottom: 4mm; }
        .kop table { width: 100%; border-collapse: collapse; }
        .kop td { vertical-align: middle; }
        .kop .logo-cell { width: 60px; text-align: center; }
        .kop .logo-cell img { width: 60px; height: 60px; object-fit: contain; }
        .kop .ident-cell { text-align: center; }
        .kop .ident-cell p, .kop .ident-cell h1, .kop .ident-cell h2 { margin: 1px 0; line-height: 1.25; }

        .judul { text-align: center; margin: 3mm 0 5mm; }
        .judul .t1 { font-size: 15px; font-weight: 700; letter-spacing: .5px; margin: 0; text-decoration: underline; }
        .judul .t2 { font-size: 12.5px; margin: 3px 0 0; }

        table.info { width: 100%; border-collapse: collapse; margin-bottom: 4mm; font-size: 12.5px; }
        table.info td { padding: 1.5px 0; }
        table.info .lbl { width: 46mm; }
        table.info .colon { width: 5mm; }

        table.roster { width: 100%; table-layout: fixed; border-collapse: collapse; font-size: 11.5px; }
        table.roster th, table.roster td { border: 1px solid #000; padding: 2.5px 5px; vertical-align: middle; }
        table.roster th { background: #e5e5e5 !important; font-weight: 700; text-align: center; }
        .no  { width: 7%; text-align: center; }
        .nis { width: 18%; text-align: center; }
        .st  { width: 20%; text-align: center; }
        .wk  { width: 18%; text-align: center; font-size: 10.5px; color: #333; }

        .ttd-heading { margin: 8mm 0 6mm; }
        table.ttd { width: 100%; border-collapse: collapse; }
        table.ttd td { width: 50%; text-align: center; vertical-align: top; }
        table.ttd .peran { margin: 0 0 16mm; }
        table.ttd .nm { font-weight: 700; text-decoration: underline; margin: 0; }

        @page { size: A4; margin: 0; }
    </style>
</head>
<body>
<div class="page">
    <div class="kop">
        <table>
            <tr>
                <td class="logo-cell">@if($kopLogoKiri)<img src="{{ $kopLogoKiri }}" alt="Logo">@endif</td>
                <td class="ident-cell">
                    @if($kopTeks)
                        {!! \App\Support\RichText::clean($kopTeks) !!}
                    @else
                        <h2 style="font-family: 'Times New Roman', Georgia, serif; font-size: 22px; font-weight: bold; margin: 0; padding: 0;">{{ $namaSekolah }}</h2>
                        <p style="font-family: 'Times New Roman', Georgia, serif; font-size: 14px; margin: 2px 0 0; padding: 0;">{{ $alamatSekolah }}</p>
                    @endif
                </td>
                <td class="logo-cell">@if($kopLogoKanan)<img src="{{ $kopLogoKanan }}" alt="Logo">@endif</td>
            </tr>
        </table>
    </div>

    <div class="judul">
        <p class="t1">DAFTAR HADIR PEMILIHAN KETUA OSIS</p>
        <p class="t2">{{ $pemilihan->nama }}</p>
    </div>

    <table class="info">
        <tr><td class="lbl">Kelompok</td><td class="colon">:</td><td>{{ $judulKelompok }}</td></tr>
        <tr><td class="lbl">Jumlah Pemilih Terdaftar</td><td class="colon">:</td><td>{{ $rows->count() }} orang</td></tr>
        <tr><td class="lbl">Jumlah Sudah Memilih</td><td class="colon">:</td><td>{{ $rows->where('sudah', true)->count() }} orang</td></tr>
    </table>

    <table class="roster">
        <thead>
            <tr>
                <th class="no">No</th>
                <th>Nama</th>
                <th class="nis">{{ $labelIdentitas }}</th>
                <th class="st">Status</th>
                <th class="wk">Waktu Memilih</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($rows as $i => $r)
            <tr>
                <td class="no">{{ $i + 1 }}</td>
                <td>{{ $r['nama'] }}</td>
                <td class="nis">{{ $r['nis'] }}</td>
                <td class="st">{{ $r['sudah'] ? 'Sudah Pilih' : 'Belum Pilih' }}</td>
                <td class="wk">{{ $r['waktu'] ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div class="ttd-heading">
        <table class="ttd">
            <tr>
                <td>
                    <p class="peran">Panitia Pemilihan</p>
                    <p class="nm">.................................</p>
                </td>
                <td>
                    <p class="peran">Mengetahui,<br>Kepala Sekolah</p>
                    <p class="nm">{{ $kepsekNama ?: '.................................' }}</p>
                </td>
            </tr>
        </table>
    </div>
</div>
</body>
</html>
