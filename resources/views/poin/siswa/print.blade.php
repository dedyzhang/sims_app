<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rekapitulasi Poin Kedisiplinan — {{ $siswa->nama }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: "Times New Roman", Georgia, serif;
            color: #111;
            margin: 0;
            background: #e9edf3;
            line-height: 1.35;
        }

        .page {
            width: 210mm;
            min-height: 297mm;
            margin: 16px auto;
            padding: 12mm 15mm;
            background: #fff;
            box-shadow: 0 4px 20px rgba(0,0,0,.15);
        }

        .kop {
            display: flex;
            align-items: center;
            gap: 12px;
            border-bottom: 5px double #000;
            padding-bottom: 6px;
        }
        .kop .logo {
            width: 70px;
            height: 70px;
            object-fit: contain;
            flex: 0 0 auto;
        }
        .kop .ident {
            flex: 1;
            text-align: center;
        }
        .kop .ident .nm {
            font-size: 20px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
            margin: 0;
        }
        .kop .ident .ad {
            font-size: 12px;
            margin: 1px 0 0;
        }
        .kop .ident p, .kop .ident h1, .kop .ident h2, .kop .ident h3, .kop .ident h4, .kop .ident h5, .kop .ident h6 {
            margin: 2px 0;
            line-height: 1.25;
        }
        .kop .ident ul, .kop .ident ol {
            margin: 2px 0;
            padding-left: 20px;
            text-align: left;
            display: inline-block;
        }

        .judul-wrap {
            text-align: center;
            margin: 12px 0 10px;
        }
        .judul {
            font-weight: 700;
            font-size: 15px;
            letter-spacing: .4px;
            text-transform: uppercase;
            text-decoration: underline;
        }
        .subjudul {
            font-size: 11.5px;
            font-weight: 600;
            color: #444;
            margin-top: 2px;
            text-transform: uppercase;
        }

        table.ident-grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            margin-bottom: 12px;
        }
        table.ident-grid td {
            padding: 2px 0;
            vertical-align: top;
        }
        table.ident-grid .l { width: 110px; }
        table.ident-grid .s { width: 10px; }
        table.ident-grid .v { font-weight: 600; }

        .summary-card {
            border: 1px solid #333;
            background: #fafbfc;
            padding: 8px 12px;
            margin-bottom: 12px;
            break-inside: avoid;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            text-align: center;
        }
        .summary-item {
            padding: 4px 6px;
            border-right: 1px dashed #bbb;
        }
        .summary-item:last-child {
            border-right: none;
        }
        .summary-label {
            font-size: 10px;
            color: #555;
            text-transform: uppercase;
            font-weight: 600;
        }
        .summary-value {
            font-size: 16px;
            font-weight: 800;
            margin-top: 2px;
        }
        .status-box {
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid #ddd;
            font-size: 11.5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .badge-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            font-size: 11px;
            text-transform: uppercase;
        }
        .badge-aman { background: #e6f4ea; color: #137333; border: 1px solid #ceead6; }
        .badge-sp1 { background: #fef7e0; color: #b06000; border: 1px solid #fce8b2; }
        .badge-sp2 { background: #fce8e6; color: #c5221f; border: 1px solid #fad2cf; }
        .badge-sp3 { background: #b71c1c; color: #fff; border: 1px solid #8e0000; }

        .section-title {
            font-weight: 700;
            font-size: 12px;
            margin: 10px 0 6px;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 2px;
        }

        table.tbl {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #333;
            font-size: 11.5px;
            margin-bottom: 12px;
        }
        table.tbl th, table.tbl td {
            border: 1px solid #333;
            padding: 4px 6px;
            vertical-align: middle;
        }
        table.tbl thead th {
            background: #eef1f6;
            text-align: center;
            font-weight: 700;
        }
        table.tbl .t-no { width: 5%; text-align: center; }
        table.tbl .t-tgl { width: 14%; text-align: center; }
        table.tbl .t-kode { width: 12%; text-align: center; font-family: monospace; }
        table.tbl .t-jenis { width: 13%; text-align: center; }
        table.tbl .t-poin { width: 10%; text-align: center; font-weight: 700; }
        table.tbl .t-sisa { width: 10%; text-align: center; font-weight: 700; }
        table.tbl tfoot td {
            font-weight: 700;
            background: #f7f9fb;
        }
        .kosong {
            text-align: center;
            color: #555;
            font-style: italic;
            padding: 10px;
        }

        .catatan-box {
            border: 1px solid #777;
            padding: 8px 12px;
            font-size: 10.5px;
            background: #fff;
            margin-bottom: 14px;
            break-inside: avoid;
            line-height: 1.4;
        }
        .catatan-box b { font-size: 11px; }
        .catatan-box ol, .catatan-box ul {
            margin: 3px 0 2px 18px;
            padding: 0;
        }

        .ttd-wrap {
            margin-top: 14px;
            font-size: 11.5px;
            break-inside: avoid;
        }
        .ttd-grid {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            text-align: center;
        }
        .ttd-col {
            flex: 1;
            padding: 0 4px;
        }
        .ttd-col p { margin: 2px 0; }
        .ttd-col .nm {
            margin-top: 50px;
            margin-bottom: 0;
            font-weight: 700;
        }
        .ttd-col .nik {
            margin: 1px 0 0;
            font-size: 10.5px;
            color: #333;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #0f172a;
            color: #fff;
            padding: 10px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            font-family: system-ui, -apple-system, sans-serif;
        }
        .toolbar .muted {
            color: #94a3b8;
            font-size: 12px;
        }
        .toolbar a, .toolbar button {
            color: #fff;
            background: transparent;
            border: 1px solid #475569;
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
        }
        .toolbar button {
            background: #fff;
            color: #0f172a;
            border-color: #fff;
        }

        @page {
            size: A4;
            margin: 0;
        }
        @media print {
            .toolbar { display: none !important; }
            body { background: #fff; }
            .page {
                margin: 0;
                padding: 10mm 14mm;
                box-shadow: none;
                width: 100%;
                min-height: auto;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <b>Cetak Rekapitulasi Poin Siswa</b>
            <span class="muted">&nbsp;&bull;&nbsp;{{ $siswa->nama }} (NIS {{ $siswa->nis }})</span>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="{{ route('poin.siswa.show', $siswa) }}">&larr; Kembali</a>
            <button onclick="window.print()">🖨 Cetak / Simpan PDF</button>
        </div>
    </div>

    <div class="page">
        {{-- Kop Surat Resmi --}}
        <div class="kop">
            @if($kopLogoKiri)
                <img src="{{ $kopLogoKiri }}" class="logo" alt="Logo Kiri">
            @else
                <span class="logo"></span>
            @endif
            <div class="ident">
                @if(trim((string) $kopTeks) !== '')
                    {!! \App\Support\RichText::clean($kopTeks) !!}
                @else
                    <p class="nm">{{ $sekolah['nama'] }}</p>
                    @if($sekolah['alamat'])
                        <p class="ad">{{ $sekolah['alamat'] }}</p>
                    @endif
                    <p class="ad">
                        {{ trim(implode(', ', array_filter([$sekolah['kota'] ?? '', $sekolah['provinsi'] ?? '']))) }}
                        @if($sekolah['telp']) &middot; Telp. {{ $sekolah['telp'] }} @endif
                        @if($sekolah['npsn']) &middot; NPSN {{ $sekolah['npsn'] }} @endif
                    </p>
                @endif
            </div>
            @if($kopLogoKanan)
                <img src="{{ $kopLogoKanan }}" class="logo" alt="Logo Kanan">
            @else
                <span class="logo"></span>
            @endif
        </div>

        {{-- Judul Dokumen --}}
        <div class="judul-wrap">
            <div class="judul">REKAPITULASI POIN KEDISIPLINAN SISWA</div>
            <div class="subjudul">Lampiran Informasi untuk Orang Tua / Wali Murid</div>
        </div>

        {{-- Identitas Siswa --}}
        <table class="ident-grid">
            <tr>
                <td class="l">Nama Siswa</td>
                <td class="s">:</td>
                <td class="v">{{ $siswa->nama }}</td>
                <td class="l">Semester</td>
                <td class="s">:</td>
                <td class="v">{{ $sem?->semester ? $sem->semester . ' (' . ($sem->semester == 1 ? 'Ganjil' : 'Genap') . ')' : '-' }}</td>
            </tr>
            <tr>
                <td class="l">NIS / NISN</td>
                <td class="s">:</td>
                <td class="v">{{ $siswa->nis }} / {{ $siswa->nisn ?: '-' }}</td>
                <td class="l">Tahun Pelajaran</td>
                <td class="s">:</td>
                <td class="v">{{ $sem?->tahun ?? '-' }}</td>
            </tr>
            <tr>
                <td class="l">Kelas</td>
                <td class="s">:</td>
                <td class="v">{{ $siswa->kelas ? $siswa->kelas->tingkat.$siswa->kelas->kelas : '-' }}</td>
                <td class="l">Tanggal Cetak</td>
                <td class="s">:</td>
                <td class="v">{{ $tanggal }}</td>
            </tr>
        </table>

        {{-- Ringkasan Poin --}}
        <div class="summary-card">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-label">Modal Poin Awal</div>
                    <div class="summary-value" style="color:#0f172a;">100</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Pengurangan</div>
                    <div class="summary-value" style="color:#dc2626;">-{{ $totalKurang }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Total Penambahan</div>
                    <div class="summary-value" style="color:#16a34a;">+{{ $totalTambah }}</div>
                </div>
                <div class="summary-item">
                    <div class="summary-label">Sisa Poin Akhir</div>
                    <div class="summary-value {{ $sisa < 50 ? 'color:#dc2626;' : ($sisa < 75 ? 'color:#d97706;' : 'color:#16a34a;') }}">
                        {{ $sisa }}
                    </div>
                </div>
            </div>
            <div class="status-box">
                <div>
                    <b>Status Pembinaan:</b>
                    @if($sisa >= 75)
                        <span class="badge-status badge-aman">Aman / Sangat Baik</span>
                    @elseif($sisa >= 50)
                        <span class="badge-status badge-sp1">Surat Peringatan 1 (SP 1)</span>
                    @elseif($sisa >= 25)
                        <span class="badge-status badge-sp2">Surat Peringatan 2 (SP 2)</span>
                    @else
                        <span class="badge-status badge-sp3">Surat Peringatan 3 (SP 3 / Panggilan Ortu)</span>
                    @endif
                </div>
                <div style="font-size: 11px; color: #555;">
                    Basis Evaluasi: Standar Tata Tertib 100 Poin
                </div>
            </div>
        </div>

        {{-- Tabel Riwayat Ledger --}}
        <div class="section-title">Rincian Riwayat Catatan Poin Kedisiplinan</div>
        <table class="tbl">
            <thead>
                <tr>
                    <th class="t-no">No</th>
                    <th class="t-tgl">Tanggal</th>
                    <th class="t-kode">Kode</th>
                    <th>Uraian Aturan / Pelanggaran / Penghargaan</th>
                    <th class="t-jenis">Kategori</th>
                    <th class="t-poin">Poin</th>
                    <th class="t-sisa">Sisa Poin</th>
                </tr>
            </thead>
            <tbody>
                <tr style="background: #f8fafc;">
                    <td class="t-no">-</td>
                    <td class="t-tgl">-</td>
                    <td class="t-kode">-</td>
                    <td><b>Poin Awal Kedisiplinan Siswa</b></td>
                    <td class="t-jenis">Standar Awal</td>
                    <td class="t-poin" style="color: #475569;">100</td>
                    <td class="t-sisa">100</td>
                </tr>
                @forelse($ledger as $i => $l)
                <tr>
                    <td class="t-no">{{ $i + 1 }}</td>
                    <td class="t-tgl">{{ $l['row']->tanggal ? $l['row']->tanggal->isoFormat('D MMM Y') : '-' }}</td>
                    <td class="t-kode">{{ $l['row']->aturan?->kode ?? '-' }}</td>
                    <td>{{ $l['row']->aturan?->aturan ?? '-' }}</td>
                    <td class="t-jenis">
                        @if(($l['row']->aturan?->jenis ?? '') === 'kurang')
                            <span style="color: #dc2626; font-weight: 600;">Pelanggaran</span>
                        @else
                            <span style="color: #16a34a; font-weight: 600;">Penghargaan</span>
                        @endif
                    </td>
                    <td class="t-poin" style="{{ $l['delta'] < 0 ? 'color: #dc2626;' : 'color: #16a34a;' }}">
                        {{ $l['delta'] > 0 ? '+' : '' }}{{ $l['delta'] }}
                    </td>
                    <td class="t-sisa">{{ $l['sisa'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="kosong">
                        Belum ada catatan pelanggaran atau pengurangan poin. Siswa mematuhi seluruh tata tertib sekolah dengan baik.
                    </td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5" style="text-align: right; padding-right: 8px;">Sisa Poin Akumulatif Saat Ini</td>
                    <td colspan="2" style="text-align: center; font-size: 13px; {{ $sisa < 50 ? 'color: #dc2626;' : ($sisa < 75 ? 'color: #d97706;' : 'color: #16a34a;') }}">
                        {{ $sisa }} Poin ({{ $peringatan !== '-' ? $peringatan : 'Aman' }})
                    </td>
                </tr>
            </tfoot>
        </table>

        {{-- Ketentuan Poin untuk Orang Tua --}}
        <div class="catatan-box">
            <b>Ketentuan Sistem Poin Kedisiplinan & Informasi untuk Orang Tua / Wali Murid:</b>
            <ol>
                <li>Setiap siswa dibekali modal awal sebesar <b>100 poin</b> sebagai bentuk kepercayaan sekolah terhadap kedisiplinan siswa.</li>
                <li>Setiap pelanggaran tata tertib sekolah akan mengurangi poin siswa sesuai bobot aturan yang telah ditetapkan pihak sekolah.</li>
                <li>Penambahan poin dapat diberikan apabila siswa meraih prestasi, menunjukkan perilaku terpuji, atau keaktifan positif.</li>
                <li>Tahapan pembinaan kedisiplinan:
                    <ul>
                        <li><b>Sisa Poin 50 – 74:</b> Diberikan <i>Surat Peringatan 1 (SP 1)</i> sebagai teguran awal dan pembinaan oleh Wali Kelas / Guru BK.</li>
                        <li><b>Sisa Poin 25 – 49:</b> Diberikan <i>Surat Peringatan 2 (SP 2)</i> dan penugasan pembinaan kedisiplinan terarah.</li>
                        <li><b>Sisa Poin &lt; 25:</b> Diberikan <i>Surat Peringatan 3 (SP 3)</i> serta pemanggilan Orang Tua / Wali Murid ke sekolah untuk penanganan intensif bersama pihak Kesiswaan dan Kepala Sekolah.</li>
                    </ul>
                </li>
                <li>Dokumen ini dilampirkan sebagai bentuk transparansi dan sarana komunikasi berkala antara pihak sekolah dan Orang Tua / Wali Murid dalam mendampingi perkembangan karakter peserta didik.</li>
            </ol>
        </div>

        {{-- Kolom Tanda Tangan 3 Pihak --}}
        <div class="ttd-wrap">
            <div class="ttd-grid">
                <div class="ttd-col">
                    <p>Mengetahui / Menerima,<br>Orang Tua / Wali Murid</p>
                    <p class="nm" style="font-weight: normal;">( .................................................... )</p>
                    <p class="nik">&nbsp;</p>
                </div>
                <div class="ttd-col">
                    <p>{{ $sekolah['kota'] ?: 'Tanjungpinang' }}, {{ $tanggal }}<br>Wali Kelas</p>
                    @if($walikelas?->nama)
                        <p class="nm" style="text-decoration: underline;">{{ $walikelas->nama }}</p>
                        <p class="nik">NIK. {{ $walikelas->nik ?? '-' }}</p>
                    @else
                        <p class="nm" style="font-weight: normal;">( .................................................... )</p>
                        <p class="nik">&nbsp;</p>
                    @endif
                </div>
                <div class="ttd-col">
                    <p>Mengetahui,<br>Kepala Sekolah</p>
                    <p class="nm" style="text-decoration: underline;">{{ $kepala }}</p>
                    @if(!empty($nipKepala) && $nipKepala !== '-')
                        <p class="nik">NIP/NIK. {{ $nipKepala }}</p>
                    @else
                        <p class="nik">&nbsp;</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>
