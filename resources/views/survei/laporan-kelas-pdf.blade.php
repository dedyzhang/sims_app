<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Survei Pembelajaran - {{ $namaKelas }}</title>
    <style>
        @page { margin: 34pt 40pt 42pt; }
        body { margin: 0; font: 10pt/1.45 "DejaVu Sans", sans-serif; color: #17263a; }
        h1, h2, h3, p { margin: 0; }
        header { border-top: 5pt solid #17648c; padding: 15pt 0 12pt; }
        header h1 { font-size: 22pt; line-height: 1.2; margin: 6pt 0; }
        .rahasia { color: #89412a; font-weight: bold; font-size: 9pt; text-transform: uppercase; }
        .meta, .catatan { font-size: 9pt; color: #46596c; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th { background: #eaf3f9; text-align: left; }
        th, td { padding: 6pt 8pt; border-bottom: 0.6pt solid #c9d7e2; vertical-align: top; }
        .angka { text-align: right; }
        .catatan { margin: 9pt 0 15pt; }
        .judul-bagian { font-size: 13pt; margin: 10pt 0 6pt; page-break-after: avoid; }
        .guru { margin: 0 0 10pt; page-break-inside: avoid; }
        .guru h3 { background: #eaf3f9; padding: 6pt 8pt; font-size: 10pt; }
        .guru p { margin: 4pt 8pt; }
        .guru th { background: #f2f7f5; width: 50%; }
        .guru th.perbaikan { background: #faf3e9; }
        ul { margin: 0; padding-left: 13pt; }
        li { margin: 0 0 3pt; }
        footer { position: fixed; bottom: -24pt; width: 100%; border-top: 0.6pt solid #c9d7e2; padding-top: 4pt; font-size: 8pt; color: #46596c; }
    </style>
</head>
<body>
    @php
        $peran = ['mapel' => 'Pembelajaran mata pelajaran', 'wali' => 'Pendampingan wali kelas'];
        $formatSkor = fn ($skor) => number_format($skor, 2, ',', '.').' / 5';
        $formatJumlah = fn ($jumlah) => number_format($jumlah, 0, ',', '.');
    @endphp

    <footer>SIMS | Survei Pembelajaran | {{ $namaKelas }} | Rahasia</footer>
    <header>
        <p class="rahasia">Rahasia · Untuk Kepala Sekolah</p>
        <h1>Laporan Survei Pembelajaran</h1>
        <p><strong>{{ $namaKelas }}</strong> · {{ $sekolah }}</p>
        <p class="meta">{{ $periode->judul }} · Dicetak {{ now()->format('d/m/Y') }}</p>
    </header>

    <h2 class="judul-bagian">Ringkasan skor kelas</h2>
    <table>
        <thead>
            <tr>
                <th style="width: 46%">Aspek</th>
                <th style="width: 25%" class="angka">Skor rata-rata</th>
                <th class="angka">Jawaban dinilai</th>
            </tr>
        </thead>
        <tbody>
            @foreach($peran as $jenis => $label)
                <tr>
                    <td>{{ $label }}</td>
                    <td class="angka"><strong>{{ $skorKelas[$jenis]['skor'] === null ? 'Data belum cukup' : $formatSkor($skorKelas[$jenis]['skor']) }}</strong></td>
                    <td class="angka">{{ $formatJumlah($skorKelas[$jenis]['jawaban_dinilai']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="catatan">Skor dihitung dari rata-rata seluruh jawaban yang memenuhi syarat di kelas ini. Setiap butir per mapel/guru atau wali kelas memerlukan minimal {{ $minimum }} jawaban yang dapat dinilai. Pilihan “Tidak tahu” dikecualikan. Untuk butir bertanda “skor dibalik”, skor tinggi berarti perilaku lebih jarang terjadi. Jumlah jawaban dihitung per butir. Kekuatan memiliki skor minimal 4,00 dan area perbaikan memiliki skor di bawah 3,50, dihitung sebelum pembulatan.</p>

    <h2 class="judul-bagian">Analisis guru di {{ $namaKelas }}</h2>
    @foreach($gurus as $guru)
        @foreach($guru['peran'] as $jenis => $ringkasan)
            <div class="guru">
                <h3>{{ $guru['nama'] }} · {{ $jenis === 'wali' ? 'Wali kelas' : $ringkasan['targets']->pluck('pelajaran_nama')->unique()->implode(', ') }}</h3>
                @if($ringkasan['skor'] === null)
                    <p>Data belum cukup.</p>
                @else
                    <p><strong>{{ $formatSkor($ringkasan['skor']) }}</strong> · {{ $formatJumlah($ringkasan['jawaban_dinilai']) }} jawaban dinilai</p>
                    <table>
                        <tr><th>Kekuatan</th><th class="perbaikan">Area perbaikan</th></tr>
                        <tr>
                            @foreach(['kekuatan', 'perbaikan'] as $kategori)
                                <td>
                                    @if($ringkasan[$kategori]->isEmpty())
                                        Tidak ada butir yang memenuhi kriteria.
                                    @else
                                        <ul>
                                            @foreach($ringkasan[$kategori] as $butir)
                                                <li>
                                                    <strong>{{ $formatSkor($butir['rata']) }}</strong> · {{ $butir['text'] }}
                                                    @if($butir['negatif'])
                                                        <em>(skor dibalik)</em>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ul>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </table>
                @endif
            </div>
        @endforeach
    @endforeach
</body>
</html>
