<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analisis Hasil Pre-Test — {{ $ujian->judul }}</title>
    <style>
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        @page {
            size: landscape;
            margin: 10mm 10mm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 9.5pt;
            color: #0f172a;
            margin: 0;
            padding: 15px;
            background: #f1f5f9;
        }
        .page {
            max-width: 297mm;
            min-width: 260mm;
            margin: 0 auto;
            background: #ffffff;
            padding: 12mm 15mm;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        .kop {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px double #0f172a;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .kop-logo { width: 65px; height: 65px; object-fit: contain; }
        .kop-text { flex: 1; text-align: center; padding: 0 15px; }
        .kop-text h2 { margin: 0; font-size: 13pt; font-weight: bold; text-transform: uppercase; }
        .kop-text h3 { margin: 2px 0; font-size: 11pt; font-weight: bold; }
        .kop-text p { margin: 2px 0 0; font-size: 8.5pt; color: #475569; }

        .judul-laporan { text-align: center; margin-bottom: 15px; }
        .judul-laporan h1 { margin: 0; font-size: 13pt; text-transform: uppercase; font-weight: bold; }
        .judul-laporan p { margin: 3px 0 0; font-size: 9pt; color: #475569; font-style: italic; }

        .meta-container {
            display: flex;
            justify-content: space-between;
            gap: 20px;
            font-size: 9pt;
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            background-color: #f8fafc;
            border-radius: 6px;
            margin-bottom: 14px;
        }
        .meta-col { flex: 1; }
        .meta-row { display: flex; margin-bottom: 3px; }
        .meta-row:last-child { margin-bottom: 0; }
        .meta-label { width: 140px; color: #475569; font-weight: 500; }
        .meta-val { font-weight: bold; color: #0f172a; flex: 1; }

        .section-header {
            font-size: 10.5pt;
            font-weight: bold;
            margin: 16px 0 6px;
            color: #1e293b;
        }

        table.matriks-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8pt;
            margin-bottom: 14px;
        }
        table.matriks-table th, table.matriks-table td {
            border: 1px solid #cbd5e1;
            padding: 4px 5px;
            text-align: center;
        }
        table.matriks-table th {
            background-color: #4f46e5;
            color: #ffffff;
            font-weight: bold;
            font-size: 8pt;
            line-height: 1.2;
        }
        table.matriks-table tbody tr:nth-child(even) { background-color: #f8fafc; }
        .text-left { text-align: left !important; }
        .text-center { text-align: center !important; }
        .text-right { text-align: right !important; }

        .bg-kunci { background-color: #e0e7ff !important; color: #1e293b !important; font-weight: bold; font-style: italic; }
        .bg-footer-salah { background-color: #fef3c7 !important; color: #78350f !important; font-weight: bold; }

        .keterangan-box {
            font-size: 7.5pt;
            color: #64748b;
            line-height: 1.4;
            margin-top: -6px;
            margin-bottom: 16px;
            font-style: italic;
        }

        .ttd-box {
            display: flex;
            justify-content: flex-end;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .ttd-wrap { width: 220px; text-align: center; font-size: 9pt; }

        .no-print {
            position: fixed;
            top: 15px;
            right: 20px;
            display: flex;
            gap: 8px;
            z-index: 999;
        }
        .btn-print {
            background: #4f46e5;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-excel {
            background: #059669;
            color: #fff;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: bold;
            font-size: 12px;
            cursor: pointer;
            text-decoration: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .btn-close {
            background: #e2e8f0;
            color: #334155;
            border: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
        }

        @media print {
            body { background: #fff; padding: 0; }
            .page { box-shadow: none; padding: 0; max-width: 100%; border-radius: 0; }
            .no-print { display: none !important; }
            .page-break { page-break-before: always; }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button class="btn-print" onclick="window.print()">
            🖨️ Cetak Dokumen
        </button>
        <a href="{{ route('ujian.pretest.unduh-analisis', $ujian) }}" class="btn-excel">
            📥 Unduh Excel (.xlsx)
        </a>
        <button class="btn-close" onclick="window.close()">Tutup</button>
    </div>

    <div class="page">
        {{-- Kop Surat Resmi --}}
        <div class="kop">
            @if($kopLogoKiri)
                <img src="{{ $kopLogoKiri }}" class="kop-logo" alt="Logo Kiri">
            @else
                <div style="width: 65px;"></div>
            @endif

            <div class="kop-text">
                @if($kopTeksHtml)
                    {!! $kopTeksHtml !!}
                @else
                    <h2>{{ $sekolah }}</h2>
                    <h3>ANALISIS HASIL PRE-TEST &amp; UJI COBA SOAL</h3>
                    <p>Sistem Informasi Manajemen Sekolah (SIMS)</p>
                @endif
            </div>

            @if($kopLogoKanan)
                <img src="{{ $kopLogoKanan }}" class="kop-logo" alt="Logo Kanan">
            @elseif($sekolahLogoUrl)
                <img src="{{ $sekolahLogoUrl }}" class="kop-logo" alt="Logo Sekolah">
            @else
                <div style="width: 65px;"></div>
            @endif
        </div>

        {{-- Judul Laporan --}}
        <div class="judul-laporan">
            <h1>ANALISIS HASIL UJI COBA PRE-TEST</h1>
            <p>{{ $sekolah }} &bull; Diekspor/Dicetak: {{ now()->translatedFormat('d F Y, H:i') }} WIB</p>
        </div>

        {{-- Info Box / Metadata Identitas --}}
        <div class="meta-container">
            <div class="meta-col">
                <div class="meta-row">
                    <span class="meta-label">Mata Pelajaran</span>
                    <span class="meta-val">: {{ $ujian->jenisLabel() }} - {{ $ujian->pelajaran?->nama ?? '-' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Judul Pre-Test</span>
                    <span class="meta-val">: {{ $pretest->judul ?? $ujian->judul }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Total Peserta Selesai</span>
                    <span class="meta-val">: {{ $totalPeserta }} Orang</span>
                </div>
            </div>
            <div class="meta-col" style="max-width: 250px;">
                <div class="meta-row">
                    <span class="meta-label" style="width: 100px;">KKM</span>
                    <span class="meta-val">: {{ $kkm }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label" style="width: 100px;">Tuntas (L)</span>
                    <span class="meta-val">: {{ $jumlahTuntas }} Orang ({{ $persenTuntas }}%)</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label" style="width: 100px;">Tidak Tuntas (TL)</span>
                    <span class="meta-val">: {{ $jumlahTidakTuntas }} Orang</span>
                </div>
            </div>
        </div>

        {{-- ========================================== --}}
        {{-- BAGIAN A: ANALISIS NILAI                   --}}
        {{-- ========================================== --}}
        <div class="section-header">A. Analisis Nilai</div>
        <table class="matriks-table">
            <thead>
                <tr>
                    <th style="width: 28px;">No</th>
                    <th style="width: 180px;" class="text-left">Nama Peserta</th>
                    @foreach($petaA as $p)
                        <th style="min-width: 22px;">{{ $p['nomor'] }}</th>
                    @endforeach
                    <th style="width: 48px;">Jumlah</th>
                    <th style="width: 48px;">Rata-rata</th>
                    <th style="width: 28px;">L</th>
                    <th style="width: 28px;">TL</th>
                </tr>
            </thead>
            <tbody>
                @forelse($matriksA as $baris)
                <tr>
                    <td>{{ $baris['no'] }}</td>
                    <td class="text-left font-bold" style="white-space: nowrap;">
                        {{ $baris['peserta']->nama }}
                        @if($baris['peserta']->identitas)
                            <span style="font-weight: normal; font-size: 7.5pt; color: #64748b;">({{ $baris['peserta']->identitas }})</span>
                        @endif
                    </td>
                    @foreach($baris['kolom'] as $col)
                        <td>{{ $col['nilai'] }}</td>
                    @endforeach
                    <td style="font-weight: bold; background-color: #f8fafc;">{{ $baris['jumlah'] }}</td>
                    <td style="font-weight: bold; background-color: #f8fafc;">{{ number_format($baris['rata_rata'], 1) }}</td>
                    <td style="font-weight: bold; color: #059669;">{{ $baris['l'] }}</td>
                    <td style="font-weight: bold; color: #dc2626;">{{ $baris['tl'] }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ 2 + count($petaA) + 4 }}">Belum ada data peserta yang menyelesaikan pre-test.</td>
                </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="bg-footer-salah">
                    <td colspan="2" class="text-left" style="padding-left: 8px;">Jumlah jawaban salah</td>
                    @foreach($footerSalahA as $colIdx => $salah)
                        <td>{{ $soalSemua[$colIdx]->tipe === 'essay' ? '-' : $salah }}</td>
                    @endforeach
                    <td colspan="4"></td>
                </tr>
                <tr class="bg-footer-salah">
                    <td colspan="2" class="text-left" style="padding-left: 8px;">Persentase Kesalahan(%)</td>
                    @foreach($footerPersenA as $persen)
                        <td>{{ $persen }}</td>
                    @endforeach
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        </table>

        {{-- ========================================== --}}
        {{-- BAGIAN B: OBJEKTIF (JAWABAN)               --}}
        {{-- ========================================== --}}
        <div class="section-header" style="margin-top: 20px;">B. Objektif (Jawaban)</div>
        <table class="matriks-table">
            <thead>
                <tr>
                    <th style="width: 28px;">No</th>
                    <th style="width: 180px;" class="text-left">Nama</th>
                    @if(count($petaB) > 0)
                        @foreach($petaB as $info)
                            @if($info['items'])
                                @foreach($info['items'] as $it)
                                    <th style="min-width: 22px;">{{ $info['nomor'] }}</th>
                                @endforeach
                            @else
                                <th style="min-width: 22px;">{{ $info['nomor'] }}</th>
                            @endif
                        @endforeach
                    @else
                        <th>(tidak ada butir soal objektif)</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                {{-- Baris Kunci Jawaban --}}
                <tr class="bg-kunci">
                    <td></td>
                    <td class="text-left" style="padding-left: 8px;">Kunci</td>
                    @foreach($kunciB as $k)
                        <td>{{ $k }}</td>
                    @endforeach
                </tr>

                {{-- Baris Peserta --}}
                @forelse($matriksB as $baris)
                <tr>
                    <td>{{ $baris['no'] }}</td>
                    <td class="text-left font-bold" style="white-space: nowrap;">
                        {{ $baris['peserta']->nama }}
                    </td>
                    @foreach($baris['jawaban'] as $jw)
                        <td style="{{ $jw !== '-' ? 'font-weight: bold; color: #1e293b;' : 'color: #94a3b8;' }}">{{ $jw }}</td>
                    @endforeach
                </tr>
                @empty
                <tr>
                    <td colspan="{{ 2 + count($kunciB) }}">Belum ada peserta pre-test.</td>
                </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Catatan Keterangan Format Real Ujian --}}
        <div class="keterangan-box">
            Ket: kolom soal esai di Analisis Nilai berisi skor mentah (bukan 1/0) dan tidak dihitung di baris Jumlah jawaban salah/Persentase Kesalahan; soal esai tidak muncul di Objektif (Jawaban). Soal pilihan ganda kompleks &amp; mencocokkan di Analisis Nilai tetap 1 kolom (benar/salah keseluruhan) — breakdown per-opsi/pasangan benar HANYA muncul di Objektif (Jawaban).
        </div>

        {{-- Tanda Tangan Guru --}}
        <div class="ttd-box">
            <div class="ttd-wrap">
                <p style="margin: 0;">{{ \App\Models\Setting::get('sekolah_kota', 'Tempat') }}, {{ now()->translatedFormat('d F Y') }}</p>
                <p style="margin: 4px 0 0;">Guru Mata Pelajaran,</p>
                <div style="height: 55px;"></div>
                <p style="margin: 0; font-weight: bold; text-decoration: underline;">
                    {{ auth()->user()->guru?->nama ?? auth()->user()->name }}
                </p>
                @if(auth()->user()->guru?->nip)
                    <p style="margin: 2px 0 0; font-size: 8pt;">NIP. {{ auth()->user()->guru->nip }}</p>
                @endif
            </div>
        </div>
    </div>
</body>
</html>
