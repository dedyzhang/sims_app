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
            <p class="t1">BERITA ACARA PELAKSANAAN {{ strtoupper($paket?->nama ?: 'UJIAN') }}</p>
            <p class="t2">{{ strtoupper($paket?->jenisLabel() ?? 'UJIAN') }}
                @if($paket?->semester) {{ $paket->semester->semester == 1 ? 'GANJIL' : 'GENAP' }} @endif
            </p>
            <p class="t3">{{ strtoupper($namaSekolah) }}</p>
            @if($paket?->semester)<p class="t4">TAHUN PELAJARAN {{ $paket->semester->tahun }}</p>@endif
        </div>

        <p class="narasi">
            Pada hari ini <b>{{ \App\Support\TanggalIndo::hari($beritaAcara->tanggal) }}</b>,
            tanggal <b>{{ $beritaAcara->tanggal->format('d') }}</b>
            bulan <b>{{ \App\Support\TanggalIndo::bulan($beritaAcara->tanggal) }}</b>
            tahun <b>{{ $beritaAcara->tanggal->format('Y') }}</b>,
            telah diselenggarakan {{ $paket?->jenisLabel() ?? 'Ujian' }}
            @if($beritaAcara->jam_mulai_aktual) dari pukul <b>{{ substr($beritaAcara->jam_mulai_aktual, 0, 5) }}</b>
                @if($beritaAcara->jam_selesai_aktual) sampai dengan pukul <b>{{ substr($beritaAcara->jam_selesai_aktual, 0, 5) }}</b>@endif
            @elseif($sesi) dari pukul <b>{{ substr($sesi->jam_mulai, 0, 5) }}</b> sampai dengan pukul <b>{{ substr($sesi->jam_selesai, 0, 5) }}</b>
            @endif.
        </p>

        <table class="fields">
            <tr><td class="lbl">Sekolah/Madrasah</td><td class="colon">:</td><td>{{ $namaSekolah }}</td></tr>
            <tr><td class="lbl">Mata Pelajaran</td><td class="colon">:</td><td>{{ $beritaAcara->mapelNama() ?: '—' }}</td></tr>
            <tr><td class="lbl">Sesi / Ruangan</td><td class="colon">:</td><td>{{ $ruangan->nama }}{{ $sesi?->label ? ' — Sesi ' . $sesi->label : '' }}</td></tr>
            <tr><td class="lbl">Jumlah Peserta Seharusnya</td><td class="colon">:</td><td>{{ $jumlahSeharusnya }} siswa</td></tr>
            <tr><td class="lbl">Jumlah Hadir (Ikut Ujian)</td><td class="colon">:</td><td>{{ $beritaAcara->jumlah_hadir ?? '—' }} siswa</td></tr>
            <tr><td class="lbl">Jumlah Tidak Hadir</td><td class="colon">:</td><td>{{ $beritaAcara->jumlah_tidak_hadir ?? '—' }} siswa</td></tr>
        </table>

        <p class="catatan-title">Catatan Selama {{ $paket?->jenisLabel() ?? 'Ujian' }}:</p>
        <div class="catatan-box">{{ $beritaAcara->catatan_kejadian ?: 'Tidak ada catatan khusus.' }}</div>

        <p class="ttd-heading">Yang Membuat Berita Acara:</p>
        <table class="ttd">
            <tr>
                <td>
                    <p class="peran">Pengawas Ruang</p>
                    <p class="nm">{{ $beritaAcara->pengawas?->nama ?? '.................................' }}</p>
                    <p class="nik">NIK: {{ $beritaAcara->pengawas?->nik ?? '.................................' }}</p>
                </td>
                <td>
                    <p class="peran">Mengetahui,<br>Kepala Sekolah</p>
                    <p class="nm">{{ $kepsekNama ?: '.................................' }}</p>
                </td>
            </tr>
        </table>
    </div>
