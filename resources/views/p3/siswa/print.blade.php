<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan P3 — {{ $siswa->nama }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Georgia, serif; color: #000; margin: 0; background:#e9edf3; }
        .page { width: 210mm; min-height: 297mm; margin: 16px auto; padding: 14mm 16mm; background: #fff; box-shadow: 0 6px 24px rgba(0,0,0,.16); }

        .kop { display: flex; align-items: center; justify-content: space-between; gap: 16px; border-bottom: 5px double #000; padding-bottom: 6px; }
        .kop .logo { width: 85px; height: 85px; max-width: 90px; max-height: 90px; object-fit: contain; flex: 0 0 85px; }
        .kop .logo-spacer { width: 85px; flex: 0 0 85px; }
        .kop .ident { flex: 1; text-align: center; }
        .kop .ident .nm { font-size: 22px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; margin: 0; }
        .kop .ident .ad { font-size: 13px; margin: 1px 0 0; }
        .kop .ident p, .kop .ident h1, .kop .ident h2, .kop .ident h3, .kop .ident h4, .kop .ident h5, .kop .ident h6 { margin: 2px 0; line-height: 1.25; }
        .kop .ident ul, .kop .ident ol { margin: 2px 0; padding-left: 22px; text-align: left; display: inline-block; }

        .judul { text-align: center; font-weight: 700; font-size: 15px; letter-spacing: .4px; margin: 12px 0 10px; text-decoration: underline; }

        table.ident-grid { width: 100%; border-collapse: collapse; font-size: 12.5px; margin-bottom: 14px; }
        table.ident-grid td { padding: 1.5px 0; vertical-align: top; }
        table.ident-grid .l { width: 110px; } table.ident-grid .s { width: 10px; } table.ident-grid .v { font-weight: 600; }

        .sub-h { font-weight: 700; font-size: 13px; margin: 14px 0 6px; text-transform: uppercase; }
        table.tbl { width: 100%; border-collapse: collapse; border: 1px solid #444; font-size: 12px; }
        table.tbl th, table.tbl td { border: 1px solid #444; padding: 4px 7px; vertical-align: middle; }
        table.tbl thead th { background: #eef1f6; text-align: center; font-weight: 700; }
        table.tbl .t-no { width: 6%; text-align: center; }
        table.tbl .t-tgl { width: 16%; }
        table.tbl .t-poin { width: 12%; text-align: center; font-weight: 700; }
        table.tbl tfoot td { font-weight: 700; background: #f7f9fb; }
        .kosong { text-align: center; color: #888; font-style: italic; padding: 8px; }

        .ttd { display: flex; justify-content: space-between; margin-top: 28px; font-size: 13px; }
        .ttd p { margin: 2px 0; }
        .ttd .b { text-align: center; width: 46%; }
        .ttd .b .nm { font-weight: 700; margin-top: 52px; margin-bottom: 0; text-decoration: underline; }

        .toolbar { position: sticky; top: 0; z-index: 50; background: #0f172a; color: #fff; padding: 12px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; font-family: system-ui, sans-serif; }
        .toolbar .muted { color: #94a3b8; font-size: 12px; }
        .toolbar a, .toolbar button { color:#fff; background:transparent; border:1px solid #475569; border-radius:8px; padding:6px 12px; font-size:13px; cursor:pointer; text-decoration:none; }
        .toolbar button { background:#fff; color:#0f172a; font-weight:600; border-color:#fff; }

        @page { size: A4; margin: 0; }
        @media print {
            .toolbar { display: none !important; }
            body { background:#fff; }
            .page { margin: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div><b>Laporan P3</b> <span class="muted">&nbsp;{{ $siswa->nama }} &middot; Semester {{ $sem?->semester }} / {{ $sem?->tahun }}</span></div>
        <div style="display:flex; gap:10px;">
            <a href="{{ route('p3.siswa.show', $siswa) }}">&larr; Kembali</a>
            <button onclick="window.print()">🖨 Cetak / Simpan PDF</button>
        </div>
    </div>

    <div class="page">
        <div class="kop">
            @if($kopLogoKiri)<img src="{{ $kopLogoKiri }}" class="logo" alt="">@else<span class="logo-spacer"></span>@endif
            <div class="ident">
                @if(trim((string) $kopTeks) !== '')
                    {!! \App\Support\RichText::cleanKop($kopTeks) !!}
                @else
                    <p class="nm">{{ $sekolah['nama'] }}</p>
                    @if($sekolah['alamat'])
                        <p class="ad">{{ $sekolah['alamat'] }}</p>
                    @endif
                    <p class="ad">
                        {{ trim(implode(', ', array_filter([$sekolah['kota'] ?? '', $sekolah['provinsi'] ?? '']))) }}
                        @if($sekolah['telp'])
                            &middot; Telp. {{ $sekolah['telp'] }}
                        @endif
                        @if($sekolah['npsn'])
                            &middot; NPSN {{ $sekolah['npsn'] }}
                        @endif
                    </p>
                @endif
            </div>
            @if($kopLogoKanan)<img src="{{ $kopLogoKanan }}" class="logo" alt="">@else<span class="logo-spacer"></span>@endif
        </div>

        <div class="judul">LAPORAN PRESTASI PESERTA DIDIK</div>

        <table class="ident-grid">
            <tr><td class="l">Nama</td><td class="s">:</td><td class="v">{{ $siswa->nama }}</td><td class="l">Semester</td><td class="s">:</td><td class="v">{{ $sem?->semester ? $sem->semester . ' ( ' . ($sem->semester == 1 ? 'Ganjil' : 'Genap') . ' )' : '-' }}</td></tr>
            <tr><td class="l">Kelas</td><td class="s">:</td><td class="v">{{ $siswa->kelas ? $siswa->kelas->tingkat.$siswa->kelas->kelas : '-' }}</td><td class="l">Tahun Pelajaran</td><td class="s">:</td><td class="v">{{ $sem?->tahun ?? '-' }}</td></tr>
            <tr><td class="l">NIS / NISN</td><td class="s">:</td><td class="v">{{ $siswa->nis }} / {{ $siswa->nisn }}</td><td class="l"></td><td class="s"></td><td class="v"></td></tr>
        </table>

        @foreach(['prestasi'=>'A. Prestasi','partisipasi'=>'B. Partisipasi','pelanggaran'=>'C. Pelanggaran'] as $jenis => $label)
        <div class="sub-h">{{ $label }}</div>
        <table class="tbl">
            <thead><tr><th class="t-no">No</th><th class="t-tgl">Tanggal</th><th>Deskripsi</th><th class="t-poin">Poin</th></tr></thead>
            <tbody>
                @forelse($grup[$jenis] as $i => $r)
                <tr>
                    <td class="t-no">{{ $i+1 }}</td>
                    <td class="t-tgl">{{ $r->tanggal->isoFormat('D MMM Y') }}</td>
                    <td>{{ $r->deskripsi }}</td>
                    <td class="t-poin">{{ $r->poin }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="kosong">Tidak ada catatan</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr><td colspan="3" style="text-align:right">Total Poin</td><td class="t-poin">{{ $grup[$jenis]->sum('poin') }}</td></tr></tfoot>
        </table>
        @endforeach

        <div class="ttd">
            <div class="b">
                <p>&nbsp;<br>Orang Tua / Wali Murid</p>
                <p class="nm" style="text-decoration: none; font-weight: normal; margin-top: 52px;">(...............................................)</p>
            </div>
            <div class="b">
                <p>{{ $sekolah['kota'] ?: 'Tanjungpinang' }}, {{ $tanggal }}<br>Wali Kelas</p>
                @if($walikelas?->nama)
                    <p class="nm">{{ $walikelas->nama }}</p>
                @else
                    <p class="nm" style="text-decoration: none; font-weight: normal; margin-top: 52px;">(...............................................)</p>
                @endif
                <p>NIK. {{ $walikelas?->nik ?? '-' }}</p>
            </div>
        </div>
    </div>
</body>
</html>
