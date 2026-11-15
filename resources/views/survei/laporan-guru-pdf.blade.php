<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan Evaluasi Guru - {{ $periode->judul }}</title>
    <style>
        @page { margin: 30pt 34pt 38pt; }
        body { margin: 0; font: 8.5pt/1.35 "DejaVu Sans", sans-serif; color: #243246; }
        h1, h2, h3, p { margin: 0; }
        .kop { text-align: center; line-height: 1.2; padding: 0 0 7pt; border-bottom: 2pt solid #244b70; margin-bottom: 7pt; }
        .kop-sekolah { font-size: 10pt; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5pt; }
        h1 { font-size: 14pt; margin-top: 3pt; line-height: 1.2; }
        .kop-subjudul { font-size: 9pt; margin-top: 3pt; }
        .kop-meta { font-size: 7pt; color: #5b6674; margin-top: 4pt; }
        .kop-meta span { padding: 0 4pt; }
        h2 { font-size: 9pt; margin: 6pt 0 4pt; padding: 4pt 7pt; background: #eaf0f6; border-left: 3pt solid #244b70; }
        h3 { font-size: 8.5pt; margin: 7pt 0 4pt; }
        h2, h3 { page-break-after: avoid; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { padding: 4pt 5pt; vertical-align: top; text-align: left; overflow-wrap: break-word; word-wrap: break-word; }
        th { font-size: 7.5pt; background: #edf2f7; font-weight: bold; }
        .data th, .data td { border: 0.5pt solid #c9d3de; }
        .data tbody tr:nth-child(even) { background: #f8fafc; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .identitas { margin-bottom: 7pt; }
        .identitas td { padding: 2pt 4pt; }
        .identitas .label { width: 17%; color: #586574; }
        .identitas .isi { width: 33%; font-weight: bold; }
        .nilai-ringkas td { border: 0.5pt solid #c9d3de; padding: 7pt 9pt; background: #f8fafc; }
        .nilai-utama { width: 25%; background: #eaf0f6 !important; }
        .nilai-utama strong { font-size: 24pt; line-height: 1.15; color: #193f65; }
        .nilai-utama > span:not(.label-kecil) { font-size: 10pt; }
        .label-kecil { display: block; font-size: 7pt; color: #586574; margin-bottom: 3pt; }
        .ringkas-angka { font-size: 12pt; font-weight: bold; }
        .angka { text-align: right; }
        .tengah { text-align: center; }
        .muted { color: #586574; font-size: 7pt; }
        .catatan-metode { margin-top: 5pt; color: #586574; font-size: 7pt; line-height: 1.4; }
        .negatif { font-size: 7pt; font-weight: bold; color: #6b4d1f; }
        .metode { padding: 7pt 8pt; background: #f3f6f9; margin-top: 8pt; font-size: 7pt; color: #485769; }
        .halaman-baru { page-break-before: always; }
        .teks-siswa { white-space: pre-wrap; }
        .tindak-lanjut td { height: 32pt; font-size: 7.5pt; }
        .isian { border-bottom: 0.5pt dotted #97a4b3; height: 8pt; }
        .blok { page-break-inside: avoid; }
        .paraf { margin-top: 5pt; }
        .paraf td { width: 33.33%; text-align: center; font-size: 7.5pt; }
        .ruang-paraf { height: 20pt; }
        .paraf-nama { border-bottom: 0.5pt solid #a5b1bf; padding-bottom: 3pt; }
        footer { position: fixed; bottom: -23pt; width: 100%; border-top: 0.5pt solid #c9d3de; padding-top: 4pt; color: #586574; font-size: 6.5pt; }
    </style>
</head>
<body>
@php
    $labelPeran = ['mapel' => 'Guru mata pelajaran', 'wali' => 'Wali kelas'];
    $adaRingkasan = $gurus->count() > 1;
    $lembarKe = 0;
@endphp
<footer>SIMS | Evaluasi berbasis survei siswa | {{ $sekolah }} | Dicetak {{ now()->format('d/m/Y H:i') }} | Rahasia</footer>
@if($adaRingkasan || $gurus->isEmpty())
    @include('survei.partials.kop-evaluasi-guru', ['judulLembar' => 'REKAP EVALUASI GURU', 'subjudulLembar' => 'Ringkasan hasil survei peserta didik untuk kepala sekolah dan waka'])
    <h2>A. Ringkasan seluruh guru</h2>
    <table class="data">
        <thead><tr><th style="width: 5%">No.</th><th style="width: 35%">Nama guru / peran</th><th class="angka" style="width: 10%">Kelas</th><th class="angka" style="width: 18%">Jawaban dinilai</th><th class="angka" style="width: 14%">Skor / 5</th><th class="angka" style="width: 18%">Nilai / 100</th></tr></thead>
        <tbody>
        @php($nomor = 0)
        @forelse($gurus as $guru)
            @foreach($guru['peran'] as $jenis => $ringkasan)
                <tr>
                    <td>{{ ++$nomor }}</td><td><strong>{{ $guru['nama'] }}</strong><p class="muted">{{ $labelPeran[$jenis] ?? $jenis }}</p></td>
                    <td class="angka">{{ $ringkasan['kelas'] }}</td><td class="angka">{{ $ringkasan['jawaban_dinilai'] }}</td>
                    <td class="angka">{{ $ringkasan['skor'] === null ? '-' : number_format($ringkasan['skor'], 2, ',', '.') }}</td>
                    <td class="angka"><strong>{{ $ringkasan['nilai'] === null ? 'Belum cukup' : number_format($ringkasan['nilai'], 2, ',', '.') }}</strong></td>
                </tr>
            @endforeach
        @empty
            <tr><td colspan="6">Belum ada sasaran survei.</td></tr>
        @endforelse
        </tbody>
    </table>
    <h2>B. Petunjuk penggunaan</h2>
    <p>Gunakan rekap untuk melihat gambaran umum, lalu baca lembar indikator dan catatan setiap guru sebelum berdiskusi. Peran guru mata pelajaran dan wali kelas dihitung terpisah. Data belum cukup tidak diberikan nilai nol.</p>
    <div class="metode">
        <strong>Kedudukan laporan.</strong> Bahan pendukung evaluasi berbasis persepsi siswa. Penilaian kinerja resmi memerlukan bukti observasi, diskusi, tindak lanjut, dan refleksi oleh pejabat penilai.
        <p style="margin-top: 4pt">Nilai = skor rata-rata × 20, dengan rentang 20-100. Jawaban "Tidak tahu" dikeluarkan; butir negatif dibalik. Jumlah jawaban butir bukan jumlah siswa unik. @if(!$lihatCatatan) Versi ini tidak memuat komentar asli; pembuat survei dapat menyiapkan versi lengkap. @endif</p>
    </div>
@endif
@foreach($gurus as $guru)
    @foreach($guru['peran'] as $jenis => $ringkasan)
        <section class="{{ $adaRingkasan || $lembarKe++ > 0 ? 'halaman-baru' : '' }}">
            @include('survei.partials.kop-evaluasi-guru', ['judulLembar' => 'LAPORAN EVALUASI GURU', 'subjudulLembar' => 'Lembar 1 | Hasil penilaian berdasarkan survei peserta didik'])
            <table class="identitas">
                <tr><td class="label">Nama guru</td><td class="isi">{{ $guru['nama'] }}</td><td class="label">Peran</td><td class="isi">{{ $labelPeran[$jenis] ?? $jenis }}</td></tr>
                <tr><td class="label">Mata pelajaran</td><td class="isi">{{ $jenis === 'wali' ? 'Pendampingan wali kelas' : $ringkasan['targets']->pluck('pelajaran_nama')->unique()->implode(', ') }}</td><td class="label">Kelas</td><td class="isi">{{ $ringkasan['targets']->pluck('kelas_nama')->unique()->implode(', ') }}</td></tr>
                <tr><td class="label">Periode survei</td><td colspan="3">{{ $periode->mulai->format('d/m/Y') }} s.d. {{ $periode->selesai->format('d/m/Y') }}</td></tr>
            </table>
            <h2>A. Ringkasan hasil</h2>
            <table class="nilai-ringkas">
                <tr>
                    <td class="nilai-utama"><span class="label-kecil">NILAI SURVEI</span><strong>{{ $ringkasan['nilai'] === null ? '-' : number_format($ringkasan['nilai'], 2, ',', '.') }}</strong><span> / 100</span></td>
                    <td><span class="label-kecil">SKOR RATA-RATA</span><p class="ringkas-angka">{{ $ringkasan['skor'] === null ? 'Belum cukup data' : number_format($ringkasan['skor'], 2, ',', '.').' / 5' }}</p></td>
                    <td><span class="label-kecil">CAKUPAN DATA</span><strong>{{ $ringkasan['kelas'] }} kelas | {{ $ringkasan['isian'] }} isian</strong><p class="muted">{{ $ringkasan['jawaban_dinilai'] }} jawaban butir dinilai</p></td>
                </tr>
            </table>
            <p class="catatan-metode">Isian adalah formulir per sasaran, bukan jumlah siswa unik. Skor hanya memakai butir dengan minimal {{ $minimum }} jawaban valid per kelas dan sasaran.</p>
            <h2>B. Rincian indikator penilaian</h2>
            <p class="catatan-metode">{{ $guru['nama'] }} | {{ $labelPeran[$jenis] ?? $jenis }} | Rincian skor olahan</p>
            <table class="data">
                <thead>
                    <tr><th style="width: 6%">No.</th><th style="width: 58%">Indikator sesuai instrumen survei</th><th class="angka" style="width: 10%">Jawaban valid</th><th class="angka" style="width: 13%">Skor / 5</th><th class="angka" style="width: 13%">Nilai / 100</th></tr>
                </thead>
                <tbody>
                    @forelse($ringkasan['butir'] as $butir)
                        <tr>
                            <td>{{ $butir['index'] + 1 }}</td>
                            <td>{{ $butir['text'] }}@if($butir['negatif']) <span class="negatif">(R)</span>@endif</td>
                            <td class="angka">{{ $butir['n'] }}</td>
                            <td class="angka">{{ number_format($butir['rata'], 2, ',', '.') }}</td>
                            <td class="angka"><strong>{{ number_format($butir['rata'] * 20, 2, ',', '.') }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Belum cukup jawaban yang dapat dinilai. Tidak diberikan nilai nol.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="catatan-metode"><strong>(R)</strong> Butir negatif: skor dibalik sehingga nilai tinggi berarti perilaku tersebut lebih jarang terjadi. Kriteria pembinaan sebelum pembulatan: kekuatan ≥ 4,00; perbaikan &lt; 3,50. Bukan predikat PKG.</p>
            <p class="catatan-metode"><strong>Ringkasan pembinaan.</strong> Kekuatan: {{ $ringkasan['kekuatan']->isEmpty() ? 'belum ada indikator yang memenuhi kriteria' : 'indikator '.$ringkasan['kekuatan']->pluck('index')->map(fn ($index) => $index + 1)->implode(', ') }}. Fokus perbaikan: {{ $ringkasan['perbaikan']->isEmpty() ? 'belum ada indikator yang memenuhi kriteria' : 'indikator '.$ringkasan['perbaikan']->pluck('index')->map(fn ($index) => $index + 1)->implode(', ') }}.</p>
            <div class="metode"><strong>Cara membaca.</strong> Nilai = skor rata-rata × 20 (rentang 20-100; skor 4,00 menjadi 80,00). Rata-rata dibobot menurut jumlah jawaban butir yang memenuhi syarat; "Tidak tahu" dikeluarkan. Komentar tidak memengaruhi skor. Laporan menjadi bahan pendukung evaluasi; penetapan kinerja resmi mempertimbangkan observasi dan refleksi oleh kepala sekolah.</div>
        </section>
        <section class="halaman-baru">
            @include('survei.partials.kop-evaluasi-guru', ['judulLembar' => 'CATATAN DAN TINDAK LANJUT', 'subjudulLembar' => 'Lembar 2 | Bahan diskusi guru, waka, dan kepala sekolah'])
            <table class="identitas">
                <tr><td class="label">Nama guru</td><td class="isi">{{ $guru['nama'] }}</td><td class="label">Peran</td><td class="isi">{{ $labelPeran[$jenis] ?? $jenis }}</td></tr>
                <tr><td class="label">Kelas</td><td colspan="3">{{ $ringkasan['targets']->pluck('kelas_nama')->unique()->implode(', ') }}</td></tr>
            </table>
            <h2>A. Rekap catatan siswa</h2>
            @if($lihatCatatan)
                <p class="catatan-metode">{{ $ringkasan['catatan']->sum('jumlah') }} catatan. Teks asli dikelompokkan menurut pertanyaan dan kesamaan tulisan. Frekuensi adalah jumlah catatan, bukan siswa unik. Sasaran dengan kurang dari {{ $minimum }} respons tidak disertakan.</p>
                @forelse($ringkasan['catatan'] as $bagian)
                    <h3>{{ $bagian['pertanyaan'] }}</h3>
                    <table class="data">
                        <thead><tr><th style="width: 6%">No.</th><th style="width: 76%">{{ $guru['nama'] }} | Catatan siswa</th><th class="angka" style="width: 18%">Frekuensi</th></tr></thead>
                        <tbody>
                            @foreach($bagian['daftar'] as $catatan)
                                <tr><td>{{ $loop->iteration }}</td><td class="teks-siswa">{{ $catatan['teks'] }}</td><td class="angka">{{ $catatan['jumlah'] }} catatan</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @empty
                    <p>Belum ada catatan yang dapat ditampilkan.</p>
                @endforelse
                <p class="catatan-metode">Tinjau teks bebas sebelum membagikan laporan karena siswa dapat menyebut data pribadi.</p>
            @else
                <p>Komentar asli hanya tersedia pada versi yang disiapkan pembuat survei. Gunakan lembar ini untuk mencatat hasil diskusi dan bukti pendukung.</p>
            @endif
            <h2>B. Rencana tindak lanjut yang disepakati</h2>
            <p class="catatan-metode">Diisi setelah diskusi bersama guru; langkah, penanggung jawab, serta target waktu belum ditetapkan otomatis.</p>
            <table class="data tindak-lanjut">
                <thead><tr><th style="width: 34%">Fokus perbaikan / bukti</th><th style="width: 42%">Langkah dan indikator keberhasilan</th><th style="width: 24%">Penanggung jawab / target waktu</th></tr></thead>
                <tbody>
                    @forelse($ringkasan['perbaikan'] as $butir)
                        <tr><td>Indikator {{ $butir['index'] + 1 }}: {{ $butir['text'] }}@if($butir['negatif']) (R)@endif</td><td></td><td></td></tr>
                    @empty
                        <tr><td>Fokus hasil diskusi:</td><td></td><td></td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="blok">
                <h2>C. Refleksi dan hasil pemantauan</h2>
                <p class="muted">Catat perubahan praktik, bukti kemajuan, dan tanggal evaluasi berikutnya.</p>
                <div class="isian"></div><div class="isian"></div>
                <h2>D. Verifikasi hasil diskusi</h2>
                <p class="muted">Tanggal diskusi: ...................................... | Paraf diisi setelah laporan ditinjau bersama.</p>
                <table class="paraf">
                    <tr><td>Guru yang dievaluasi</td><td>Waka / pendamping</td><td>Kepala sekolah</td></tr>
                    <tr><td class="ruang-paraf"></td><td></td><td></td></tr>
                    <tr><td><span class="paraf-nama">{{ $guru['nama'] }}</span></td><td>......................................</td><td>......................................</td></tr>
                </table>
                <p class="catatan-metode">Rujukan susunan pembinaan: <a href="https://pusatinformasi.ult.kemendikdasmen.go.id/hc/id/articles/40451104394521-Tentang-Alur-Penilaian-Praktik-Kinerja-Oleh-Kepala-Sekolah" style="color: #485769">Panduan Pengelolaan Kinerja Guru, Kemendikdasmen</a>. Format SIMS ini adalah rekap pendukung dari survei siswa.</p>
            </div>
        </section>
    @endforeach
@endforeach
</body>
</html>
