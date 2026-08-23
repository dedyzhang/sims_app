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
            <p class="t1">DAFTAR HADIR PESERTA {{ strtoupper($paket?->nama ?: 'UJIAN') }}</p>
        </div>

        <table class="info">
            <tr><td class="lbl">Ruangan</td><td class="colon">:</td><td>{{ $ruangan->nama }}{{ $sesi?->label ? ' — Sesi ' . $sesi->label : '' }}</td></tr>
            <tr><td class="lbl">Tanggal</td><td class="colon">:</td><td>{{ \App\Support\TanggalIndo::panjangDenganHari(\Carbon\Carbon::parse($tanggal)) }}</td></tr>
            <tr><td class="lbl">Jumlah Peserta</td><td class="colon">:</td><td>{{ $ruangan->peserta->count() }} siswa</td></tr>
        </table>

        <table class="roster">
            <thead>
                <tr>
                    <th class="no">No</th>
                    <th>Nama Siswa</th>
                    <th class="nis">NIS</th>
                    <th class="kls">Kelas</th>
                    <th class="status">Status</th>
                    <th class="ket">Keterangan</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ruangan->peserta->sortBy(fn($p) => $p->siswa?->nama) as $i => $p)
                @php $h = $hadirBySiswa->get($p->id_siswa); @endphp
                <tr>
                    <td class="no">{{ $i + 1 }}</td>
                    <td>{{ $p->siswa?->nama ?? '(siswa terhapus)' }}</td>
                    <td class="nis">{{ $p->siswa?->nis }}</td>
                    <td class="kls">{{ $p->siswa?->kelas?->tingkat }}{{ $p->siswa?->kelas?->kelas }}</td>
                    <td class="status">{{ $h?->statusLabel() ?? '—' }}</td>
                    <td class="ket">{{ $h?->keterangan }}</td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center; padding:8px;">Belum ada peserta di ruangan ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="ttd-heading">
            <div class="ttd">
                <p class="peran">{{ \App\Support\TanggalIndo::panjang(\Carbon\Carbon::parse($tanggal)) }}<br>Pengawas Ruang</p>
                <p class="nm">.................................</p>
            </div>
        </div>
    </div>
